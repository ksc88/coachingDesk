<?php

/**
 * One-shot Indresh fee case runner. Usage:
 *   php artisan tinker --execute="require 'database/scripts/run_fee_cases.php';"
 */

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
use Illuminate\Support\Facades\DB;

$t = Tenant::where('code', 'INDR')->firstOrFail();
app(TenantContext::class)->set($t);
$billing = app(BillingService::class);

DB::transaction(function () {
    PaymentAllocation::query()->delete();
    Receipt::query()->delete();
    Payment::query()->delete();
    Invoice::query()->delete();
    ReceiptSequence::query()->update(['last_number' => 0]);
});

$batchX = Batch::where('name', 'like', '%Class X%')->firstOrFail();
$batchXI = Batch::where('name', 'like', '%Class XI%')->firstOrFail();
$batchXII = Batch::where('name', 'like', '%Class XII%')->firstOrFail();

$pawan = Student::where('admission_no', 'ADM-260803-841')->firstOrFail();
$neha = Student::where('admission_no', '2026-0003')->firstOrFail();
$riya = Student::where('admission_no', 'ADM-260803-419')->firstOrFail();
$vikram = Student::where('admission_no', 'ADM-260803-513')->firstOrFail();
$kabir = Student::where('admission_no', '2026-0002')->firstOrFail();

$setFee = function (Student $student, Batch $batch, string $style, float $amount, ?int $installments = null) {
    $e = Enrolment::query()
        ->where('student_id', $student->id)
        ->where('batch_id', $batch->id)
        ->where('status', 'active')
        ->first();

    if (! $e) {
        $e = Enrolment::query()->create([
            'tenant_id' => $student->tenant_id,
            'student_id' => $student->id,
            'batch_id' => $batch->id,
            'enrolled_on' => now()->toDateString(),
            'status' => 'active',
        ]);
    }

    $e->update([
        'fee_style' => $style,
        'fee_amount' => $amount,
        'fee_installments' => $style === 'installments' ? ($installments ?? 3) : null,
    ]);

    return $e->fresh();
};

$results = [];

// CASE 1 — Monthly full pay (Pawan)
$setFee($pawan, $batchX, 'monthly', 1200);
$inv1 = $billing->createInvoices($pawan, [
    'batch_id' => $batchX->id,
    'plan_type' => 'monthly',
    'total' => 1200,
    'due_date' => now()->toDateString(),
    'notes' => 'CASE1 monthly',
])[0];
$pay1 = $billing->recordManualPayment($pawan, [
    'invoice_id' => $inv1->id,
    'amount' => 1200,
    'mode' => 'cash',
    'paid_on' => now()->toDateString(),
]);
$inv1->refresh();
$results[] = [
    'case' => '1 Monthly full',
    'student' => $pawan->first_name,
    'ok' => $inv1->status === 'paid' && $pay1['receipt'] !== null,
    'detail' => "status={$inv1->status} paid={$inv1->paid_total} receipt={$pay1['receipt']->receipt_no}",
];

// CASE 2 — Partial + overdue (Neha)
$setFee($neha, $batchXII, 'monthly', 1600);
$inv2 = $billing->createInvoices($neha, [
    'batch_id' => $batchXII->id,
    'plan_type' => 'monthly',
    'total' => 1600,
    'due_date' => now()->subDays(3)->toDateString(),
    'notes' => 'CASE2 overdue partial',
])[0];
$billing->recordManualPayment($neha, [
    'invoice_id' => $inv2->id,
    'amount' => 600,
    'mode' => 'upi',
    'paid_on' => now()->toDateString(),
]);
$inv2->refresh();
$results[] = [
    'case' => '2 Partial overdue',
    'student' => $neha->first_name,
    'ok' => $inv2->status === 'partial' && $inv2->displayStatus() === 'overdue' && abs($inv2->balance() - 1000) < 0.01,
    'detail' => "status={$inv2->status} display={$inv2->displayStatus()} balance={$inv2->balance()}",
];

// CASE 3 — Term lump (Riya)
$setFee($riya, $batchXI, 'term', 15000);
$inv3 = $billing->createInvoices($riya, [
    'batch_id' => $batchXI->id,
    'plan_type' => 'term',
    'total' => 15000,
    'due_date' => now()->addDays(7)->toDateString(),
    'notes' => 'CASE3 term',
])[0];
$billing->recordManualPayment($riya, [
    'invoice_id' => $inv3->id,
    'amount' => 15000,
    'mode' => 'bank',
    'paid_on' => now()->toDateString(),
]);
$inv3->refresh();
$results[] = [
    'case' => '3 Term full',
    'student' => $riya->first_name,
    'ok' => $inv3->status === 'paid' && str_contains((string) $inv3->notes, 'Term'),
    'detail' => "status={$inv3->status} notes={$inv3->notes}",
];

// CASE 4 — Instalments (Vikram)
$setFee($vikram, $batchX, 'installments', 15000, 3);
$parts = $billing->createInvoices($vikram, [
    'batch_id' => $batchX->id,
    'plan_type' => 'installments',
    'installments' => 3,
    'total' => 15000,
    'due_date' => now()->toDateString(),
    'notes' => 'CASE4 instalments',
]);
$billing->recordManualPayment($vikram, [
    'invoice_id' => $parts[0]->id,
    'amount' => 5000,
    'mode' => 'cash',
    'paid_on' => now()->toDateString(),
]);
$parts[0]->refresh();
$openLater = collect($parts)->slice(1)->every(fn ($i) => $i->fresh()->status === 'open');
$results[] = [
    'case' => '4 Instalments 3',
    'student' => $vikram->first_name,
    'ok' => count($parts) === 3 && $parts[0]->status === 'paid' && $openLater,
    'detail' => 'count='.count($parts).' first='.$parts[0]->status.' amounts='.collect($parts)->pluck('total')->implode(','),
];

// CASE 5 — Custom admission (Pawan)
$inv5 = $billing->createInvoices($pawan, [
    'batch_id' => $batchX->id,
    'plan_type' => 'custom',
    'total' => 500,
    'due_date' => now()->toDateString(),
    'notes' => 'Admission',
])[0];
$billing->recordManualPayment($pawan, [
    'invoice_id' => $inv5->id,
    'amount' => 500,
    'mode' => 'cash',
    'paid_on' => now()->toDateString(),
]);
$inv5->refresh();
$ledgerPawan = $billing->studentMonthLedger($pawan);
$results[] = [
    'case' => '5 Custom admission',
    'student' => $pawan->first_name,
    'ok' => $inv5->status === 'paid' && str_contains((string) $inv5->notes, 'Admission'),
    'detail' => "status={$inv5->status} ledger_months=".count($ledgerPawan),
];

// CASE 6 — Multi-batch FIFO (Kabir)
$setFee($kabir, $batchX, 'monthly', 1200);
$setFee($kabir, $batchXI, 'monthly', 1400);
$k1 = $billing->createInvoices($kabir, [
    'batch_id' => $batchX->id,
    'plan_type' => 'monthly',
    'total' => 1200,
    'due_date' => now()->subDays(10)->toDateString(),
    'notes' => 'CASE6 Class X',
])[0];
$k2 = $billing->createInvoices($kabir, [
    'batch_id' => $batchXI->id,
    'plan_type' => 'monthly',
    'total' => 1400,
    'due_date' => now()->toDateString(),
    'notes' => 'CASE6 Class XI',
])[0];
$billing->recordManualPayment($kabir, [
    'amount' => 1500,
    'mode' => 'upi',
    'paid_on' => now()->toDateString(),
]);
$k1->refresh();
$k2->refresh();
$results[] = [
    'case' => '6 Multi-batch FIFO',
    'student' => $kabir->first_name,
    'ok' => $k1->status === 'paid' && $k2->status === 'partial' && abs((float) $k2->paid_total - 300) < 0.01,
    'detail' => "X={$k1->status}/{$k1->paid_total} XI={$k2->status}/{$k2->paid_total}",
];

$overdue = Invoice::query()->whereIn('status', ['open', 'partial'])->whereDate('due_date', '<', today())->get();
$partial = Invoice::query()->where('status', 'partial')->count();
$collectedToday = (float) Payment::query()->whereDate('paid_on', today())->sum('amount');

echo "=== FEE CASE RESULTS (INDR) ===\n";
foreach ($results as $r) {
    echo ($r['ok'] ? 'PASS' : 'FAIL').' | '.$r['case'].' | '.$r['student'].' | '.$r['detail']."\n";
}
echo "=== TOTALS ===\n";
echo 'invoices='.Invoice::query()->count().' payments='.Payment::query()->count().' receipts='.Receipt::query()->count()."\n";
echo 'overdue_count='.$overdue->count().' partial='.$partial.' collected_today='.$collectedToday."\n";
echo 'all_pass='.(collect($results)->every(fn ($r) => $r['ok']) ? 'YES' : 'NO')."\n";
