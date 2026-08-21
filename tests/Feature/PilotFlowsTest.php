<?php

namespace Tests\Feature;

use App\Domain\Attendance\AttendanceService;
use App\Domain\Billing\BillingService;
use App\Domain\Billing\TenantGatewayResolver;
use App\Domain\CRM\EnquiryService;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\ClassSession;
use App\Models\Enquiry;
use App\Models\Enrolment;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\NotificationOutbox;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\TenantPaymentGateway;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PilotFlowsTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->tenant = Tenant::query()->where('slug', 'demo-coaching')->firstOrFail();
        $this->owner = User::query()->where('email', 'owner@demo-coaching.test')->firstOrFail();
        TenantContext::set($this->tenant);
        $this->actingAs($this->owner);
    }

    public function test_attendance_absent_enqueues_parent_notification(): void
    {
        $student = Student::query()->where('admission_no', 'ADM-001')->firstOrFail();
        $batch = Batch::query()->where('code', 'JEE-M')->firstOrFail();

        $session = ClassSession::query()->create([
            'tenant_id' => $this->tenant->id,
            'batch_id' => $batch->id,
            'session_date' => now()->toDateString(),
            'teacher_id' => $this->owner->id,
            'status' => 'scheduled',
        ]);

        app(AttendanceService::class)->markBulk($session, [
            ['student_id' => $student->id, 'status' => 'absent'],
        ], true, true, false);

        $this->assertDatabaseHas('notification_outbox', [
            'student_id' => $student->id,
            'event_type' => 'attendance.absent',
            'status' => 'pending',
        ]);
    }

    public function test_manual_payment_issues_immutable_receipt(): void
    {
        $student = Student::query()->where('admission_no', 'ADM-001')->firstOrFail();
        $billing = app(BillingService::class);

        $invoice = $billing->createInvoice($student, [
            'total' => 5000,
            'discount_total' => 500,
            'subtotal' => 5500,
        ]);

        $result = $billing->recordManualPayment($student, [
            'amount' => 2000,
            'mode' => 'upi',
            'invoice_id' => $invoice->id,
            'reference' => 'UPI123',
        ]);

        $this->assertNotNull($result['receipt']->receipt_no);
        $this->assertStringStartsWith('DEMO/', $result['receipt']->receipt_no);
        $this->assertSame('partial', $invoice->fresh()->status);
    }

    public function test_gateway_resolver_rejects_cross_tenant_invoice(): void
    {
        $other = Tenant::query()->create(['name' => 'Other', 'slug' => 'other', 'code' => 'OTH', 'status' => 'active']);
        $gateway = TenantPaymentGateway::query()->create([
            'tenant_id' => $other->id,
            'provider' => 'razorpay',
            'mode' => 'test',
            'is_active' => true,
            'onboarding_status' => 'connected',
        ]);

        $student = Student::query()->where('admission_no', 'ADM-001')->firstOrFail();
        $invoice = app(BillingService::class)->createInvoice($student, ['total' => 1000]);

        $this->expectException(\RuntimeException::class);
        app(TenantGatewayResolver::class)->assertMatchesInvoice($gateway, $invoice);
    }

    public function test_enquiry_converts_to_admission(): void
    {
        $enquiry = Enquiry::query()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Neha Verma',
            'phone' => '9123456780',
            'status' => 'interested',
            'source' => 'landing_page',
        ]);

        $batch = Batch::query()->first();
        $student = app(EnquiryService::class)->convertToAdmission($enquiry, [
            'admission_no' => 'ADM-100',
            'batch_id' => $batch->id,
            'guardian_name' => 'Parent Verma',
            'guardian_phone' => '9123456781',
            'whatsapp_opt_in' => true,
        ]);

        $this->assertSame('admitted', $enquiry->fresh()->status);
        $this->assertSame('ADM-100', $student->admission_no);
        $this->assertTrue(Enrolment::query()->where('student_id', $student->id)->exists());
    }
}
