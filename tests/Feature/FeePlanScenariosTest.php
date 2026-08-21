<?php

namespace Tests\Feature;

use App\Domain\Billing\BillingService;
use App\Domain\Platform\TenantProvisioner;
use App\Models\Batch;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeePlanScenariosTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->owner = app(TenantProvisioner::class)->provision([
            'name' => 'Fee Coaching',
            'code' => 'FEE',
            'slug' => 'fee-coaching',
            'owner_name' => 'Fee Owner',
            'owner_email' => 'owner@fee.test',
            'password' => 'secret-pass-123',
        ])['owner'];

        $this->actingAs($this->owner)->post('/app/academics/batches', [
            'name' => 'Class XI English',
            'weekdays' => [2, 4],
            'starts_at' => '16:00',
            'ends_at' => '17:00',
            'default_fee' => 1400,
        ]);
    }

    public function test_monthly_invoice_uses_batch_and_student_amount(): void
    {
        $batch = Batch::query()->withoutGlobalScope('tenant')->where('name', 'Class XI English')->firstOrFail();

        $this->actingAs($this->owner)->post('/app/students', [
            'first_name' => 'Riya',
            'batch_id' => $batch->id,
        ]);

        $student = Student::query()->withoutGlobalScope('tenant')->where('first_name', 'Riya')->firstOrFail();

        $this->actingAs($this->owner)
            ->post('/app/fees/invoices', [
                'student_id' => $student->id,
                'plan_type' => 'monthly',
                'total' => 1400,
                'discount_total' => 0,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $invoice = Invoice::query()->withoutGlobalScope('tenant')->firstOrFail();
        $this->assertSame('1400.00', (string) $invoice->total);
        $this->assertSame($batch->id, $invoice->batch_id);
        $this->assertStringContainsString('Monthly', (string) $invoice->notes);
    }

    public function test_installments_create_multiple_invoices(): void
    {
        $batch = Batch::query()->withoutGlobalScope('tenant')->where('name', 'Class XI English')->firstOrFail();

        $this->actingAs($this->owner)->post('/app/students', [
            'first_name' => 'Kabir',
            'batch_id' => $batch->id,
        ]);

        $student = Student::query()->withoutGlobalScope('tenant')->where('first_name', 'Kabir')->firstOrFail();

        $this->actingAs($this->owner)
            ->post('/app/fees/invoices', [
                'student_id' => $student->id,
                'plan_type' => 'installments',
                'installments' => 3,
                'total' => 15000,
                'discount_total' => 0,
                'due_date' => '2026-08-01',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $invoices = Invoice::query()->withoutGlobalScope('tenant')->orderBy('id')->get();
        $this->assertCount(3, $invoices);
        $this->assertEquals(15000.0, round((float) $invoices->sum('total'), 2));
        $this->assertStringContainsString('Instalment 1/3', (string) $invoices[0]->notes);
        $this->assertSame('2026-08-01', $invoices[0]->due_date?->toDateString());
        $this->assertSame('2026-09-01', $invoices[1]->due_date?->toDateString());
    }

    public function test_student_specific_discount_on_term_invoice(): void
    {
        $batch = Batch::query()->withoutGlobalScope('tenant')->where('name', 'Class XI English')->firstOrFail();

        $this->actingAs($this->owner)->post('/app/students', [
            'first_name' => 'Neha',
            'batch_id' => $batch->id,
        ]);

        $student = Student::query()->withoutGlobalScope('tenant')->where('first_name', 'Neha')->firstOrFail();

        $this->actingAs($this->owner)
            ->post('/app/fees/invoices', [
                'student_id' => $student->id,
                'plan_type' => 'term',
                'total' => 15000,
                'discount_total' => 2000,
                'notes' => 'Sibling discount',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $invoice = Invoice::query()->withoutGlobalScope('tenant')->firstOrFail();
        $this->assertSame('13000.00', (string) $invoice->total);
        $this->assertStringContainsString('Sibling discount', (string) $invoice->notes);
    }

    public function test_gateway_can_be_saved_inactive_for_manual_only(): void
    {
        $this->actingAs($this->owner)
            ->post('/app/settings/gateway', [
                'key_id' => 'rzp_test_x',
                'key_secret' => 'secret',
                'mode' => 'test',
                'is_active' => false,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tenant_payment_gateways', [
            'tenant_id' => $this->owner->tenant_id,
            'provider' => 'razorpay',
            'is_active' => 0,
        ]);
    }

    public function test_payment_first_creates_monthly_invoice_and_receipt(): void
    {
        $batch = Batch::query()->withoutGlobalScope('tenant')->where('name', 'Class XI English')->firstOrFail();

        $this->actingAs($this->owner)->post('/app/students', [
            'first_name' => 'Pawan',
            'batch_id' => $batch->id,
            'fee_style' => 'monthly',
            'fee_amount' => 1200,
        ]);

        $student = Student::query()->withoutGlobalScope('tenant')->where('first_name', 'Pawan')->firstOrFail();

        $this->assertSame(0, Invoice::query()->withoutGlobalScope('tenant')->count());

        $this->actingAs($this->owner)
            ->post('/app/fees/payments', [
                'student_id' => $student->id,
                'batch_id' => $batch->id,
                'billing_period' => '2026-08',
                'amount' => 1200,
                'mode' => 'cash',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $invoice = Invoice::query()->withoutGlobalScope('tenant')->firstOrFail();
        $this->assertSame('1200.00', (string) $invoice->total);
        $this->assertSame('paid', $invoice->status);
        // Joined mid-month after the default due day (5th) → first bill due on join date.
        $enrolment = $student->enrolments()->withoutGlobalScope('tenant')->where('status', 'active')->firstOrFail();
        $expectedDue = app(\App\Domain\Billing\BillingService::class)->monthlyDueDateForPeriod(
            '2026-08',
            $enrolment->fee_due_day,
            $enrolment->enrolled_on,
        );
        $this->assertSame($expectedDue, $invoice->due_date?->toDateString());
        $this->assertDatabaseHas('receipts', [
            'student_id' => $student->id,
            'amount' => 1200,
        ]);
    }

    public function test_bulk_generate_batch_monthly_dues(): void
    {
        $batch = Batch::query()->withoutGlobalScope('tenant')->where('name', 'Class XI English')->firstOrFail();

        $this->actingAs($this->owner)->post('/app/students', [
            'first_name' => 'A',
            'batch_id' => $batch->id,
            'fee_style' => 'monthly',
            'fee_amount' => 1400,
        ]);
        $this->actingAs($this->owner)->post('/app/students', [
            'first_name' => 'B',
            'batch_id' => $batch->id,
            'fee_style' => 'monthly',
            'fee_amount' => 1400,
        ]);

        $this->actingAs($this->owner)
            ->post('/app/fees/batch-dues', [
                'batch_id' => $batch->id,
                'billing_period' => '2026-08',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Invoice::query()->withoutGlobalScope('tenant')->count());

        $this->actingAs($this->owner)
            ->post('/app/fees/batch-dues', [
                'batch_id' => $batch->id,
                'billing_period' => '2026-08',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Invoice::query()->withoutGlobalScope('tenant')->count());
    }

    public function test_payment_rejects_month_before_enrollment(): void
    {
        $batch = Batch::query()->withoutGlobalScope('tenant')->where('name', 'Class XI English')->firstOrFail();

        $this->actingAs($this->owner)->post('/app/students', [
            'first_name' => 'Neha',
            'batch_id' => $batch->id,
            'fee_style' => 'monthly',
            'fee_amount' => 1600,
        ]);

        $student = Student::query()->withoutGlobalScope('tenant')->where('first_name', 'Neha')->firstOrFail();

        $this->actingAs($this->owner)
            ->post('/app/fees/payments', [
                'student_id' => $student->id,
                'batch_id' => $batch->id,
                'billing_period' => '2026-01',
                'amount' => 1600,
                'mode' => 'cash',
            ])
            ->assertSessionHasErrors('billing_period');

        $this->assertSame(0, Invoice::query()->withoutGlobalScope('tenant')->count());
    }

    public function test_monthly_bill_uses_fee_due_day_from_enrolment(): void
    {
        $batch = Batch::query()->withoutGlobalScope('tenant')->where('name', 'Class XI English')->firstOrFail();

        $this->actingAs($this->owner)->post('/app/students', [
            'first_name' => 'DueDay',
            'batch_id' => $batch->id,
            'joined_on' => '2026-08-01',
            'fee_style' => 'monthly',
            'fee_amount' => 1400,
            'fee_due_day' => 15,
            'raise_first_invoice' => false,
        ]);

        $student = Student::query()->withoutGlobalScope('tenant')->where('first_name', 'DueDay')->firstOrFail();

        $this->actingAs($this->owner)
            ->post('/app/fees/payments', [
                'student_id' => $student->id,
                'batch_id' => $batch->id,
                'billing_period' => '2026-08',
                'amount' => 1400,
                'mode' => 'cash',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $invoice = Invoice::query()->withoutGlobalScope('tenant')->firstOrFail();
        $this->assertSame('2026-08-15', $invoice->due_date?->toDateString());
    }

    public function test_back_due_allowed_with_note(): void
    {
        $batch = Batch::query()->withoutGlobalScope('tenant')->where('name', 'Class XI English')->firstOrFail();

        $this->actingAs($this->owner)->post('/app/students', [
            'first_name' => 'BackDue',
            'batch_id' => $batch->id,
            'joined_on' => '2026-08-01',
            'fee_style' => 'monthly',
            'fee_amount' => 1400,
        ]);

        $student = Student::query()->withoutGlobalScope('tenant')->where('first_name', 'BackDue')->firstOrFail();

        $this->actingAs($this->owner)
            ->post('/app/fees/payments', [
                'student_id' => $student->id,
                'batch_id' => $batch->id,
                'billing_period' => '2026-06',
                'allow_back_due' => true,
                'back_due_note' => 'Arrears from previous centre',
                'amount' => 1400,
                'mode' => 'cash',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $invoice = Invoice::query()->withoutGlobalScope('tenant')->firstOrFail();
        $this->assertStringContainsString('Back due', (string) $invoice->notes);
    }

    public function test_receipt_print_page_and_student_payment_history(): void
    {
        $batch = Batch::query()->withoutGlobalScope('tenant')->where('name', 'Class XI English')->firstOrFail();

        $this->actingAs($this->owner)->post('/app/students', [
            'first_name' => 'Receipt',
            'batch_id' => $batch->id,
            'fee_style' => 'monthly',
            'fee_amount' => 1400,
        ]);

        $student = Student::query()->withoutGlobalScope('tenant')->where('first_name', 'Receipt')->firstOrFail();

        $this->actingAs($this->owner)
            ->post('/app/fees/payments', [
                'student_id' => $student->id,
                'batch_id' => $batch->id,
                'billing_period' => '2026-08',
                'amount' => 1400,
                'mode' => 'cash',
                'paid_on' => '2026-08-05',
            ])
            ->assertRedirect()
            ->assertSessionHas('print_receipt_id');

        $receiptId = session('print_receipt_id');
        $this->assertNotNull($receiptId);

        $this->actingAs($this->owner)
            ->get("/app/fees/receipts/{$receiptId}")
            ->assertOk()
            ->assertSee('Fee receipt')
            ->assertSee('Receipt');

        $history = app(BillingService::class)->studentPaymentHistory($student);
        $this->assertCount(1, $history);
        $this->assertSame('2026-08-05', $history[0]['paid_on']);
        $this->assertSame(1400.0, $history[0]['amount']);
    }
}
