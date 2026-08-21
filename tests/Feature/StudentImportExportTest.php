<?php

namespace Tests\Feature;

use App\Models\NotificationOutbox;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class StudentImportExportTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $tenant = Tenant::query()->where('slug', 'demo-coaching')->firstOrFail();
        $this->owner = User::query()->where('email', 'owner@demo-coaching.test')->firstOrFail();
        TenantContext::set($tenant);
    }

    public function test_csv_import_creates_students_and_skips_duplicates(): void
    {
        $csv = implode("\n", [
            'admission_no,first_name,last_name,phone,email,batch,guardian_name,guardian_phone',
            'ADM-500,Riya,Singh,9000000001,riya@test.com,JEE Morning 2026,Sunita Singh,9000000002',
            'ADM-001,Duplicate,Student,,,,,',
            ',Missing,Admission,,,,,',
        ]);

        $response = $this->actingAs($this->owner)->post(route('students.import'), [
            'file' => UploadedFile::fake()->createWithContent('students.csv', $csv),
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('students', ['admission_no' => 'ADM-500', 'first_name' => 'Riya']);
        $this->assertSame(1, Student::query()->where('admission_no', 'ADM-001')->count());

        $imported = Student::query()->where('admission_no', 'ADM-500')->firstOrFail();
        $this->assertSame('Sunita Singh', $imported->guardians()->first()->name);
        $this->assertTrue($imported->enrolments()->exists());
    }

    public function test_export_returns_csv_with_seeded_student(): void
    {
        $response = $this->actingAs($this->owner)->get(route('students.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('ADM-001', $response->streamedContent());
    }

    public function test_outbox_dispatch_command_marks_messages_sent(): void
    {
        NotificationOutbox::query()->create([
            'tenant_id' => $this->owner->tenant_id,
            'channel' => 'sms',
            'event_type' => 'attendance.absent',
            'recipient_phone' => '9000000009',
            'body' => 'Test alert',
            'status' => 'pending',
            'scheduled_at' => now()->subMinute(),
        ]);

        $this->artisan('outbox:dispatch')->assertSuccessful();

        $this->assertDatabaseHas('notification_outbox', [
            'recipient_phone' => '9000000009',
            'status' => 'sent',
        ]);
    }
}
