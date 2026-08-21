<?php

namespace Tests\Feature;

use App\Domain\Platform\TenantProvisioner;
use App\Models\Batch;
use App\Models\Enquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnquiryValidationTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->owner = app(TenantProvisioner::class)->provision([
            'name' => 'Validation Coaching',
            'code' => 'VAL',
            'slug' => 'validation-coaching',
            'owner_name' => 'Owner',
            'owner_email' => 'owner@validation.test',
            'password' => 'secret-pass-123',
        ])['owner'];

        $this->actingAs($this->owner)->post('/app/academics/batches', [
            'name' => 'Class XI English',
            'weekdays' => [2, 4, 6],
            'starts_at' => '16:00',
            'ends_at' => '17:00',
        ]);
    }

    public function test_landing_enquiry_rejects_invalid_phone(): void
    {
        $batch = Batch::query()->withoutGlobalScope('tenant')->where('name', 'Class XI English')->firstOrFail();

        $this->post('/c/validation-coaching/enquiry', [
            'name' => 'Riya Sharma',
            'phone' => '71953254',
            'batch_id' => $batch->id,
        ])->assertSessionHasErrors('phone');

        $this->assertSame(0, Enquiry::query()->withoutGlobalScope('tenant')->count());
    }

    public function test_landing_enquiry_accepts_valid_indian_mobile_and_requires_batch(): void
    {
        $batch = Batch::query()->withoutGlobalScope('tenant')->where('name', 'Class XI English')->firstOrFail();

        $this->post('/c/validation-coaching/enquiry', [
            'name' => 'Riya Sharma',
            'phone' => '98765 03101',
            'batch_id' => $batch->id,
            'whatsapp_opt_in' => '1',
        ])->assertRedirect('/c/validation-coaching#enquire')
            ->assertSessionHasNoErrors();

        $enquiry = Enquiry::query()->withoutGlobalScope('tenant')->firstOrFail();
        $this->assertSame('9876503101', $enquiry->phone);
        $this->assertTrue($enquiry->whatsapp_opt_in);

        $this->from('/c/validation-coaching#enquire')
            ->post('/c/validation-coaching/enquiry', [
                'name' => 'No Batch',
                'phone' => '9876503102',
            ])->assertRedirect('/c/validation-coaching#enquire')
            ->assertSessionHasErrors('batch_id');
    }

    public function test_enquiry_index_defaults_to_open_pipeline_only(): void
    {
        Enquiry::query()->create([
            'tenant_id' => $this->owner->tenant_id,
            'owner_id' => $this->owner->id,
            'name' => 'Open Lead',
            'phone' => '9876503111',
            'source' => 'walk-in',
            'status' => 'new',
        ]);
        Enquiry::query()->create([
            'tenant_id' => $this->owner->tenant_id,
            'owner_id' => $this->owner->id,
            'name' => 'Lost Lead',
            'phone' => '9876503112',
            'source' => 'phone',
            'status' => 'lost',
        ]);
        Enquiry::query()->create([
            'tenant_id' => $this->owner->tenant_id,
            'owner_id' => $this->owner->id,
            'name' => 'Admitted Lead',
            'phone' => '9876503113',
            'source' => 'referral',
            'status' => 'admitted',
        ]);

        $this->actingAs($this->owner)
            ->get('/app/enquiries')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Enquiries/Index')
                ->where('filters.view', 'open')
                ->has('enquiries.data', 1)
                ->where('enquiries.data.0.name', 'Open Lead')
                ->where('counts.open', 1)
                ->where('counts.lost', 1)
                ->where('counts.admitted', 1)
            );

        $this->actingAs($this->owner)
            ->get('/app/enquiries?view=all')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('enquiries.data', 3));
    }
}
