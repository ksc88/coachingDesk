<?php

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\Course;
use App\Models\Enquiry;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Sample enquiry CRM data for Indresh English Classes (manual-flow testing).
 */
class IndreshEnquiryDemoSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'indresh-english-classes')->first();

        if (! $tenant) {
            $this->command?->error('Tenant indresh-english-classes not found. Create it from Provider console first.');

            return;
        }

        TenantContext::set($tenant);

        $courseId = Course::query()->where('name', 'English')->value('id');
        $batches = Batch::query()->orderBy('name')->get()->keyBy('name');

        $rows = [
            [
                'name' => 'Pawan Singh',
                'phone' => '159357456223',
                'email' => 'pawan@example.test',
                'batch' => 'Class X English',
                'status' => 'new',
                'source' => 'landing_page',
                'notes' => 'Wants Class X evening confirmation',
                'whatsapp_opt_in' => true,
                'sms_opt_in' => true,
                'next_follow_up_at' => now()->addDay(),
            ],
            [
                'name' => 'Riya Sharma',
                'phone' => '9876503101',
                'email' => 'riya.sharma@example.test',
                'batch' => 'Class XI English',
                'status' => 'contacted',
                'source' => 'landing_page',
                'notes' => 'Called mother — interested in Tue/Thu batch',
                'whatsapp_opt_in' => true,
                'sms_opt_in' => true,
                'next_follow_up_at' => now()->addDays(2),
            ],
            [
                'name' => 'Aman Verma',
                'phone' => '9876503102',
                'email' => null,
                'batch' => 'Class XII English',
                'status' => 'interested',
                'source' => 'walk-in',
                'notes' => 'Visited campus with father; board year 2027',
                'whatsapp_opt_in' => true,
                'sms_opt_in' => false,
                'next_follow_up_at' => now()->addDays(3),
            ],
            [
                'name' => 'Sneha Gupta',
                'phone' => '9876503103',
                'email' => 'sneha@example.test',
                'batch' => 'Class X English',
                'status' => 'demo_scheduled',
                'source' => 'referral',
                'notes' => 'Demo class Saturday 4 PM',
                'whatsapp_opt_in' => false,
                'sms_opt_in' => true,
                'next_follow_up_at' => now()->addDays(1)->setTime(15, 0),
            ],
            [
                'name' => 'Vikram Yadav',
                'phone' => '9876503104',
                'email' => 'vikram@example.test',
                'batch' => 'Class XI English',
                'status' => 'new',
                'source' => 'landing_page',
                'notes' => 'Asked fee and seating capacity',
                'whatsapp_opt_in' => true,
                'sms_opt_in' => true,
                'next_follow_up_at' => now()->addHours(6),
            ],
            [
                'name' => 'Meera Patel',
                'phone' => '9876503105',
                'email' => null,
                'batch' => 'Class XII English',
                'status' => 'lost',
                'source' => 'phone',
                'notes' => 'Joined another coaching — keep for remarketing next session',
                'whatsapp_opt_in' => false,
                'sms_opt_in' => false,
                'next_follow_up_at' => null,
            ],
            [
                'name' => 'Kabir Khan',
                'phone' => '9876503106',
                'email' => 'kabir@example.test',
                'batch' => 'Class X English',
                'status' => 'interested',
                'source' => 'landing_page',
                'notes' => 'Prefers Mon/Wed/Fri; guardian WhatsApp same number',
                'whatsapp_opt_in' => true,
                'sms_opt_in' => true,
                'next_follow_up_at' => now()->addDays(1),
            ],
        ];

        $created = 0;
        $updated = 0;

        foreach ($rows as $row) {
            $batch = $batches->get($row['batch']);

            $enquiry = Enquiry::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'phone' => $row['phone'],
                ],
                [
                    'name' => $row['name'],
                    'email' => $row['email'],
                    'course_id' => $courseId,
                    'batch_id' => $batch?->id,
                    'source' => $row['source'],
                    'status' => $row['status'],
                    'notes' => $row['notes'],
                    'whatsapp_opt_in' => $row['whatsapp_opt_in'],
                    'sms_opt_in' => $row['sms_opt_in'],
                    'next_follow_up_at' => $row['next_follow_up_at'],
                ],
            );

            $enquiry->wasRecentlyCreated ? $created++ : $updated++;
        }

        TenantContext::clear();

        $this->command?->info("Indresh enquiry dataset ready ({$created} created, {$updated} updated).");
        $this->command?->table(
            ['Name', 'Batch', 'Status', 'WhatsApp'],
            collect($rows)->map(fn ($r) => [
                $r['name'],
                $r['batch'],
                $r['status'],
                $r['whatsapp_opt_in'] ? 'Yes' : 'No',
            ])->all(),
        );
        $this->command?->comment('Open /app/enquiries as Indresh owner to test Follow up + Convert.');
    }
}
