<?php

namespace Tests\Feature;

use App\Domain\Academics\AcademicSessionResolver;
use App\Domain\Platform\TenantProvisioner;
use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AcademicSessionRolloverTest extends TestCase
{
    use RefreshDatabase;

    protected function onboard(): array
    {
        return app(TenantProvisioner::class)->provision([
            'name' => 'Rollover Coaching',
            'code' => 'ROLL',
            'slug' => 'rollover-coaching',
            'owner_name' => 'Roll Owner',
            'owner_email' => 'owner@roll.test',
            'password' => 'secret-pass-123',
        ]);
    }

    public function test_new_session_is_created_when_the_year_rolls_over(): void
    {
        Carbon::setTestNow('2026-06-01');
        $tenant = $this->onboard()['tenant'];

        $this->assertSame('2026-27', $tenant->academicSessions()->where('is_current', true)->value('name'));

        Carbon::setTestNow('2027-04-05');
        $session = app(AcademicSessionResolver::class)->current($tenant->id);

        $this->assertSame('2027-28', $session->name);
        $this->assertSame('2027-04-01', $session->starts_on->toDateString());
        $this->assertSame('2028-03-31', $session->ends_on->toDateString());

        $current = AcademicSession::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('is_current', true)
            ->pluck('name');

        $this->assertSame(['2027-28'], $current->all());
    }

    public function test_batches_created_after_rollover_belong_to_the_new_session(): void
    {
        Carbon::setTestNow('2026-06-01');
        $owner = $this->onboard()['owner'];

        Carbon::setTestNow('2027-05-10');
        $this->actingAs($owner)
            ->post('/app/academics/batches', ['name' => 'JEE 2027 Morning'])
            ->assertRedirect();

        $batch = Batch::query()->withoutGlobalScope('tenant')->where('name', 'JEE 2027 Morning')->firstOrFail();
        $session = AcademicSession::query()->withoutGlobalScope('tenant')->findOrFail($batch->academic_session_id);

        $this->assertSame('2027-28', $session->name);
    }

    public function test_roll_command_is_idempotent_across_all_coachings(): void
    {
        Carbon::setTestNow('2026-06-01');
        $tenant = $this->onboard()['tenant'];

        Carbon::setTestNow('2027-04-02');
        $this->artisan('sessions:roll')->assertSuccessful();
        $this->artisan('sessions:roll')->assertSuccessful();

        $sessions = AcademicSession::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->pluck('name')
            ->sort()
            ->values();

        $this->assertSame(['2026-27', '2027-28'], $sessions->all());
        $this->assertSame(1, AcademicSession::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('is_current', true)
            ->count());
    }

    public function test_closed_coachings_are_skipped_by_the_roll_command(): void
    {
        Carbon::setTestNow('2026-06-01');
        $tenant = $this->onboard()['tenant'];
        $tenant->update(['status' => 'closed']);

        Carbon::setTestNow('2027-04-02');
        $this->artisan('sessions:roll')->assertSuccessful();

        $this->assertSame(1, AcademicSession::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->count());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_suspended_coachings_still_roll(): void
    {
        Carbon::setTestNow('2026-06-01');
        $tenant = $this->onboard()['tenant'];
        Tenant::query()->whereKey($tenant->id)->update(['status' => 'suspended']);

        Carbon::setTestNow('2027-04-02');
        $this->artisan('sessions:roll')->assertSuccessful();

        $this->assertSame('2027-28', AcademicSession::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('is_current', true)
            ->value('name'));
    }
}
