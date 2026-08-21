<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\StaffAssignment;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class StaffController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Staff/Index', [
            'staff' => User::query()
                ->where('tenant_id', auth()->user()->tenant_id)
                ->where('is_platform_admin', false)
                ->orderBy('name')
                ->get(),
            'assignments' => StaffAssignment::query()->with(['user', 'batch', 'subject'])->latest()->get(),
            'batches' => Batch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'subjects' => Subject::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['nullable', 'string'],
            'role_label' => ['required', 'in:owner,branch_manager,teacher,accountant,receptionist'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = User::query()->create([
            'tenant_id' => $request->user()->tenant_id,
            'branch_id' => $request->user()->branch_id,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'role_label' => $data['role_label'],
            'password' => Hash::make($data['password']),
            'is_active' => true,
            // The owner vouches for staff accounts, so they can sign in without an email round-trip.
            'email_verified_at' => now(),
        ]);

        if (method_exists($user, 'assignRole')) {
            try {
                $user->assignRole($data['role_label']);
            } catch (\Throwable) {
                // Roles may not be seeded yet in some environments.
            }
        }

        return back()->with('success', 'Staff member added.');
    }

    public function assign(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'batch_id' => ['nullable', 'exists:batches,id'],
            'subject_id' => ['nullable', 'exists:subjects,id'],
            'role' => ['required', 'string'],
        ]);

        StaffAssignment::query()->create([
            ...$data,
            'tenant_id' => $request->user()->tenant_id,
        ]);

        return back()->with('success', 'Assignment saved.');
    }
}
