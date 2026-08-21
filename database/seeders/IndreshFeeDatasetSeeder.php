<?php

namespace Database\Seeders;

use App\Domain\Billing\BillingService;
use App\Models\Batch;
use App\Models\Enrolment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Receipt;
use App\Models\ReceiptSequence;
use App\Models\Student;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Full fee-scenario dataset for Indresh English Classes (INDR) only.
 *
 * Search students in Fees → Collect fee by "FEE" or admission no FEE-…
 *
 * Re-run safely: wipes INDR invoices/payments/receipts, upserts FEE-* students.
 */
class IndreshFeeDatasetSeeder extends Seeder
{
    protected Tenant $tenant;

    protected BillingService $billing;

    protected Batch $batchX;

    protected Batch $batchXI;

    protected Batch $batchXII;

    protected int $branchId;

    /** @var list<array{code: string, name: string, detail: string}> */
    protected array $summary = [];

    public function run(): void
    {
        $this->tenant = Tenant::query()->where('code', 'INDR')->firstOrFail();
        TenantContext::set($this->tenant);
        $this->billing = app(BillingService::class);
        $this->branchId = (int) $this->tenant->branches()->value('id');

        $this->batchX = Batch::query()->where('name', 'like', '%Class X%')->firstOrFail();
        $this->batchXI = Batch::query()->where('name', 'like', '%Class XI%')->firstOrFail();
        $this->batchXII = Batch::query()->where('name', 'like', '%Class XII%')->firstOrFail();

        $this->wipeMoney();

        $this->caseMonthlyOpen();
        $this->caseMonthlyPaid();
        $this->caseMonthlyPartial();
        $this->caseMonthlyOverdue();
        $this->caseMonthlyArrearsPaid();
        $this->caseMonthlyDueDay15();
        $this->caseMonthlyMidJoin();
        $this->caseTermOpen();
        $this->caseTermPaidWithDiscount();
        $this->caseTermPartial();
        $this->caseInstallmentsAllOpen();
        $this->caseInstallmentsInProgress();
        $this->caseInstallmentsFirstOverdue();
        $this->caseCustomAdmissionPlusMonthlyReady();
        $this->caseMultiBatchFifo();
        $this->caseCollectReadyNoInvoice();
        $this->caseBatchDuesReady();

        $this->command?->newLine();
        $this->command?->info('=== INDR fee dataset (search Collect fee for "FEE") ===');
        foreach ($this->summary as $row) {
            $this->command?->line(sprintf('%-10s  %-28s  %s', $row['code'], $row['name'], $row['detail']));
        }
        $this->command?->newLine();
        $this->command?->info(sprintf(
            'Totals: students=%d invoices=%d payments=%d receipts=%d',
            Student::query()->where('admission_no', 'like', 'FEE-%')->count(),
            Invoice::query()->count(),
            Payment::query()->count(),
            Receipt::query()->count(),
        ));
    }

    protected function wipeMoney(): void
    {
        $tid = $this->tenant->id;

        DB::transaction(function () use ($tid) {
            $paymentIds = Payment::withoutGlobalScopes()->where('tenant_id', $tid)->pluck('id');
            PaymentAllocation::query()->whereIn('payment_id', $paymentIds)->delete();
            Receipt::withoutGlobalScopes()->where('tenant_id', $tid)->delete();
            Payment::withoutGlobalScopes()->where('tenant_id', $tid)->delete();
            Invoice::withoutGlobalScopes()->where('tenant_id', $tid)->delete();
            ReceiptSequence::withoutGlobalScopes()->where('tenant_id', $tid)->update(['last_number' => 0]);
        });
    }

    protected function student(string $code, string $first, string $last, string $classLevel, string $joinedOn): Student
    {
        $student = Student::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $this->tenant->id, 'admission_no' => $code],
            [
                'branch_id' => $this->branchId,
                'first_name' => $first,
                'last_name' => $last,
                'class_level' => $classLevel,
                'phone' => '9'.substr(preg_replace('/\D/', '', $code).'000000000', 0, 9),
                'status' => 'active',
                'joined_on' => $joinedOn,
                'remarks' => 'Fee dataset case '.$code,
            ],
        );

        return $student;
    }

    protected function enrol(
        Student $student,
        Batch $batch,
        string $style,
        float $amount,
        string $enrolledOn,
        ?int $installments = null,
        ?int $dueDay = null,
    ): Enrolment {
        $enrolment = Enrolment::withoutGlobalScopes()->updateOrCreate(
            [
                'tenant_id' => $this->tenant->id,
                'student_id' => $student->id,
                'batch_id' => $batch->id,
            ],
            [
                'enrolled_on' => $enrolledOn,
                'status' => 'active',
                'fee_style' => $style,
                'fee_amount' => $amount,
                'fee_installments' => $style === 'installments' ? ($installments ?? 3) : null,
                'fee_due_day' => $style === 'monthly' ? ($dueDay ?? 5) : null,
            ],
        );

        return $enrolment;
    }

    protected function note(string $code, string $name, string $detail): void
    {
        $this->summary[] = compact('code', 'name', 'detail');
    }

    protected function caseMonthlyOpen(): void
    {
        $s = $this->student('FEE-M01', 'Monthly', 'Open', 'X', '2026-04-01');
        $this->enrol($s, $this->batchX, 'monthly', 1200, '2026-04-01', null, 5);
        $inv = $this->billing->ensureMonthlyInvoice($s, $this->batchX->id, '2026-08');
        $this->note('FEE-M01', 'Monthly Open', "open ₹{$inv->total} Aug — collect full");
    }

    protected function caseMonthlyPaid(): void
    {
        $s = $this->student('FEE-M02', 'Monthly', 'Paid', 'X', '2026-04-01');
        $this->enrol($s, $this->batchX, 'monthly', 1200, '2026-04-01', null, 5);
        $inv = $this->billing->ensureMonthlyInvoice($s, $this->batchX->id, '2026-08');
        $this->billing->recordManualPayment($s, [
            'invoice_id' => $inv->id,
            'amount' => 1200,
            'mode' => 'cash',
            'paid_on' => now()->toDateString(),
        ]);
        $this->note('FEE-M02', 'Monthly Paid', 'Aug fully paid (cash)');
    }

    protected function caseMonthlyPartial(): void
    {
        $s = $this->student('FEE-M03', 'Monthly', 'Partial', 'XI', '2026-04-01');
        $this->enrol($s, $this->batchXI, 'monthly', 1400, '2026-04-01', null, 5);
        $inv = $this->billing->ensureMonthlyInvoice($s, $this->batchXI->id, '2026-08');
        $this->billing->recordManualPayment($s, [
            'invoice_id' => $inv->id,
            'amount' => 500,
            'mode' => 'upi',
            'paid_on' => now()->toDateString(),
        ]);
        $inv->refresh();
        $this->note('FEE-M03', 'Monthly Partial', "paid ₹500 · balance ₹{$inv->balance()}");
    }

    protected function caseMonthlyOverdue(): void
    {
        $s = $this->student('FEE-M04', 'Monthly', 'Overdue', 'XII', '2026-04-01');
        $this->enrol($s, $this->batchXII, 'monthly', 1600, '2026-04-01', null, 5);
        $inv = $this->billing->createInvoices($s, [
            'batch_id' => $this->batchXII->id,
            'plan_type' => 'monthly',
            'total' => 1600,
            'due_date' => now()->subDays(10)->toDateString(),
            'notes' => 'FEE-M04 overdue July carry',
        ])[0];
        $this->note('FEE-M04', 'Monthly Overdue', "unpaid overdue · due {$inv->due_date->toDateString()}");
    }

    protected function caseMonthlyArrearsPaid(): void
    {
        $s = $this->student('FEE-M05', 'Monthly', 'ArrearsPaid', 'X', '2026-04-01');
        $this->enrol($s, $this->batchX, 'monthly', 1200, '2026-04-01', null, 5);
        $this->billing->recordManualPayment($s, [
            'batch_id' => $this->batchX->id,
            'billing_period' => '2026-06',
            'allow_back_due' => true,
            'back_due_note' => 'Arrears from previous centre',
            'amount' => 1200,
            'mode' => 'bank',
            'paid_on' => now()->toDateString(),
        ]);
        $this->note('FEE-M05', 'Monthly ArrearsPaid', 'Jun back-due paid (bank)');
    }

    protected function caseMonthlyDueDay15(): void
    {
        $s = $this->student('FEE-M06', 'Monthly', 'DueDay15', 'XI', '2026-04-01');
        $this->enrol($s, $this->batchXI, 'monthly', 1400, '2026-04-01', null, 15);
        $inv = $this->billing->ensureMonthlyInvoice($s, $this->batchXI->id, '2026-08');
        $this->note('FEE-M06', 'Monthly DueDay15', "open · due {$inv->due_date->toDateString()}");
    }

    protected function caseMonthlyMidJoin(): void
    {
        $joined = '2026-08-10';
        $s = $this->student('FEE-M07', 'Monthly', 'MidJoin', 'X', $joined);
        $this->enrol($s, $this->batchX, 'monthly', 1200, $joined, null, 5);
        $inv = $this->billing->ensureMonthlyInvoice($s, $this->batchX->id, '2026-08');
        $this->note('FEE-M07', 'Monthly MidJoin', "joined {$joined} · due {$inv->due_date->toDateString()}");
    }

    protected function caseTermOpen(): void
    {
        $s = $this->student('FEE-T01', 'Term', 'Open', 'XI', '2026-04-01');
        $this->enrol($s, $this->batchXI, 'term', 15000, '2026-04-01');
        $inv = $this->billing->createInvoices($s, [
            'batch_id' => $this->batchXI->id,
            'plan_type' => 'term',
            'total' => 15000,
            'due_date' => now()->addDays(14)->toDateString(),
            'notes' => 'FEE-T01 term open',
        ])[0];
        $this->note('FEE-T01', 'Term Open', "open ₹{$inv->total}");
    }

    protected function caseTermPaidWithDiscount(): void
    {
        $s = $this->student('FEE-T02', 'Term', 'PaidDisc', 'XI', '2026-04-01');
        $this->enrol($s, $this->batchXI, 'term', 15000, '2026-04-01');
        $inv = $this->billing->createInvoices($s, [
            'batch_id' => $this->batchXI->id,
            'plan_type' => 'term',
            'total' => 15000,
            'discount_total' => 2000,
            'due_date' => now()->toDateString(),
            'notes' => 'Sibling discount',
        ])[0];
        $this->billing->recordManualPayment($s, [
            'invoice_id' => $inv->id,
            'amount' => 13000,
            'mode' => 'bank',
            'paid_on' => now()->toDateString(),
        ]);
        $this->note('FEE-T02', 'Term PaidDisc', '₹15k − ₹2k discount · paid');
    }

    protected function caseTermPartial(): void
    {
        $s = $this->student('FEE-T03', 'Term', 'Partial', 'XII', '2026-04-01');
        $this->enrol($s, $this->batchXII, 'term', 18000, '2026-04-01');
        $inv = $this->billing->createInvoices($s, [
            'batch_id' => $this->batchXII->id,
            'plan_type' => 'term',
            'total' => 18000,
            'due_date' => now()->addDays(7)->toDateString(),
            'notes' => 'FEE-T03 term partial',
        ])[0];
        $this->billing->recordManualPayment($s, [
            'invoice_id' => $inv->id,
            'amount' => 6000,
            'mode' => 'upi',
            'paid_on' => now()->toDateString(),
        ]);
        $inv->refresh();
        $this->note('FEE-T03', 'Term Partial', "paid ₹6k · balance ₹{$inv->balance()}");
    }

    protected function caseInstallmentsAllOpen(): void
    {
        $s = $this->student('FEE-I01', 'Inst', 'AllOpen', 'X', '2026-04-01');
        $this->enrol($s, $this->batchX, 'installments', 15000, '2026-04-01', 3);
        $parts = $this->billing->createInvoices($s, [
            'batch_id' => $this->batchX->id,
            'plan_type' => 'installments',
            'installments' => 3,
            'total' => 15000,
            'due_date' => '2026-08-01',
            'notes' => 'FEE-I01',
        ]);
        $this->note('FEE-I01', 'Inst AllOpen', count($parts).' open instalments (₹5k each)');
    }

    protected function caseInstallmentsInProgress(): void
    {
        $s = $this->student('FEE-I02', 'Inst', 'Progress', 'X', '2026-04-01');
        $this->enrol($s, $this->batchX, 'installments', 15000, '2026-04-01', 3);
        $parts = $this->billing->createInvoices($s, [
            'batch_id' => $this->batchX->id,
            'plan_type' => 'installments',
            'installments' => 3,
            'total' => 15000,
            'due_date' => '2026-07-01',
            'notes' => 'FEE-I02',
        ]);
        $this->billing->recordManualPayment($s, [
            'invoice_id' => $parts[0]->id,
            'amount' => 5000,
            'mode' => 'cash',
            'paid_on' => now()->toDateString(),
        ]);
        $this->note('FEE-I02', 'Inst Progress', '1/3 paid · 2 open');
    }

    protected function caseInstallmentsFirstOverdue(): void
    {
        $s = $this->student('FEE-I03', 'Inst', 'Overdue', 'XI', '2026-04-01');
        $this->enrol($s, $this->batchXI, 'installments', 18000, '2026-04-01', 3);
        $parts = $this->billing->createInvoices($s, [
            'batch_id' => $this->batchXI->id,
            'plan_type' => 'installments',
            'installments' => 3,
            'total' => 18000,
            'due_date' => now()->subDays(20)->toDateString(),
            'notes' => 'FEE-I03',
        ]);
        $this->note('FEE-I03', 'Inst Overdue', '1st overdue unpaid · 2 later open');
        unset($parts);
    }

    protected function caseCustomAdmissionPlusMonthlyReady(): void
    {
        $s = $this->student('FEE-C01', 'Custom', 'Admission', 'X', '2026-08-01');
        $this->enrol($s, $this->batchX, 'monthly', 1200, '2026-08-01', null, 5);
        $inv = $this->billing->createInvoices($s, [
            'batch_id' => $this->batchX->id,
            'plan_type' => 'custom',
            'total' => 500,
            'due_date' => now()->toDateString(),
            'notes' => 'Admission',
        ])[0];
        $this->billing->recordManualPayment($s, [
            'invoice_id' => $inv->id,
            'amount' => 500,
            'mode' => 'cash',
            'paid_on' => now()->toDateString(),
        ]);
        $this->note('FEE-C01', 'Custom Admission', 'admission ₹500 paid · Aug monthly not raised');
    }

    protected function caseMultiBatchFifo(): void
    {
        $s = $this->student('FEE-X01', 'Multi', 'BatchFifo', 'XI', '2026-04-01');
        $this->enrol($s, $this->batchX, 'monthly', 1200, '2026-04-01', null, 5);
        $this->enrol($s, $this->batchXI, 'monthly', 1400, '2026-04-01', null, 5);
        $older = $this->billing->createInvoices($s, [
            'batch_id' => $this->batchX->id,
            'plan_type' => 'monthly',
            'total' => 1200,
            'due_date' => now()->subDays(12)->toDateString(),
            'notes' => 'FEE-X01 Class X',
        ])[0];
        $newer = $this->billing->createInvoices($s, [
            'batch_id' => $this->batchXI->id,
            'plan_type' => 'monthly',
            'total' => 1400,
            'due_date' => now()->toDateString(),
            'notes' => 'FEE-X01 Class XI',
        ])[0];
        $this->billing->recordManualPayment($s, [
            'amount' => 1500,
            'mode' => 'upi',
            'paid_on' => now()->toDateString(),
        ]);
        $older->refresh();
        $newer->refresh();
        $this->note(
            'FEE-X01',
            'Multi BatchFifo',
            "X={$older->status} XI={$newer->status} (₹1500 FIFO)",
        );
    }

    protected function caseCollectReadyNoInvoice(): void
    {
        $s = $this->student('FEE-R01', 'Collect', 'Ready', 'XII', '2026-04-01');
        $this->enrol($s, $this->batchXII, 'monthly', 1600, '2026-04-01', null, 5);
        $this->note('FEE-R01', 'Collect Ready', 'no bill — use Collect fee (This month)');
    }

    protected function caseBatchDuesReady(): void
    {
        $s = $this->student('FEE-R02', 'BatchDues', 'Ready', 'X', '2026-04-01');
        $this->enrol($s, $this->batchX, 'monthly', 1200, '2026-04-01', null, 5);
        $this->note('FEE-R02', 'BatchDues Ready', 'no bill — use Batch month generate');
    }
}
