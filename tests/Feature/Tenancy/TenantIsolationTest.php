<?php

namespace Tests\Feature\Tenancy;

use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_scope_hides_other_tenant_students(): void
    {
        $a = Tenant::query()->create(['name' => 'A', 'slug' => 'a', 'code' => 'AAA', 'status' => 'active']);
        $b = Tenant::query()->create(['name' => 'B', 'slug' => 'b', 'code' => 'BBB', 'status' => 'active']);

        TenantContext::set($a);
        Student::query()->create([
            'tenant_id' => $a->id,
            'admission_no' => 'A1',
            'first_name' => 'Alice',
            'status' => 'active',
        ]);

        TenantContext::set($b);
        Student::query()->create([
            'tenant_id' => $b->id,
            'admission_no' => 'B1',
            'first_name' => 'Bob',
            'status' => 'active',
        ]);

        TenantContext::set($a);
        $this->assertSame(1, Student::query()->count());
        $this->assertSame('Alice', Student::query()->first()->first_name);

        TenantContext::clear();
    }

    public function test_owner_cannot_access_app_without_tenant(): void
    {
        $user = User::factory()->create([
            'tenant_id' => null,
            'is_platform_admin' => false,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertForbidden();
    }
}
