<?php

namespace Database\Seeders;

use App\Domain\Academics\AcademicSessionResolver;
use App\Domain\Academics\BatchScheduleFormatter;
use App\Domain\Platform\TenantProvisioner;
use App\Models\Batch;
use App\Models\Category;
use App\Models\Course;
use App\Models\Enrolment;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Demo coaching: school English tuition for Class X–XII.
 * One batch per student.
 */
class EnglishTuitionDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (Tenant::query()->where('slug', 'sharma-english')->exists()) {
            $this->command?->warn('Tenant sharma-english already exists — skipping create, refreshing demo data.');
            $tenant = Tenant::query()->where('slug', 'sharma-english')->firstOrFail();
            $owner = $tenant->users()->where('role_label', 'owner')->first();
            $password = '(unchanged — use existing login)';
        } else {
            $result = app(TenantProvisioner::class)->provision([
                'name' => 'Sharma English Tuition',
                'code' => 'SET',
                'slug' => 'sharma-english',
                'owner_name' => 'Ramesh Sharma',
                'owner_email' => 'owner@sharma-english.test',
                'owner_phone' => '9876501122',
                'password' => 'english-pass-123',
                'branch' => 'Civil Lines Campus',
                'session' => '2026-27',
            ]);

            $tenant = $result['tenant'];
            $owner = $result['owner'];
            $password = $result['password'];
        }

        TenantContext::set($tenant);

        $tenant->settings = array_merge($tenant->settings ?? [], [
            'single_batch_mode' => true,
            'landing_headline' => 'English that builds board confidence.',
            'landing_subheadline' => 'Focused English tuition for Class X, XI and XII — enquire once and join the batch for your class.',
        ]);
        $tenant->phone = $tenant->phone ?: '9876501122';
        $tenant->address = $tenant->address ?: 'Civil Lines, near City Library';
        $tenant->primary_color = '#0c4a6e';
        $tenant->save();

        $category = Category::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'School'],
            ['slug' => 'school', 'description' => 'School academic classes', 'is_active' => true],
        );

        $course = Course::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'English'],
            [
                'category_id' => $category->id,
                'code' => 'ENG',
                'description' => 'English for Class X–XII',
                'is_active' => true,
            ],
        );

        $subject = Subject::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'English'],
            ['code' => 'ENG'],
        );

        $branchId = $tenant->branches()->value('id');
        $sessionId = app(AcademicSessionResolver::class)->current($tenant->id)->id;
        $schedule = app(BatchScheduleFormatter::class);

        $batchDefs = [
            [
                'name' => 'Class X English',
                'class_level' => 'X',
                'weekdays' => [1, 3, 5],
                'starts_at' => '16:00',
                'ends_at' => '17:00',
                'shift' => 'afternoon',
                'default_fee' => 1200,
                'capacity' => 25,
            ],
            [
                'name' => 'Class XI English',
                'class_level' => 'XI',
                'weekdays' => [2, 4, 6],
                'starts_at' => '16:00',
                'ends_at' => '17:15',
                'shift' => 'afternoon',
                'default_fee' => 1400,
                'capacity' => 25,
            ],
            [
                'name' => 'Class XII English',
                'class_level' => 'XII',
                'weekdays' => [1, 2, 3, 4, 5],
                'starts_at' => '17:30',
                'ends_at' => '19:00',
                'shift' => 'evening',
                'default_fee' => 1600,
                'capacity' => 30,
            ],
        ];

        $batches = [];
        foreach ($batchDefs as $def) {
            $timing = $schedule->format($def['weekdays'], $def['starts_at'], $def['ends_at'], $def['shift']);

            $batch = Batch::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'name' => $def['name'],
                    'academic_session_id' => $sessionId,
                ],
                [
                    'branch_id' => $branchId,
                    'course_id' => $course->id,
                    'weekdays' => $def['weekdays'],
                    'starts_at' => $def['starts_at'],
                    'ends_at' => $def['ends_at'],
                    'shift' => $def['shift'],
                    'timing' => $timing,
                    'default_fee' => $def['default_fee'],
                    'capacity' => $def['capacity'],
                    'is_active' => true,
                ],
            );

            $batch->subjects()->syncWithoutDetaching([$subject->id]);
            $batches[$def['class_level']] = $batch;
        }

        $students = [
            ['first_name' => 'Ananya', 'last_name' => 'Verma', 'class_level' => 'X', 'school_name' => 'KV No.1', 'phone' => '9876502001', 'guardian' => 'Suresh Verma', 'gphone' => '9876502002'],
            ['first_name' => 'Kabir', 'last_name' => 'Singh', 'class_level' => 'X', 'school_name' => 'St. Mary\'s', 'phone' => '9876502003', 'guardian' => 'Meena Singh', 'gphone' => '9876502004'],
            ['first_name' => 'Isha', 'last_name' => 'Gupta', 'class_level' => 'XI', 'school_name' => 'DPS', 'phone' => '9876502005', 'guardian' => 'Rajesh Gupta', 'gphone' => '9876502006'],
            ['first_name' => 'Rohan', 'last_name' => 'Mehta', 'class_level' => 'XI', 'school_name' => 'KV No.2', 'phone' => '9876502007', 'guardian' => 'Pooja Mehta', 'gphone' => '9876502008'],
            ['first_name' => 'Neha', 'last_name' => 'Yadav', 'class_level' => 'XII', 'school_name' => 'Government Inter College', 'phone' => '9876502009', 'guardian' => 'Amit Yadav', 'gphone' => '9876502010'],
            ['first_name' => 'Arjun', 'last_name' => 'Patel', 'class_level' => 'XII', 'school_name' => 'St. Xavier\'s', 'phone' => '9876502011', 'guardian' => 'Nisha Patel', 'gphone' => '9876502012'],
        ];

        $year = now()->year;
        $seq = 1;

        foreach ($students as $row) {
            $admission = sprintf('%d-E%03d', $year, $seq++);

            $student = Student::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'admission_no' => $admission,
                ],
                [
                    'branch_id' => $branchId,
                    'first_name' => $row['first_name'],
                    'last_name' => $row['last_name'],
                    'class_level' => $row['class_level'],
                    'school_name' => $row['school_name'],
                    'phone' => $row['phone'],
                    'status' => 'active',
                    'joined_on' => now()->toDateString(),
                    'source' => 'Demo seed',
                ],
            );

            $guardian = Guardian::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'phone' => $row['gphone'],
                ],
                [
                    'name' => $row['guardian'],
                    'relation' => 'parent',
                    'whatsapp_opt_in' => true,
                    'sms_opt_in' => true,
                    'consent_at' => now(),
                ],
            );

            $student->guardians()->syncWithoutDetaching([
                $guardian->id => ['is_primary' => true],
            ]);

            $batch = $batches[$row['class_level']];

            Enrolment::query()->firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'student_id' => $student->id,
                    'batch_id' => $batch->id,
                    'status' => 'active',
                ],
                [
                    'enrolled_on' => now()->toDateString(),
                ],
            );
        }

        TenantContext::clear();

        $this->command?->info('Sharma English Tuition ready.');
        $this->command?->table(
            ['Item', 'Value'],
            [
                ['Login email', $owner?->email ?? 'owner@sharma-english.test'],
                ['Password', $password],
                ['Public page', url('/c/sharma-english')],
                ['Rule', 'One batch per student'],
                ['Batches', 'Class X / XI / XII English'],
                ['Students', '6 demo students enrolled'],
            ],
        );
    }
}
