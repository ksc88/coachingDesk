<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_create_command_provisions_a_working_owner_login(): void
    {
        $this->artisan('tenant:create', [
            '--name' => 'XYZ Coaching Classes',
            '--code' => 'XYZ',
            '--slug' => 'xyz-coaching',
            '--owner-name' => 'XYZ Owner',
            '--owner-email' => 'owner@xyz.test',
            '--password' => 'secret-pass-123',
        ])->assertSuccessful();

        $this->post('/login', [
            'email' => 'owner@xyz.test',
            'password' => 'secret-pass-123',
        ])->assertRedirect('/app/dashboard');

        $owner = User::query()->where('email', 'owner@xyz.test')->firstOrFail();

        $this->actingAs($owner)->get('/app/dashboard')->assertOk();
        $this->get('/c/xyz-coaching')->assertOk();
    }
}
