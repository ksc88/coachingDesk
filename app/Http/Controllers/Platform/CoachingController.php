<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Platform\TenantProvisioner;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CoachingController extends Controller
{
    public function index(): Response
    {
        $tenants = Tenant::query()
            ->withCount(['students', 'users', 'batches'])
            ->with(['users' => fn ($query) => $query->where('role_label', 'owner')->orderBy('id')->limit(1)])
            ->orderByDesc('id')
            ->get()
            ->map(fn (Tenant $tenant) => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'code' => $tenant->code,
                'slug' => $tenant->slug,
                'status' => $tenant->status,
                'phone' => $tenant->phone,
                'students_count' => $tenant->students_count,
                'users_count' => $tenant->users_count,
                'batches_count' => $tenant->batches_count,
                'created_at' => $tenant->created_at?->toDateString(),
                'owner' => $tenant->users->first() ? [
                    'id' => $tenant->users->first()->id,
                    'name' => $tenant->users->first()->name,
                    'email' => $tenant->users->first()->email,
                ] : null,
                'landing_url' => url('/c/'.$tenant->slug),
            ]);

        return Inertia::render('Platform/Coachings/Index', [
            'coachings' => $tenants,
            'stats' => [
                'total' => $tenants->count(),
                'active' => $tenants->where('status', 'active')->count(),
                'suspended' => $tenants->where('status', 'suspended')->count(),
                'students' => $tenants->sum('students_count'),
            ],
            'credentials' => session('credentials'),
            'sessionLabel' => app(TenantProvisioner::class)->defaultSessionLabel(),
        ]);
    }

    public function store(Request $request, TenantProvisioner $provisioner): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'min:2', 'max:16', 'alpha_num', Rule::unique('tenants', 'code')],
            'slug' => ['required', 'string', 'max:255', Rule::unique('tenants', 'slug')],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'owner_phone' => ['nullable', 'string', 'max:20'],
            'password' => ['nullable', 'string', 'min:8'],
            'branch' => ['nullable', 'string', 'max:255'],
            'session' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ], [
            'session.regex' => 'Academic session must look like 2026-27.',
        ]);

        $result = $provisioner->provision($data);

        $this->audit('platform.coaching.created', $result['tenant']->id, [
            'name' => $result['tenant']->name,
            'code' => $result['tenant']->code,
        ]);

        return back()->with('credentials', [
            'coaching' => $result['tenant']->name,
            'email' => $result['owner']->email,
            'password' => $result['generated_password'] ? $result['password'] : null,
            'login_url' => url('/login'),
            'landing_url' => url('/c/'.$result['tenant']->slug),
        ])->with('success', $result['tenant']->name.' onboarded.');
    }

    public function updateStatus(Request $request, Tenant $coaching): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:active,suspended'],
        ]);

        $previous = $coaching->status;
        $coaching->update(['status' => $data['status']]);

        $this->audit('platform.coaching.status_changed', $coaching->id, [
            'from' => $previous,
            'to' => $data['status'],
        ]);

        return back()->with(
            'success',
            $data['status'] === 'active'
                ? $coaching->name.' activated.'
                : $coaching->name.' suspended. Their staff can no longer sign in.'
        );
    }

    public function resetOwnerPassword(Tenant $coaching): RedirectResponse
    {
        $owner = User::query()
            ->where('tenant_id', $coaching->id)
            ->where('role_label', 'owner')
            ->orderBy('id')
            ->first();

        if (! $owner) {
            return back()->with('error', 'No owner account found for '.$coaching->name.'.');
        }

        $password = Str::password(12, symbols: false);
        $owner->forceFill(['password' => $password])->save();

        $this->audit('platform.owner.password_reset', $coaching->id, ['user_id' => $owner->id]);

        return back()->with('credentials', [
            'coaching' => $coaching->name,
            'email' => $owner->email,
            'password' => $password,
            'login_url' => url('/login'),
            'landing_url' => url('/c/'.$coaching->slug),
        ])->with('success', 'New password generated for '.$owner->email.'.');
    }

    protected function audit(string $action, int $tenantId, array $values): void
    {
        AuditLog::query()->create([
            'tenant_id' => $tenantId,
            'user_id' => auth()->id(),
            'action' => $action,
            'auditable_type' => Tenant::class,
            'auditable_id' => $tenantId,
            'new_values' => $values,
            'ip_address' => request()->ip(),
        ]);
    }
}
