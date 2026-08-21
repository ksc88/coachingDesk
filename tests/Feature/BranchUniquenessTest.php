<?php

namespace Tests\Feature;

use App\Domain\Platform\TenantProvisioner;
use App\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchUniquenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_branch_code_returns_validation_error_not_500(): void
    {
        $this->withoutVite();

        $owner = app(TenantProvisioner::class)->provision([
            'name' => 'Branch Coaching',
            'code' => 'BRN',
            'slug' => 'branch-coaching',
            'owner_name' => 'Branch Owner',
            'owner_email' => 'owner@branch.test',
            'password' => 'secret-pass-123',
        ])['owner'];

        $this->actingAs($owner)
            ->post('/app/academics/branches', ['name' => 'Supper30', 'code' => 'su-30'])
            ->assertRedirect();

        $this->actingAs($owner)
            ->post('/app/academics/branches', ['name' => 'Supper30 again', 'code' => 'su-30'])
            ->assertSessionHasErrors('code')
            ->assertStatus(302);

        $this->assertSame(2, Branch::query()->withoutGlobalScope('tenant')->where('tenant_id', $owner->tenant_id)->count());
        // Main Campus from provisioner + first Supper30
    }
}
