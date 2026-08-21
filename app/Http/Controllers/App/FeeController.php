<?php

namespace App\Http\Controllers\App;

use App\Domain\Billing\BillingService;
use App\Domain\Billing\TenantGatewayResolver;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Enrolment;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\Student;
use App\Models\TenantPaymentGateway;
use App\Support\Contracts\PaymentGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

class FeeController extends Controller
{
    public function __construct(
        protected BillingService $billing,
        protected TenantGatewayResolver $gateways,
        protected PaymentGateway $razorpay,
    ) {}

    public function index(Request $request): Response
    {
        $students = Student::query()
            ->where('status', 'active')
            ->with([
                'enrolments' => fn ($q) => $q->where('status', 'active')->with('batch:id,name,default_fee'),
            ])
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'admission_no', 'phone'])
            ->map(function (Student $student) {
                $batches = $student->enrolments
                    ->map(function ($enrolment) {
                        $batch = $enrolment->batch;
                        if (! $batch) {
                            return null;
                        }

                        $amount = $enrolment->fee_amount !== null
                            ? (float) $enrolment->fee_amount
                            : ($batch->default_fee !== null ? (float) $batch->default_fee : null);

                        return [
                            'id' => $batch->id,
                            'name' => $batch->name,
                            'default_fee' => $batch->default_fee !== null ? (float) $batch->default_fee : null,
                            'fee_style' => $enrolment->fee_style ?: 'monthly',
                            'fee_amount' => $amount,
                            'fee_installments' => $enrolment->fee_installments,
                            'enrolled_on' => optional($enrolment->enrolled_on)->toDateString(),
                            'fee_due_day' => $enrolment->fee_due_day,
                            'fee_first_due_date' => optional($enrolment->fee_first_due_date)->toDateString(),
                        ];
                    })
                    ->filter()
                    ->unique('id')
                    ->values()
                    ->all();

                $primary = $batches[0] ?? null;

                return [
                    'id' => $student->id,
                    'first_name' => $student->first_name,
                    'last_name' => $student->last_name,
                    'admission_no' => $student->admission_no,
                    'phone' => $student->phone,
                    'batch_id' => $primary['id'] ?? null,
                    'batch_name' => $primary['name'] ?? null,
                    'batch_fee' => $primary['fee_amount'] ?? $primary['default_fee'] ?? null,
                    'fee_style' => $primary['fee_style'] ?? 'monthly',
                    'fee_installments' => $primary['fee_installments'] ?? null,
                    'batches' => $batches,
                ];
            });

        $gateway = TenantPaymentGateway::query()->where('provider', 'razorpay')->first();

        $mapInvoice = fn (Invoice $invoice) => $invoice->toLedgerArray();

        $ledgerStudentId = $request->integer('ledger_student_id') ?: null;
        $ledgerStudentBatchId = $request->integer('ledger_student_batch_id') ?: null;
        $ledgerBatchId = $request->integer('ledger_batch_id') ?: null;
        $ledger = null;
        if ($ledgerStudentId) {
            $ledgerStudent = Student::query()->find($ledgerStudentId);
            if ($ledgerStudent) {
                $ledgerBatches = Enrolment::query()
                    ->where('student_id', $ledgerStudent->id)
                    ->where('status', 'active')
                    ->with('batch:id,name')
                    ->get()
                    ->map(fn ($e) => [
                        'id' => $e->batch_id,
                        'name' => $e->batch?->name ?? 'Batch',
                    ])
                    ->filter(fn ($b) => $b['id'])
                    ->unique('id')
                    ->values()
                    ->all();

                $ledger = [
                    'student_id' => $ledgerStudent->id,
                    'student_name' => trim($ledgerStudent->first_name.' '.($ledgerStudent->last_name ?? '')),
                    'batches' => $ledgerBatches,
                    'selected_batch_id' => $ledgerStudentBatchId,
                    'months' => $this->billing->studentMonthLedger($ledgerStudent, $ledgerStudentBatchId),
                    'payments' => $this->billing->studentPaymentHistory($ledgerStudent),
                    'outstanding' => (float) Invoice::query()
                        ->where('student_id', $ledgerStudent->id)
                        ->when($ledgerStudentBatchId, fn ($q) => $q->where('batch_id', $ledgerStudentBatchId))
                        ->whereIn('status', ['open', 'partial'])
                        ->selectRaw('COALESCE(SUM(total - paid_total),0) as dues')
                        ->value('dues'),
                ];
            }
        }

        $batchOutstanding = Batch::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'default_fee'])
            ->map(function (Batch $batch) {
                $studentIds = Enrolment::query()
                    ->where('batch_id', $batch->id)
                    ->where('status', 'active')
                    ->pluck('student_id');

                $pending = (float) Invoice::query()
                    ->whereIn('status', ['open', 'partial'])
                    ->where(function ($q) use ($batch, $studentIds) {
                        $q->where('batch_id', $batch->id)
                            ->orWhere(function ($fallback) use ($studentIds) {
                                $fallback->whereNull('batch_id')->whereIn('student_id', $studentIds);
                            });
                    })
                    ->selectRaw('COALESCE(SUM(total - paid_total),0) as dues')
                    ->value('dues');

                return [
                    'id' => $batch->id,
                    'name' => $batch->name,
                    'students' => $studentIds->count(),
                    'pending' => $pending,
                ];
            })
            ->values();

        $batchLedger = null;
        if ($ledgerBatchId) {
            $batch = Batch::query()->find($ledgerBatchId);
            if ($batch) {
                $studentIds = Enrolment::query()
                    ->where('batch_id', $batch->id)
                    ->where('status', 'active')
                    ->pluck('student_id');

                $rows = Student::query()
                    ->whereIn('id', $studentIds)
                    ->orderBy('first_name')
                    ->get(['id', 'first_name', 'last_name', 'admission_no'])
                    ->map(function (Student $student) use ($batch) {
                        $invoices = Invoice::query()
                            ->where('student_id', $student->id)
                            ->where(function ($q) use ($batch) {
                                $q->where('batch_id', $batch->id)->orWhereNull('batch_id');
                            })
                            ->get();

                        $open = $invoices->whereIn('status', ['open', 'partial']);
                        $pending = round($open->sum(fn (Invoice $i) => $i->balance()), 2);
                        $billed = round($invoices->sum(fn (Invoice $i) => (float) $i->total), 2);
                        $paid = round($invoices->sum(fn (Invoice $i) => (float) $i->paid_total), 2);

                        $enrolment = Enrolment::query()
                            ->where('student_id', $student->id)
                            ->where('batch_id', $batch->id)
                            ->where('status', 'active')
                            ->first();

                        // Pending ₹0 is NOT "paid" if no bill was ever raised.
                        if ($invoices->isEmpty()) {
                            $status = 'not_billed';
                        } elseif ($pending <= 0) {
                            $status = 'paid';
                        } elseif ($open->contains(fn (Invoice $i) => $i->displayStatus() === 'overdue')) {
                            $status = 'overdue';
                        } elseif ($paid > 0) {
                            $status = 'partial';
                        } else {
                            $status = 'due';
                        }

                        return [
                            'id' => $student->id,
                            'name' => trim($student->first_name.' '.($student->last_name ?? '')),
                            'admission_no' => $student->admission_no,
                            'fee_style' => $enrolment?->fee_style ?: '—',
                            'fee_amount' => $enrolment?->fee_amount !== null
                                ? (float) $enrolment->fee_amount
                                : ($batch->default_fee !== null ? (float) $batch->default_fee : null),
                            'billed' => $billed,
                            'paid' => $paid,
                            'pending' => $pending,
                            'status' => $status,
                        ];
                    });

                $batchLedger = [
                    'batch_id' => $batch->id,
                    'batch_name' => $batch->name,
                    'rows' => $rows,
                ];
            }
        }

        return Inertia::render('Fees/Index', [
            'currentBillingPeriod' => now()->format('Y-m'),
            'openInvoices' => Invoice::query()
                ->with(['student:id,first_name,last_name,admission_no', 'batch:id,name'])
                ->whereIn('status', ['open', 'partial'])
                ->latest()
                ->limit(100)
                ->get()
                ->map($mapInvoice)
                ->values(),
            'recentInvoices' => Invoice::query()
                ->with(['student:id,first_name,last_name,admission_no', 'batch:id,name'])
                ->latest()
                ->limit(8)
                ->get()
                ->map($mapInvoice)
                ->values(),
            'recentReceipts' => Receipt::query()
                ->with('student:id,first_name,last_name,admission_no')
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (Receipt $receipt) => [
                    'id' => $receipt->id,
                    'receipt_no' => $receipt->receipt_no,
                    'amount' => (float) $receipt->amount,
                    'issued_on' => $receipt->issued_on?->toDateString(),
                    'student' => $receipt->student,
                ]),
            'students' => $students,
            'ledger' => $ledger,
            'ledgerStudentId' => $ledgerStudentId,
            'batchOutstanding' => $batchOutstanding,
            'batchLedger' => $batchLedger,
            'ledgerBatchId' => $ledgerBatchId,
            'gatewayStatus' => [
                'is_active' => (bool) ($gateway?->is_active),
                'connected' => filled($gateway?->key_id),
            ],
        ]);
    }

    public function storeInvoice(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'batch_id' => ['nullable', 'exists:batches,id'],
            'plan_type' => ['required', 'in:monthly,term,installments,custom'],
            'installments' => ['nullable', 'integer', 'min:1', 'max:12'],
            'total' => ['required', 'numeric', 'min:1'],
            'discount_total' => ['nullable', 'numeric', 'min:0'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $student = Student::query()->with([
            'enrolments' => fn ($q) => $q->where('status', 'active'),
        ])->findOrFail($data['student_id']);

        if (! empty($data['batch_id'])) {
            $ownsBatch = $student->enrolments->contains('batch_id', (int) $data['batch_id']);
            if (! $ownsBatch) {
                return back()->withErrors(['batch_id' => 'Selected batch is not linked to this student.']);
            }
        } else {
            $data['batch_id'] = $student->enrolments->first()?->batch_id;
        }

        if (($data['plan_type'] ?? '') !== 'installments') {
            $data['installments'] = 1;
        } else {
            $data['installments'] = max(2, (int) ($data['installments'] ?? 2));
        }

        $invoices = $this->billing->createInvoices($student, $data);
        $count = count($invoices);

        return back()->with('success', $count > 1
            ? "Created {$count} instalment invoices."
            : 'Invoice created.');
    }

    public function storePayment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'batch_id' => ['nullable', 'exists:batches,id'],
            'billing_period' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
            'allow_back_due' => ['nullable', 'boolean'],
            'back_due_note' => ['nullable', 'string', 'max:200'],
            'invoice_id' => ['nullable', 'exists:invoices,id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'mode' => ['required', 'in:cash,upi,bank,razorpay'],
            'reference' => ['nullable', 'string', 'max:100'],
            'paid_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $student = Student::query()->findOrFail($data['student_id']);
        $result = $this->billing->recordManualPayment($student, $data);

        $receipt = $result['receipt'] ?? null;
        $receiptNo = $receipt?->receipt_no;

        return back()
            ->with('success', $receiptNo
                ? "Payment recorded. Receipt {$receiptNo} issued."
                : 'Payment recorded and receipt issued.')
            ->with('print_receipt_id', $receipt?->id);
    }

    public function showReceipt(Receipt $receipt): View
    {
        abort_unless($receipt->tenant_id === auth()->user()?->tenant_id, 404);

        $receipt->load([
            'student',
            'payment.allocations.invoice.batch',
        ]);

        $tenant = auth()->user()?->tenant;

        return view('fees.receipt', [
            'receipt' => $receipt,
            'tenant' => $tenant,
            'payment' => $receipt->payment,
            'student' => $receipt->student,
            'allocations' => $receipt->payment?->allocations ?? collect(),
        ]);
    }

    public function generateBatchDues(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'batch_id' => ['required', 'exists:batches,id'],
            'billing_period' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $batch = Batch::query()->findOrFail($data['batch_id']);
        $period = $data['billing_period'] ?? now()->format('Y-m');
        $result = $this->billing->generateBatchMonthlyDues($batch, $period);

        return back()->with('success', sprintf(
            'Generated %d monthly bills for %s (%s). Skipped %d (already billed or not monthly).',
            $result['created'],
            $batch->name,
            $result['period_label'],
            $result['skipped'],
        ));
    }

    public function createRazorpayOrder(Request $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'invoice_id' => ['required', 'exists:invoices,id'],
        ]);

        $invoice = Invoice::query()->findOrFail($data['invoice_id']);
        $gateway = $this->gateways->forCurrentTenant();
        $this->gateways->assertMatchesInvoice($gateway, $invoice);

        $duePaise = (int) round(max(0, (float) $invoice->total - (float) $invoice->paid_total) * 100);
        $order = $this->razorpay->createOrder($gateway, $invoice, $duePaise);

        return response()->json([
            'order' => $order,
            'key_id' => $gateway->key_id ?: 'rzp_test_demo',
            'invoice' => $invoice,
        ]);
    }
}
