<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Course;
use App\Models\Enrolment;
use App\Models\FeePlan;
use App\Models\Guardian;
use App\Models\NotificationTemplate;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Tenant;
use App\Models\TenantPaymentGateway;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'students.manage', 'attendance.manage', 'fees.manage', 'announcements.manage',
            'notes.manage', 'enquiries.manage', 'staff.manage', 'reports.view', 'settings.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        foreach (['platform_admin', 'owner', 'branch_manager', 'teacher', 'accountant', 'receptionist', 'parent', 'student'] as $role) {
            Role::findOrCreate($role);
        }

        Role::findByName('owner')->syncPermissions($permissions);
        Role::findByName('branch_manager')->syncPermissions($permissions);
        Role::findByName('teacher')->syncPermissions(['students.manage', 'attendance.manage', 'notes.manage', 'announcements.manage']);
        Role::findByName('accountant')->syncPermissions(['fees.manage', 'reports.view']);
        Role::findByName('receptionist')->syncPermissions(['students.manage', 'enquiries.manage', 'fees.manage']);

        $tenant = Tenant::query()->create([
            'name' => 'Demo Competition Coaching',
            'slug' => 'demo-coaching',
            'code' => 'DEMO',
            'email' => 'owner@demo-coaching.test',
            'phone' => '9999999999',
            'primary_color' => '#0c4a6e',
            'address' => 'Sector 18, Noida',
            'status' => 'active',
            'settings' => [
                'notify_absent' => true,
                'notify_present' => false,
            ],
        ]);

        TenantContext::set($tenant);

        $branch = Branch::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Campus',
            'code' => 'MAIN',
            'phone' => '9999999999',
            'address' => 'Sector 18, Noida',
            'is_active' => true,
        ]);

        AcademicSession::query()->create([
            'tenant_id' => $tenant->id,
            'name' => '2026-27',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_current' => true,
        ]);

        $owner = User::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'name' => 'Demo Owner',
            'email' => 'owner@demo-coaching.test',
            'phone' => '9999999999',
            'password' => Hash::make('password'),
            'role_label' => 'owner',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $owner->assignRole('owner');

        $teacher = User::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'name' => 'Demo Teacher',
            'email' => 'teacher@demo-coaching.test',
            'phone' => '9888888888',
            'password' => Hash::make('password'),
            'role_label' => 'teacher',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $teacher->assignRole('teacher');

        User::query()->create([
            'name' => 'Platform Admin',
            'email' => 'admin@coaching-saas.test',
            'password' => Hash::make('password'),
            'is_platform_admin' => true,
            'role_label' => 'platform_admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ])->assignRole('platform_admin');

        $category = Category::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Competition',
            'slug' => 'competition',
            'description' => 'Entrance exam preparation',
            'is_active' => true,
        ]);

        $jee = Course::query()->create([
            'tenant_id' => $tenant->id,
            'category_id' => $category->id,
            'name' => 'JEE Main',
            'code' => 'JEE',
            'is_active' => true,
        ]);

        $neet = Course::query()->create([
            'tenant_id' => $tenant->id,
            'category_id' => $category->id,
            'name' => 'NEET',
            'code' => 'NEET',
            'is_active' => true,
        ]);

        $physics = Subject::query()->create(['tenant_id' => $tenant->id, 'name' => 'Physics', 'code' => 'PHY']);
        $chemistry = Subject::query()->create(['tenant_id' => $tenant->id, 'name' => 'Chemistry', 'code' => 'CHE']);
        $maths = Subject::query()->create(['tenant_id' => $tenant->id, 'name' => 'Mathematics', 'code' => 'MATH']);

        $batch = Batch::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'course_id' => $jee->id,
            'name' => 'JEE Morning 2026',
            'code' => 'JEE-M',
            'timing' => '7:00 AM - 11:00 AM',
            'capacity' => 40,
            'default_fee' => 8000,
            'is_active' => true,
        ]);

        $batch->subjects()->attach([
            $physics->id => ['teacher_id' => $teacher->id],
            $chemistry->id => ['teacher_id' => $teacher->id],
            $maths->id => ['teacher_id' => $teacher->id],
        ]);

        Batch::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'course_id' => $neet->id,
            'name' => 'NEET Evening 2026',
            'code' => 'NEET-E',
            'timing' => '4:00 PM - 8:00 PM',
            'capacity' => 35,
            'default_fee' => 7500,
            'is_active' => true,
        ]);

        FeePlan::query()->create([
            'tenant_id' => $tenant->id,
            'batch_id' => $batch->id,
            'name' => 'Monthly Fee',
            'frequency' => 'monthly',
            'amount' => 8000,
            'installments' => 12,
            'is_active' => true,
        ]);

        TenantPaymentGateway::query()->create([
            'tenant_id' => $tenant->id,
            'provider' => 'razorpay',
            'mode' => 'test',
            'key_id' => null,
            'key_secret' => null,
            'onboarding_status' => 'pending',
            'enabled_methods' => ['upi', 'card', 'netbanking'],
            'is_active' => true,
        ]);

        foreach ([
            ['key' => 'attendance.absent', 'channel' => 'whatsapp', 'body' => '{{student_name}} was marked ABSENT for {{batch_name}} on {{date}} ({{subject}}{{topic_part}}).'],
            ['key' => 'attendance.absent', 'channel' => 'email', 'body' => '{{student_name}} was marked ABSENT for {{batch_name}} on {{date}} ({{subject}}{{topic_part}}).'],
            ['key' => 'attendance.absent', 'channel' => 'sms', 'body' => '{{student_name}} ABSENT {{batch_name}} {{date}}'],
            ['key' => 'attendance.present', 'channel' => 'whatsapp', 'body' => '{{student_name}} was marked PRESENT for {{batch_name}} on {{date}} ({{subject}}{{topic_part}}).'],
            ['key' => 'attendance.present', 'channel' => 'sms', 'body' => '{{student_name}} PRESENT {{batch_name}} {{date}}'],
            ['key' => 'attendance.present', 'channel' => 'email', 'body' => '{{student_name}} was marked PRESENT for {{batch_name}} on {{date}} ({{subject}}{{topic_part}}).'],
            ['key' => 'announcement', 'channel' => 'whatsapp', 'body' => '{{title}}: {{body}}'],
            ['key' => 'announcement', 'channel' => 'sms', 'body' => '{{title}}: {{body}}'],
            ['key' => 'announcement', 'channel' => 'email', 'body' => '{{title}}: {{body}}'],
        ] as $template) {
            NotificationTemplate::query()->create([
                'tenant_id' => $tenant->id,
                ...$template,
                'locale' => 'en',
                'is_active' => true,
            ]);
        }

        $student = Student::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'admission_no' => 'ADM-001',
            'first_name' => 'Aarav',
            'last_name' => 'Sharma',
            'phone' => '9777777777',
            'status' => 'active',
            'joined_on' => now()->toDateString(),
        ]);

        $guardian = Guardian::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Ravi Sharma',
            'relation' => 'father',
            'phone' => '9766666666',
            'email' => 'parent@demo-coaching.test',
            'whatsapp_opt_in' => true,
            'sms_opt_in' => true,
            'consent_at' => now(),
        ]);

        $student->guardians()->attach($guardian->id, ['is_primary' => true]);

        Enrolment::query()->create([
            'tenant_id' => $tenant->id,
            'student_id' => $student->id,
            'batch_id' => $batch->id,
            'enrolled_on' => now()->toDateString(),
            'status' => 'active',
        ]);

        $parentUser = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Ravi Sharma',
            'email' => 'parent@demo-coaching.test',
            'phone' => '9766666666',
            'password' => Hash::make('password'),
            'role_label' => 'parent',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $parentUser->assignRole('parent');
        $guardian->update(['user_id' => $parentUser->id]);

        TenantContext::clear();
    }
}
