<?php

namespace Tests\Feature;

use App\Domain\Platform\TenantProvisioner;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoachingSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->owner = app(TenantProvisioner::class)->provision([
            'name' => 'Speak Demo',
            'code' => 'SPD',
            'slug' => 'speak-demo',
            'owner_name' => 'Owner',
            'owner_email' => 'owner@speak-demo.test',
            'password' => 'secret-pass-123',
            'owner_phone' => '9999900001',
        ])['owner'];
    }

    public function test_owner_can_update_landing_page_copy(): void
    {
        $this->actingAs($this->owner)
            ->put('/app/settings', [
                'phone' => '7418552963',
                'landing_headline' => 'Speak with clarity. Learn with confidence.',
                'landing_subheadline' => 'Spoken English courses in focused batches.',
                'primary_color' => '#0c4a6e',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $tenant = Tenant::query()->where('slug', 'speak-demo')->firstOrFail();
        $this->assertSame('7418552963', $tenant->phone);
        $this->assertSame('Speak with clarity. Learn with confidence.', $tenant->settings['landing_headline']);
        $this->assertSame('Spoken English courses in focused batches.', $tenant->settings['landing_subheadline']);

        $this->get('/c/speak-demo')
            ->assertOk()
            ->assertSee('Speak with clarity. Learn with confidence.', false)
            ->assertSee('Spoken English courses in focused batches.', false)
            ->assertSee('7418552963', false)
            ->assertDontSee('class="hero has-photo"', false);
    }

    public function test_owner_can_upload_a_tenant_hero_image(): void
    {
        $file = \Illuminate\Http\UploadedFile::fake()->image('hero.jpg', 1920, 1080);

        $this->actingAs($this->owner)
            ->post('/app/settings', [
                'phone' => '7418552963',
                'landing_headline' => 'Headline',
                'landing_subheadline' => 'Sub',
                'landing_hero' => $file,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $tenant = Tenant::query()->where('slug', 'speak-demo')->firstOrFail();
        $this->assertNotEmpty($tenant->settings['landing_hero_path'] ?? null);
        $this->assertTrue(\Illuminate\Support\Facades\Storage::disk('public')->exists($tenant->settings['landing_hero_path']));

        $this->get('/c/speak-demo')
            ->assertOk()
            ->assertSee('class="hero has-photo"', false);
    }
}
