<?php

namespace App\Domain\Platform;

use App\Models\AcademicSession;
use App\Models\Branch;
use App\Models\NotificationTemplate;
use App\Models\Tenant;
use App\Models\TenantPaymentGateway;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class TenantProvisioner
{
    /**
     * Create a coaching organization together with its owner login.
     *
     * @param  array{name: string, code: string, slug: string, owner_name: string, owner_email: string, owner_phone?: ?string, password?: ?string, branch?: ?string, session?: ?string}  $data
     * @return array{tenant: Tenant, owner: User, password: string, generated_password: bool}
     */
    public function provision(array $data): array
    {
        $password = $data['password'] ?? null;
        $generated = $password === null || $password === '';

        if ($generated) {
            $password = Str::password(12, symbols: false);
        }

        $ownerEmail = strtolower($data['owner_email']);
        $ownerPhone = $data['owner_phone'] ?? null;
        $sessionLabel = $this->normalizeSessionLabel($data['session'] ?? null);

        [$tenant, $owner] = DB::transaction(function () use ($data, $ownerEmail, $ownerPhone, $password, $sessionLabel) {
            $previousTenantId = TenantContext::id();

            $tenant = Tenant::query()->create([
                'name' => $data['name'],
                'slug' => Str::slug($data['slug']),
                'code' => strtoupper($data['code']),
                'email' => $ownerEmail,
                'phone' => $ownerPhone ?: null,
                'primary_color' => '#0c4a6e',
                'timezone' => 'Asia/Kolkata',
                'locale' => 'en',
                'status' => 'active',
                'settings' => [
                    'notify_absent' => true,
                    'notify_present' => false,
                ],
            ]);

            TenantContext::setId($tenant->id);

            $branch = Branch::query()->create([
                'tenant_id' => $tenant->id,
                'name' => ($data['branch'] ?? null) ?: 'Main Campus',
                'code' => 'MAIN',
                'phone' => $ownerPhone ?: null,
                'is_active' => true,
            ]);

            [$startsOn, $endsOn] = $this->sessionDates($sessionLabel);

            AcademicSession::query()->create([
                'tenant_id' => $tenant->id,
                'name' => $sessionLabel,
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
                'is_current' => true,
            ]);

            $owner = User::query()->create([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'name' => $data['owner_name'],
                'email' => $ownerEmail,
                'phone' => $ownerPhone ?: null,
                'password' => Hash::make($password),
                'role_label' => 'owner',
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            $this->ensureRolesExist();
            $owner->assignRole('owner');

            TenantPaymentGateway::query()->create([
                'tenant_id' => $tenant->id,
                'provider' => 'razorpay',
                'mode' => 'test',
                'onboarding_status' => 'pending',
                'enabled_methods' => ['upi', 'card', 'netbanking'],
                'is_active' => false,
            ]);

            $this->seedTemplates($tenant->id);

            $previousTenantId ? TenantContext::setId($previousTenantId) : TenantContext::clear();

            return [$tenant, $owner];
        });

        return [
            'tenant' => $tenant,
            'owner' => $owner,
            'password' => $password,
            'generated_password' => $generated,
        ];
    }

    public function ensureRolesExist(): void
    {
        $permissions = [
            'students.manage', 'attendance.manage', 'fees.manage', 'announcements.manage',
            'notes.manage', 'enquiries.manage', 'staff.manage', 'reports.view', 'settings.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $roles = [
            'platform_admin' => [],
            'owner' => $permissions,
            'branch_manager' => $permissions,
            'teacher' => ['students.manage', 'attendance.manage', 'notes.manage', 'announcements.manage'],
            'accountant' => ['fees.manage', 'reports.view'],
            'receptionist' => ['students.manage', 'enquiries.manage', 'fees.manage'],
            'parent' => [],
            'student' => [],
        ];

        foreach ($roles as $role => $rolePermissions) {
            $model = Role::findOrCreate($role);

            if ($rolePermissions !== []) {
                $model->syncPermissions($rolePermissions);
            }
        }
    }

    /**
     * Session labels drive April-March date ranges, so anything unparsable falls back to the running year.
     */
    public function normalizeSessionLabel(?string $label): string
    {
        return preg_match('/^\d{4}-\d{2}$/', (string) $label)
            ? $label
            : $this->defaultSessionLabel();
    }

    public function defaultSessionLabel(): string
    {
        $year = (int) now()->format('Y');
        $startYear = (int) now()->format('n') >= 4 ? $year : $year - 1;

        return $startYear.'-'.substr((string) ($startYear + 1), -2);
    }

    protected function seedTemplates(int $tenantId): void
    {
        $templates = [
            ['key' => 'attendance.absent', 'channel' => 'whatsapp', 'body' => '{{student_name}} was marked ABSENT for {{batch_name}} on {{date}} ({{subject}}{{topic_part}}).'],
            ['key' => 'attendance.absent', 'channel' => 'email', 'body' => '{{student_name}} was marked ABSENT for {{batch_name}} on {{date}} ({{subject}}{{topic_part}}).'],
            ['key' => 'attendance.absent', 'channel' => 'sms', 'body' => '{{student_name}} ABSENT {{batch_name}} {{date}}'],
            ['key' => 'attendance.present', 'channel' => 'whatsapp', 'body' => '{{student_name}} was marked PRESENT for {{batch_name}} on {{date}} ({{subject}}{{topic_part}}).'],
            ['key' => 'attendance.present', 'channel' => 'sms', 'body' => '{{student_name}} PRESENT {{batch_name}} {{date}}'],
            ['key' => 'attendance.present', 'channel' => 'email', 'body' => '{{student_name}} was marked PRESENT for {{batch_name}} on {{date}} ({{subject}}{{topic_part}}).'],
            ['key' => 'announcement', 'channel' => 'whatsapp', 'body' => '{{title}}: {{body}}'],
            ['key' => 'announcement', 'channel' => 'sms', 'body' => '{{title}}: {{body}}'],
            ['key' => 'announcement', 'channel' => 'email', 'body' => '{{title}}: {{body}}'],
        ];

        foreach ($templates as $template) {
            NotificationTemplate::query()->create([
                'tenant_id' => $tenantId,
                ...$template,
                'locale' => 'en',
                'is_active' => true,
            ]);
        }
    }

    protected function sessionDates(string $label): array
    {
        $startYear = (int) Str::before($label, '-') ?: (int) now()->format('Y');

        return [$startYear.'-04-01', ($startYear + 1).'-03-31'];
    }
}
