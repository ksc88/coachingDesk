<?php

namespace App\Domain\Billing;

use App\Domain\Identity\AuditLogger;
use App\Models\Enrolment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Receipt;
use App\Models\ReceiptSequence;
use App\Models\Student;
use App\Models\Batch;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BillingService
{
    public function __construct(protected AuditLogger $audit) {}

    public function createInvoice(Student $student, array $data): Invoice
    {
        return $this->createInvoices($student, $data)[0];
    }

    /**
     * Create one invoice, or several for instalment plans.
     *
     * @return list<Invoice>
     */
    public function createInvoices(Student $student, array $data): array
    {
        $planType = $data['plan_type'] ?? 'custom';
        $installments = max(1, min(12, (int) ($data['installments'] ?? 1)));

        if ($planType !== 'installments') {
            $installments = 1;
        }

        $gross = (float) $data['total'];
        $discount = (float) ($data['discount_total'] ?? 0);
        $net = max(0.01, $gross - $discount);

        $parts = $this->splitAmount($net, $installments);
        $startDue = isset($data['due_date']) && $data['due_date']
            ? \Carbon\Carbon::parse($data['due_date'])
            : now();

        $invoices = [];

        foreach ($parts as $index => $amount) {
            $label = $installments > 1
                ? sprintf('Instalment %d/%d', $index + 1, $installments)
                : match ($planType) {
                    'monthly' => 'Monthly fee',
                    'term' => 'Term / lump-sum fee',
                    default => 'Fee',
                };

            $notes = trim(implode(' · ', array_filter([
                $label,
                $data['notes'] ?? null,
            ])));

            $invoiceNo = $this->nextDocumentNo('INV');
            $dueDate = $installments > 1
                ? $startDue->copy()->addMonths($index)->toDateString()
                : ($data['due_date'] ?? null);

            $invoice = Invoice::query()->create([
                'tenant_id' => $student->tenant_id,
                'student_id' => $student->id,
                'batch_id' => $data['batch_id'] ?? null,
                'fee_plan_id' => $data['fee_plan_id'] ?? null,
                'invoice_no' => $invoiceNo,
                'invoice_date' => $data['invoice_date'] ?? now()->toDateString(),
                'due_date' => $dueDate,
                'subtotal' => $amount,
                'discount_total' => $installments > 1 ? 0 : $discount,
                'fine_total' => $data['fine_total'] ?? 0,
                'tax_total' => $data['tax_total'] ?? 0,
                'total' => $amount,
                'paid_total' => 0,
                'status' => 'open',
                'notes' => $notes !== '' ? $notes : null,
            ]);

            $this->audit->log('invoice.created', $invoice);
            $invoices[] = $invoice;
        }

        return $invoices;
    }

    /**
     * Create a monthly invoice for one student+batch+month if none exists yet.
     */
    public function ensureMonthlyInvoice(Student $student, int $batchId, ?string $periodYm = null, array $options = []): ?Invoice
    {
        $periodYm = $periodYm ?? now()->format('Y-m');
        [$year, $month] = array_map('intval', explode('-', $periodYm));

        $monthLabel = \Carbon\Carbon::createFromDate($year, $month, 1)->format('M Y');

        $existing = Invoice::query()
            ->where('student_id', $student->id)
            ->where('batch_id', $batchId)
            ->whereNotNull('due_date')
            ->whereYear('due_date', $year)
            ->whereMonth('due_date', $month)
            ->where(fn ($q) => $q->where('notes', 'like', "Monthly fee%")->orWhere('notes', 'like', "{$monthLabel}%"))
            ->orderBy('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        $enrolment = Enrolment::query()
            ->where('student_id', $student->id)
            ->where('batch_id', $batchId)
            ->where('status', 'active')
            ->first();

        if (! $enrolment || ($enrolment->fee_style ?: 'monthly') !== 'monthly') {
            return null;
        }

        $this->assertBillablePeriod($enrolment, $periodYm, $options);

        $batch = Batch::query()->find($batchId);
        $amount = $enrolment->fee_amount ?? $batch?->default_fee;

        if ($amount === null || (float) $amount <= 0) {
            return null;
        }

        $dueDate = $this->monthlyDueDateForPeriod(
            $periodYm,
            $enrolment->fee_due_day,
            $enrolment->enrolled_on
        );
        $note = $monthLabel;
        if (! empty($options['back_due_note'])) {
            $note .= ' · Back due: '.$options['back_due_note'];
        }

        return $this->createInvoices($student, [
            'batch_id' => $batchId,
            'plan_type' => 'monthly',
            'total' => (float) $amount,
            'discount_total' => 0,
            'due_date' => $dueDate,
            'notes' => $note,
        ])[0];
    }

    /**
     * Raise monthly dues for every active monthly enrolment in a batch.
     *
     * @return array{created: int, skipped: int, period: string, period_label: string}
     */
    public function generateBatchMonthlyDues(Batch $batch, ?string $periodYm = null): array
    {
        $periodYm = $periodYm ?? now()->format('Y-m');
        [$year, $month] = array_map('intval', explode('-', $periodYm));
        $periodLabel = \Carbon\Carbon::createFromDate($year, $month, 1)->format('M Y');

        $created = 0;
        $skipped = 0;

        $enrolments = Enrolment::query()
            ->where('batch_id', $batch->id)
            ->where('status', 'active')
            ->with('student')
            ->get();

        foreach ($enrolments as $enrolment) {
            $student = $enrolment->student;

            if (! $student || $student->status !== 'active') {
                $skipped++;

                continue;
            }

            if (($enrolment->fee_style ?: 'monthly') !== 'monthly') {
                $skipped++;

                continue;
            }

            if ($enrolment->enrolled_on && $periodYm < $enrolment->enrolled_on->format('Y-m')) {
                $skipped++;

                continue;
            }

            $exists = Invoice::query()
                ->where('student_id', $student->id)
                ->where('batch_id', $batch->id)
                ->whereNotNull('due_date')
                ->whereYear('due_date', $year)
                ->whereMonth('due_date', $month)
                ->where(fn ($q) => $q->where('notes', 'like', 'Monthly fee%')->orWhere('notes', 'like', "{$periodLabel}%"))
                ->exists();

            if ($exists) {
                $skipped++;

                continue;
            }

            $invoice = $this->ensureMonthlyInvoice($student, $batch->id, $periodYm);

            if ($invoice) {
                $created++;
            } else {
                $skipped++;
            }
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'period' => $periodYm,
            'period_label' => $periodLabel,
        ];
    }

    protected function resolveBillingPeriod(array $data): string
    {
        if (! empty($data['billing_period'])) {
            return \Carbon\Carbon::parse($data['billing_period'].'-01')->format('Y-m');
        }

        return \Carbon\Carbon::parse($data['paid_on'] ?? now()->toDateString())->format('Y-m');
    }

    protected function assertBillablePeriod(Enrolment $enrolment, string $periodYm, array $data = []): void
    {
        if (! $enrolment->enrolled_on) {
            return;
        }

        $earliest = $enrolment->enrolled_on->format('Y-m');

        if ($periodYm >= $earliest) {
            return;
        }

        if (! empty($data['allow_back_due']) && trim((string) ($data['back_due_note'] ?? '')) !== '') {
            return;
        }

        $picked = \Carbon\Carbon::createFromFormat('Y-m', $periodYm)->format('M Y');

        throw ValidationException::withMessages([
            'billing_period' => sprintf(
                'Cannot bill for %s — student joined this batch on %s. Pick %s or later, or add a back-due note.',
                $picked,
                $enrolment->enrolled_on->format('d-m-Y'),
                $enrolment->enrolled_on->format('M Y'),
            ),
        ]);
    }

    protected function prepareOpenInvoicesForPayment(Student $student, array $data): void
    {
        if (! empty($data['invoice_id'])) {
            return;
        }

        $hasOpen = Invoice::query()
            ->where('student_id', $student->id)
            ->whereIn('status', ['open', 'partial'])
            ->exists();

        if ($hasOpen) {
            return;
        }

        $batchId = ! empty($data['batch_id']) ? (int) $data['batch_id'] : null;

        if (! $batchId) {
            $enrolment = Enrolment::query()
                ->where('student_id', $student->id)
                ->where('status', 'active')
                ->where(fn ($q) => $q->where('fee_style', 'monthly')->orWhereNull('fee_style'))
                ->first();

            $batchId = $enrolment?->batch_id;
        }

        if (! $batchId) {
            throw ValidationException::withMessages([
                'student_id' => 'No open bill. Pick a batch, generate monthly dues, or create a special invoice.',
            ]);
        }

        $periodYm = $this->resolveBillingPeriod($data);
        $options = array_filter([
            'allow_back_due' => ! empty($data['allow_back_due']),
            'back_due_note' => $data['back_due_note'] ?? null,
        ]);
        $invoice = $this->ensureMonthlyInvoice($student, $batchId, $periodYm, $options);

        if (! $invoice) {
            throw ValidationException::withMessages([
                'batch_id' => 'This batch uses term/instalment fees — create a special bill first.',
            ]);
        }

        if ($invoice->balance() <= 0.001) {
            throw ValidationException::withMessages([
                'billing_period' => 'This month is already paid. Choose another month.',
            ]);
        }
    }

    /**
     * @return list<float>
     */
    protected function splitAmount(float $total, int $parts): array
    {
        if ($parts <= 1) {
            return [round($total, 2)];
        }

        $base = floor(($total / $parts) * 100) / 100;
        $amounts = array_fill(0, $parts, $base);
        $amounts[$parts - 1] = round($total - ($base * ($parts - 1)), 2);

        return $amounts;
    }

    public function recordManualPayment(Student $student, array $data): array
    {
        return DB::transaction(function () use ($student, $data) {
            $this->prepareOpenInvoicesForPayment($student, $data);

            $payment = Payment::query()->create([
                'tenant_id' => $student->tenant_id,
                'student_id' => $student->id,
                'payment_no' => $this->nextDocumentNo('PAY'),
                'mode' => $data['mode'],
                'amount' => $data['amount'],
                'paid_on' => $data['paid_on'] ?? now()->toDateString(),
                'reference' => $data['reference'] ?? null,
                'gateway' => null,
                'status' => 'completed',
                'received_by' => Auth::id(),
                'notes' => $data['notes'] ?? null,
            ]);

            $remaining = (float) $data['amount'];
            $allocated = 0.0;
            $invoiceIds = $data['invoice_ids'] ?? [];

            if ($invoiceIds === [] && ! empty($data['invoice_id'])) {
                $invoiceIds = [$data['invoice_id']];
            }

            // No pinned invoice → FIFO: oldest due first (null due_dates last).
            if ($invoiceIds === []) {
                $invoiceIds = Invoice::query()
                    ->where('student_id', $student->id)
                    ->whereIn('status', ['open', 'partial'])
                    ->orderByRaw('due_date IS NULL')
                    ->orderBy('due_date')
                    ->orderBy('id')
                    ->pluck('id')
                    ->all();
            }

            foreach ($invoiceIds as $invoiceId) {
                if ($remaining <= 0) {
                    break;
                }

                $invoice = Invoice::query()->findOrFail($invoiceId);

                if ((int) $invoice->student_id !== (int) $student->id) {
                    continue;
                }

                $due = max(0, (float) $invoice->total - (float) $invoice->paid_total);
                if ($due <= 0) {
                    continue;
                }

                $alloc = min($due, $remaining);

                PaymentAllocation::query()->create([
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoice->id,
                    'amount' => $alloc,
                ]);

                $invoice->paid_total = (float) $invoice->paid_total + $alloc;
                $invoice->status = $invoice->paid_total + 0.001 >= (float) $invoice->total ? 'paid' : 'partial';
                $invoice->save();

                $remaining -= $alloc;
                $allocated += $alloc;
            }

            if ($allocated <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Could not apply payment to any open bill.',
                ]);
            }

            $receipt = $this->issueReceipt($payment);

            $this->audit->log('payment.recorded', $payment);

            return compact('payment', 'receipt');
        });
    }

    /**
     * Month-wise ledger rows for one student (due / paid / pending / status).
     *
     * @return list<array{period: string, period_label: string, due: float, paid: float, pending: float, status: string, invoices: int, batch_id: int|null, batch_name: string|null}>
     */
    public function studentMonthLedger(Student $student, ?int $batchId = null): array
    {
        $invoices = Invoice::query()
            ->where('student_id', $student->id)
            ->when($batchId, fn ($q) => $q->where('batch_id', $batchId))
            ->with('batch:id,name')
            ->orderByRaw('COALESCE(due_date, invoice_date) ASC')
            ->orderBy('id')
            ->get();

        $groups = [];

        foreach ($invoices as $invoice) {
            $period = $invoice->billingPeriod();
            $groupKey = $batchId
                ? $period
                : $period.'|'.($invoice->batch_id ?? 0);

            if (! isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'period' => $period,
                    'period_label' => $invoice->billingPeriodLabel(),
                    'due' => 0.0,
                    'paid' => 0.0,
                    'pending' => 0.0,
                    'status' => 'paid',
                    'invoices' => 0,
                    'batch_id' => $invoice->batch_id,
                    'batch_name' => $invoice->batch?->name,
                ];
            }

            $groups[$groupKey]['due'] += (float) $invoice->total;
            $groups[$groupKey]['paid'] += (float) $invoice->paid_total;
            $groups[$groupKey]['pending'] += $invoice->balance();
            $groups[$groupKey]['invoices']++;
            $groups[$groupKey]['status'] = $this->worseLedgerStatus(
                $groups[$groupKey]['status'],
                $invoice->displayStatus()
            );
        }

        return array_values(array_map(function (array $row) {
            $row['due'] = round($row['due'], 2);
            $row['paid'] = round($row['paid'], 2);
            $row['pending'] = round($row['pending'], 2);

            return $row;
        }, $groups));
    }

    /**
     * Payment history for one student (newest first).
     *
     * @return list<array<string, mixed>>
     */
    public function studentPaymentHistory(Student $student): array
    {
        return Payment::query()
            ->where('student_id', $student->id)
            ->with(['receipt:id,payment_id,receipt_no', 'allocations.invoice.batch:id,name'])
            ->orderByDesc('paid_on')
            ->orderByDesc('id')
            ->get()
            ->map(function (Payment $payment) {
                return [
                    'id' => $payment->id,
                    'payment_no' => $payment->payment_no,
                    'amount' => (float) $payment->amount,
                    'paid_on' => $payment->paid_on?->toDateString(),
                    'mode' => $payment->mode,
                    'reference' => $payment->reference,
                    'receipt_id' => $payment->receipt?->id,
                    'receipt_no' => $payment->receipt?->receipt_no,
                    'allocations' => $payment->allocations->map(fn (PaymentAllocation $a) => [
                        'amount' => (float) $a->amount,
                        'invoice_no' => $a->invoice?->invoice_no,
                        'batch_name' => $a->invoice?->batch?->name,
                        'notes' => $a->invoice?->notes,
                    ])->values()->all(),
                ];
            })
            ->all();
    }

    protected function worseLedgerStatus(string $current, string $next): string
    {
        $rank = [
            'paid' => 0,
            'not_due' => 1,
            'due' => 2,
            'partial' => 3,
            'overdue' => 4,
        ];

        return ($rank[$next] ?? 0) >= ($rank[$current] ?? 0) ? $next : $current;
    }

    public function recordGatewayPayment(Student $student, array $data): array
    {
        return DB::transaction(function () use ($student, $data) {
            $existing = Payment::query()
                ->where('gateway_payment_id', $data['gateway_payment_id'])
                ->first();

            if ($existing) {
                $receipt = Receipt::query()->where('payment_id', $existing->id)->first();

                return ['payment' => $existing, 'receipt' => $receipt];
            }

            $payment = Payment::query()->create([
                'tenant_id' => $student->tenant_id,
                'student_id' => $student->id,
                'payment_no' => $this->nextDocumentNo('PAY'),
                'mode' => 'razorpay',
                'amount' => $data['amount'],
                'paid_on' => $data['paid_on'] ?? now()->toDateString(),
                'reference' => $data['reference'] ?? null,
                'gateway' => 'razorpay',
                'gateway_order_id' => $data['gateway_order_id'] ?? null,
                'gateway_payment_id' => $data['gateway_payment_id'],
                'status' => 'completed',
                'received_by' => Auth::id(),
                'notes' => $data['notes'] ?? null,
            ]);

            if (! empty($data['invoice_id'])) {
                $invoice = Invoice::query()->findOrFail($data['invoice_id']);
                PaymentAllocation::query()->create([
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoice->id,
                    'amount' => min((float) $data['amount'], max(0, (float) $invoice->total - (float) $invoice->paid_total)),
                ]);
                $invoice->paid_total = (float) $invoice->paid_total + (float) $data['amount'];
                $invoice->status = $invoice->paid_total + 0.001 >= (float) $invoice->total ? 'paid' : 'partial';
                $invoice->save();
            }

            $receipt = $this->issueReceipt($payment);
            $this->audit->log('payment.gateway', $payment);

            return compact('payment', 'receipt');
        });
    }

    public function issueReceipt(Payment $payment): Receipt
    {
        $existing = Receipt::query()->where('payment_id', $payment->id)->first();
        if ($existing) {
            return $existing;
        }

        $fy = $this->financialYear($payment->paid_on?->toDateString() ?? now()->toDateString());
        $tenant = Tenant::query()->findOrFail($payment->tenant_id);

        $sequence = ReceiptSequence::query()->firstOrCreate(
            ['tenant_id' => $payment->tenant_id, 'financial_year' => $fy],
            ['last_number' => 0]
        );

        $sequence->last_number++;
        $sequence->save();

        $receiptNo = sprintf('%s/%s/%05d', $tenant->code, $fy, $sequence->last_number);

        return Receipt::query()->create([
            'tenant_id' => $payment->tenant_id,
            'payment_id' => $payment->id,
            'student_id' => $payment->student_id,
            'receipt_no' => $receiptNo,
            'financial_year' => $fy,
            'issued_on' => $payment->paid_on,
            'amount' => $payment->amount,
            'issued_by' => Auth::id(),
            'snapshot' => [
                'mode' => $payment->mode,
                'reference' => $payment->reference,
                'student_id' => $payment->student_id,
            ],
        ]);
    }

    public function financialYear(string $date): string
    {
        $ts = strtotime($date);
        $year = (int) date('Y', $ts);
        $month = (int) date('n', $ts);

        if ($month >= 4) {
            return substr((string) $year, -2).substr((string) ($year + 1), -2);
        }

        return substr((string) ($year - 1), -2).substr((string) $year, -2);
    }

    protected function nextDocumentNo(string $prefix): string
    {
        static $seq = 0;
        $seq++;

        return $prefix.'-'.now()->format('YmdHis').'-'.str_pad((string) $seq, 3, '0', STR_PAD_LEFT).substr(uniqid(), -3);
    }

    /**
     * Due date for a monthly bill: given billing month (Y-m) and due day (e.g. 5th).
     * If student joined mid-month after the due day, first month uses join date.
     */
    public function monthlyDueDateForPeriod(
        string $periodYm,
        ?int $dueDay,
        ?\Carbon\CarbonInterface $enrolledOn = null
    ): string {
        [$year, $month] = array_map('intval', explode('-', $periodYm));
        $day = $this->normalizeDueDay($dueDay);
        $lastDayOfMonth = (int) \Carbon\Carbon::createFromDate($year, $month, 1)->endOfMonth()->format('j');
        $due = \Carbon\Carbon::createFromDate($year, $month, min($day, $lastDayOfMonth));

        if ($enrolledOn !== null && $enrolledOn->format('Y-m') === $periodYm) {
            $joined = $enrolledOn->copy()->startOfDay();
            if ($joined->gt($due)) {
                return $joined->toDateString();
            }
        }

        return $due->toDateString();
    }

    public function normalizeDueDay(?int $dueDay): int
    {
        $default = (int) config('coaching.default_fee_due_day', 5);

        return min(28, max(1, $dueDay ?? $default));
    }
}
