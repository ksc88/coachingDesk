<?php

namespace Tests\Feature;

use App\Domain\Platform\TenantProvisioner;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformConsoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    protected function platformAdmin(): User
    {
        app(TenantProvisioner::class)->ensureRolesExist();

        $admin = User::factory()->create([
            'tenant_id' => null,
            'is_platform_admin' => true,
            'role_label' => 'platform_admin',
            'is_active' => true,
        ]);

        return $admin->assignRole('platform_admin');
    }

    protected function asGuest(): void
    {
        $this->app['auth']->logout();
        $this->flushSession();
    }

    public function test_platform_admin_can_onboard_a_coaching_from_the_console(): void
    {
        $this->actingAs($this->platformAdmin())
            ->get('/platform/coachings')
            ->assertOk();

        $this->actingAs($this->platformAdmin())
            ->post('/platform/coachings', [
                'name' => 'XYZ Coaching Classes',
                'code' => 'XYZ',
                'slug' => 'xyz-coaching',
                'owner_name' => 'XYZ Owner',
                'owner_email' => 'owner@xyz.test',
                'branch' => 'Main Campus',
            ])
            ->assertRedirect()
            ->assertSessionHas('credentials');

        $this->assertDatabaseHas('tenants', ['code' => 'XYZ', 'status' => 'active']);

        $owner = User::query()->where('email', 'owner@xyz.test')->firstOrFail();
        $this->assertTrue($owner->hasRole('owner'));
        $this->assertNotNull($owner->tenant_id);
    }

    public function test_suspending_a_coaching_blocks_its_owner_from_signing_in(): void
    {
        $result = app(TenantProvisioner::class)->provision([
            'name' => 'ABC Coaching',
            'code' => 'ABC',
            'slug' => 'abc-coaching',
            'owner_name' => 'ABC Owner',
            'owner_email' => 'owner@abc.test',
            'password' => 'secret-pass-123',
        ]);

        $tenant = $result['tenant'];

        $this->actingAs($this->platformAdmin())
            ->patch("/platform/coachings/{$tenant->id}/status", ['status' => 'suspended'])
            ->assertRedirect();

        $this->assertSame('suspended', $tenant->fresh()->status);

        $this->asGuest();
        $this->post('/login', ['email' => 'owner@abc.test', 'password' => 'secret-pass-123'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->actingAs($result['owner'])->get('/app/dashboard')->assertForbidden();

        $this->actingAs($this->platformAdmin())
            ->patch("/platform/coachings/{$tenant->id}/status", ['status' => 'active'])
            ->assertRedirect();

        $this->asGuest();
        $this->post('/login', ['email' => 'owner@abc.test', 'password' => 'secret-pass-123'])
            ->assertRedirect('/app/dashboard');
    }

    public function test_platform_admin_can_reset_an_owner_password(): void
    {
        $result = app(TenantProvisioner::class)->provision([
            'name' => 'PQR Coaching',
            'code' => 'PQR',
            'slug' => 'pqr-coaching',
            'owner_name' => 'PQR Owner',
            'owner_email' => 'owner@pqr.test',
            'password' => 'old-password-123',
        ]);

        $response = $this->actingAs($this->platformAdmin())
            ->post("/platform/coachings/{$result['tenant']->id}/reset-owner-password");

        $newPassword = session('credentials')['password'];
        $response->assertRedirect();

        $this->asGuest();
        $this->post('/login', ['email' => 'owner@pqr.test', 'password' => 'old-password-123'])
            ->assertSessionHasErrors('email');

        $this->post('/login', ['email' => 'owner@pqr.test', 'password' => $newPassword])
            ->assertRedirect('/app/dashboard');
    }

    public function test_coaching_users_cannot_reach_the_platform_console(): void
    {
        $result = app(TenantProvisioner::class)->provision([
            'name' => 'LMN Coaching',
            'code' => 'LMN',
            'slug' => 'lmn-coaching',
            'owner_name' => 'LMN Owner',
            'owner_email' => 'owner@lmn.test',
            'password' => 'secret-pass-123',
        ]);

        $this->actingAs($result['owner'])->get('/platform/coachings')->assertForbidden();
        $this->actingAs($result['owner'])->post('/platform/coachings', [])->assertForbidden();
    }

    public function test_platform_admin_landing_on_app_is_sent_to_the_console(): void
    {
        $this->actingAs($this->platformAdmin())
            ->get('/app/dashboard')
            ->assertRedirect('/platform/coachings');
    }

    public function test_platform_admin_command_creates_a_working_console_login(): void
    {
        $this->artisan('platform:admin', [
            '--name' => 'Service Provider',
            '--email' => 'provider@saas.test',
            '--password' => 'provider-pass-123',
        ])->assertSuccessful();

        $this->post('/login', ['email' => 'provider@saas.test', 'password' => 'provider-pass-123'])
            ->assertRedirect('/platform/coachings');

        $this->assertDatabaseHas('users', ['email' => 'provider@saas.test', 'is_platform_admin' => true]);
    }

    public function test_academic_session_must_be_a_year_label(): void
    {
        $this->actingAs($this->platformAdmin())
            ->post('/platform/coachings', [
                'name' => 'Bad Session Coaching',
                'code' => 'BAD',
                'slug' => 'bad-session',
                'owner_name' => 'Bad Owner',
                'owner_email' => 'owner@bad.test',
                'session' => 'next year',
            ])
            ->assertSessionHasErrors('session');

        $this->assertDatabaseMissing('tenants', ['code' => 'BAD']);
    }

    public function test_duplicate_code_or_email_is_rejected(): void
    {
        app(TenantProvisioner::class)->provision([
            'name' => 'First Coaching',
            'code' => 'FST',
            'slug' => 'first-coaching',
            'owner_name' => 'First Owner',
            'owner_email' => 'owner@first.test',
            'password' => 'secret-pass-123',
        ]);

        $this->actingAs($this->platformAdmin())
            ->post('/platform/coachings', [
                'name' => 'Second Coaching',
                'code' => 'FST',
                'slug' => 'first-coaching',
                'owner_name' => 'Second Owner',
                'owner_email' => 'owner@first.test',
            ])
            ->assertSessionHasErrors(['code', 'slug', 'owner_email']);

        $this->assertSame(1, Tenant::query()->count());
    }
}
