<?php

namespace App\Http\Controllers\App;

use App\Support\Format\IndiaDate;
use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Batch;
use App\Models\Enquiry;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public const REPORTS = [
        'fees' => [
            'title' => 'Fees & collections',
            'blurb' => 'Invoices and money collected in a period',
        ],
        'receipts' => [
            'title' => 'Receipts',
            'blurb' => 'Payment receipts issued to parents',
        ],
        'defaulters' => [
            'title' => 'Overdue defaulters',
            'blurb' => 'Unpaid bills past the due date',
        ],
        'pending_dues' => [
            'title' => 'Pending collection',
            'blurb' => 'All open and partial bills — including due today',
        ],
        'enquiries' => [
            'title' => 'Enquiry pipeline',
            'blurb' => 'Leads by status across your CRM',
        ],
        'attendance' => [
            'title' => 'Attendance',
            'blurb' => 'Who was absent, by batch — simple overview for your coaching',
        ],
        'students' => [
            'title' => 'Students & batches',
            'blurb' => 'Active students and batch strength',
        ],
    ];

    public function index(Request $request): Response
    {
        $report = $request->string('report')->toString();
        if ($report !== '' && ! array_key_exists($report, self::REPORTS)) {
            $report = '';
        }

        $filters = $this->resolveFilters($request);
        $payload = [
            'report' => $report ?: null,
            'catalog' => collect(self::REPORTS)->map(fn ($meta, $key) => [
                'key' => $key,
                'title' => $meta['title'],
                'blurb' => $meta['blurb'],
            ])->values(),
            'filters' => $filters,
            'exportQuery' => array_filter([
                'report' => $report ?: null,
                'period' => $filters['period'],
                'from' => $filters['from'],
                'to' => $filters['to'],
                'invoice_q' => $filters['invoice_q'] ?: null,
                'receipt_q' => $filters['receipt_q'] ?: null,
                'invoice_status' => $filters['invoice_status'] !== 'all' ? $filters['invoice_status'] : null,
            ]),
            'data' => null,
        ];

        if ($report !== '') {
            $payload['data'] = match ($report) {
                'fees' => $this->feesData($filters),
                'receipts' => $this->receiptsData($filters),
                'defaulters' => $this->defaultersData(),
                'pending_dues' => $this->pendingDuesData(),
                'enquiries' => $this->enquiriesData(),
                'attendance' => $this->attendanceData($filters),
                'students' => $this->studentsData(),
                default => null,
            };
        }

        return Inertia::render('Reports/Index', $payload);
    }

    public function exportDefaulters(): StreamedResponse
    {
        $filename = 'overdue-defaulters-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Invoice', 'Admission no', 'Student', 'Batch', 'Total', 'Paid', 'Due', 'Due Date', 'Status']);

            $this->overdueInvoiceQuery()
                ->with(['student', 'batch'])
                ->orderBy('due_date')
                ->chunk(100, function ($rows) use ($out) {
                    foreach ($rows as $invoice) {
                        fputcsv($out, $this->invoiceCsvRow($invoice));
                    }
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function exportPendingDues(): StreamedResponse
    {
        $filename = 'pending-dues-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Invoice', 'Admission no', 'Student', 'Batch', 'Total', 'Paid', 'Due', 'Due Date', 'Status']);

            Invoice::query()
                ->with(['student', 'batch'])
                ->whereIn('status', ['open', 'partial'])
                ->orderBy('due_date')
                ->chunk(100, function ($rows) use ($out) {
                    foreach ($rows as $invoice) {
                        fputcsv($out, $this->invoiceCsvRow($invoice));
                    }
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function exportInvoices(Request $request): StreamedResponse
    {
        $filters = $this->resolveFilters($request);
        $filename = 'invoices-'.$filters['from'].'_'.$filters['to'].'.csv';

        return response()->streamDownload(function () use ($filters) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Invoice', 'Student', 'Total', 'Paid', 'Due', 'Status', 'Note', 'Invoice Date', 'Due Date']);

            $this->invoiceQuery($filters)
                ->with('student')
                ->orderByDesc('id')
                ->chunk(200, function ($rows) use ($out) {
                    foreach ($rows as $invoice) {
                        $total = (float) $invoice->total;
                        $paid = (float) $invoice->paid_total;
                        fputcsv($out, [
                            $invoice->invoice_no,
                            $invoice->student?->full_name,
                            $total,
                            $paid,
                            max(0, $total - $paid),
                            $invoice->status,
                            $invoice->notes,
                            IndiaDate::format($invoice->invoice_date),
                            IndiaDate::format($invoice->due_date),
                        ]);
                    }
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function exportReceipts(Request $request): StreamedResponse
    {
        $filters = $this->resolveFilters($request);
        $filename = 'receipts-'.$filters['from'].'_'.$filters['to'].'.csv';

        return response()->streamDownload(function () use ($filters) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Receipt', 'Student', 'Amount', 'Date']);

            $this->receiptQuery($filters)
                ->with('student')
                ->orderByDesc('id')
                ->chunk(200, function ($rows) use ($out) {
                    foreach ($rows as $receipt) {
                        fputcsv($out, [
                            $receipt->receipt_no,
                            $receipt->student?->full_name,
                            $receipt->amount,
                            IndiaDate::format($receipt->issued_on),
                        ]);
                    }
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    protected function feesData(array $filters): array
    {
        $collections = Payment::query()
            ->selectRaw('mode, SUM(amount) as total')
            ->where('status', 'completed')
            ->when($filters['from'], fn ($q) => $q->whereDate('paid_on', '>=', $filters['from']))
            ->when($filters['to'], fn ($q) => $q->whereDate('paid_on', '<=', $filters['to']))
            ->groupBy('mode')
            ->pluck('total', 'mode');

        $invoices = $this->invoiceQuery($filters)
            ->with(['student', 'batch'])
            ->latest('invoice_date')
            ->latest('id')
            ->paginate(15, ['*'], 'page')
            ->withQueryString()
            ->through(fn (Invoice $invoice) => $this->mapInvoiceRow($invoice));

        return [
            'collections' => $collections,
            'invoices' => $invoices,
            'summary' => [
                'invoice_count' => $invoices->total(),
                'collected' => (float) $collections->sum(),
            ],
        ];
    }

    protected function receiptsData(array $filters): array
    {
        $receipts = $this->receiptQuery($filters)
            ->with('student')
            ->latest('issued_on')
            ->latest('id')
            ->paginate(15, ['*'], 'page')
            ->withQueryString();

        return [
            'receipts' => $receipts,
            'summary' => [
                'receipt_count' => $receipts->total(),
                'amount' => (float) $this->receiptQuery($filters)->sum('amount'),
            ],
        ];
    }

    protected function defaultersData(): array
    {
        $rows = $this->overdueInvoiceQuery()
            ->with(['student', 'batch'])
            ->orderBy('due_date')
            ->limit(100)
            ->get()
            ->map(fn (Invoice $invoice) => $this->mapInvoiceRow($invoice));

        return [
            'rows' => $rows,
            'summary' => [
                'count' => $rows->count(),
                'due_total' => (float) $rows->sum('due'),
            ],
        ];
    }

    protected function pendingDuesData(): array
    {
        $rows = Invoice::query()
            ->with(['student', 'batch'])
            ->whereIn('status', ['open', 'partial'])
            ->orderBy('due_date')
            ->limit(100)
            ->get()
            ->map(fn (Invoice $invoice) => $this->mapInvoiceRow($invoice));

        return [
            'rows' => $rows,
            'summary' => [
                'count' => $rows->count(),
                'due_total' => (float) $rows->sum('due'),
            ],
        ];
    }

    protected function overdueInvoiceQuery(): Builder
    {
        return Invoice::query()
            ->whereIn('status', ['open', 'partial'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', today());
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapInvoiceRow(Invoice $invoice): array
    {
        return [
            'invoice_no' => $invoice->invoice_no,
            'admission_no' => $invoice->student?->admission_no,
            'student' => $invoice->student?->full_name,
            'batch' => $invoice->batch?->name,
            'total' => (float) $invoice->total,
            'paid' => (float) $invoice->paid_total,
            'due' => $invoice->balance(),
            'due_date' => optional($invoice->due_date)->toDateString(),
            'status' => $invoice->status,
            'display_status' => $invoice->displayStatus(),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    protected function invoiceCsvRow(Invoice $invoice): array
    {
        return [
            $invoice->invoice_no,
            $invoice->student?->admission_no,
            $invoice->student?->full_name,
            $invoice->batch?->name,
            (float) $invoice->total,
            (float) $invoice->paid_total,
            $invoice->balance(),
            IndiaDate::format($invoice->due_date),
            $invoice->displayStatus(),
        ];
    }

    protected function enquiriesData(): array
    {
        $pipeline = Enquiry::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $labels = [
            'new' => 'New',
            'contacted' => 'Contacted',
            'interested' => 'Interested',
            'demo_scheduled' => 'Demo scheduled',
            'admitted' => 'Admitted',
            'lost' => 'Lost',
        ];

        return [
            'pipeline' => $pipeline,
            'labels' => $labels,
            'open' => (int) Enquiry::query()->whereIn('status', ['new', 'contacted', 'interested', 'demo_scheduled'])->count(),
            'total' => (int) Enquiry::query()->count(),
        ];
    }

    protected function attendanceData(array $filters): array
    {
        $inPeriod = fn ($q) => $q
            ->when($filters['from'], fn ($inner) => $inner->whereHas('classSession', fn ($s) => $s->whereDate('session_date', '>=', $filters['from'])))
            ->when($filters['to'], fn ($inner) => $inner->whereHas('classSession', fn ($s) => $s->whereDate('session_date', '<=', $filters['to'])));

        $byStatus = AttendanceRecord::query()
            ->tap($inPeriod)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $sessions = \App\Models\ClassSession::query()
            ->when($filters['from'], fn ($q) => $q->whereDate('session_date', '>=', $filters['from']))
            ->when($filters['to'], fn ($q) => $q->whereDate('session_date', '<=', $filters['to']))
            ->count();

        $byBatch = AttendanceRecord::query()
            ->tap($inPeriod)
            ->join('class_sessions', 'class_sessions.id', '=', 'attendance_records.class_session_id')
            ->join('batches', 'batches.id', '=', 'class_sessions.batch_id')
            ->selectRaw("batches.name as batch_name,
                SUM(CASE WHEN attendance_records.status = 'present' THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN attendance_records.status = 'absent' THEN 1 ELSE 0 END) as absent,
                SUM(CASE WHEN attendance_records.status = 'late' THEN 1 ELSE 0 END) as late,
                SUM(CASE WHEN attendance_records.status = 'leave' THEN 1 ELSE 0 END) as leave_count,
                COUNT(*) as total")
            ->groupBy('batches.id', 'batches.name')
            ->orderBy('batches.name')
            ->get()
            ->map(fn ($row) => [
                'batch' => $row->batch_name,
                'present' => (int) $row->present,
                'absent' => (int) $row->absent,
                'late' => (int) $row->late,
                'leave' => (int) $row->leave_count,
                'total' => (int) $row->total,
            ]);

        $recentAbsences = AttendanceRecord::query()
            ->tap($inPeriod)
            ->where('status', 'absent')
            ->with([
                'student:id,first_name,last_name,admission_no',
                'classSession:id,batch_id,subject_id,session_date,topic',
                'classSession.batch:id,name',
                'classSession.subject:id,name',
            ])
            ->latest('marked_at')
            ->limit(30)
            ->get()
            ->map(fn (AttendanceRecord $row) => [
                'date' => optional($row->classSession?->session_date)->format('d-m-Y'),
                'student' => trim(($row->student?->first_name ?? '').' '.($row->student?->last_name ?? '')),
                'admission_no' => $row->student?->admission_no,
                'batch' => $row->classSession?->batch?->name ?? '—',
                'subject' => $row->classSession?->subject?->name ?? '—',
                'topic' => $row->classSession?->topic ?: '—',
            ]);

        return [
            'by_status' => $byStatus,
            'total_marks' => (int) $byStatus->sum(),
            'sessions' => $sessions,
            'present' => (int) ($byStatus['present'] ?? 0),
            'absent' => (int) ($byStatus['absent'] ?? 0),
            'late' => (int) ($byStatus['late'] ?? 0),
            'leave' => (int) ($byStatus['leave'] ?? 0),
            'by_batch' => $byBatch,
            'recent_absences' => $recentAbsences,
        ];
    }

    protected function studentsData(): array
    {
        $active = Student::query()->where('status', 'active')->count();
        $left = Student::query()->where('status', 'left')->count();

        $byBatch = Batch::query()
            ->where('is_active', true)
            ->withCount([
                'enrolments as students_count' => fn ($q) => $q->where('status', 'active'),
            ])
            ->orderBy('name')
            ->get(['id', 'name', 'capacity'])
            ->map(fn (Batch $batch) => [
                'name' => $batch->name,
                'students' => (int) $batch->students_count,
                'capacity' => $batch->capacity,
            ]);

        $unassigned = Student::query()
            ->where('status', 'active')
            ->whereDoesntHave('enrolments', fn ($q) => $q->where('status', 'active'))
            ->count();

        return [
            'active' => $active,
            'left' => $left,
            'unassigned' => $unassigned,
            'batches' => $byBatch,
        ];
    }

    /**
     * @return array{period: string, from: string, to: string, invoice_q: string, receipt_q: string, invoice_status: string}
     */
    protected function resolveFilters(Request $request): array
    {
        $period = $request->string('period')->toString() ?: 'this_month';
        $today = now()->startOfDay();

        [$defaultFrom, $defaultTo] = match ($period) {
            'last_30' => [$today->copy()->subDays(29)->toDateString(), $today->toDateString()],
            'this_fy' => $this->financialYearBounds($today),
            'all' => ['2020-01-01', $today->toDateString()],
            default => [$today->copy()->startOfMonth()->toDateString(), $today->toDateString()],
        };

        $from = $request->filled('from') ? $request->date('from')->toDateString() : $defaultFrom;
        $to = $request->filled('to') ? $request->date('to')->toDateString() : $defaultTo;

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [
            'period' => $period,
            'from' => $from,
            'to' => $to,
            'invoice_q' => trim($request->string('invoice_q')->toString()),
            'receipt_q' => trim($request->string('receipt_q')->toString()),
            'invoice_status' => $request->string('invoice_status')->toString() ?: 'all',
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function financialYearBounds(Carbon $today): array
    {
        $year = (int) $today->format('Y');
        $month = (int) $today->format('n');
        $startYear = $month >= 4 ? $year : $year - 1;

        return [
            sprintf('%d-04-01', $startYear),
            $today->toDateString(),
        ];
    }

    protected function invoiceQuery(array $filters): Builder
    {
        return Invoice::query()
            ->when($filters['from'], fn ($q) => $q->whereDate('invoice_date', '>=', $filters['from']))
            ->when($filters['to'], fn ($q) => $q->whereDate('invoice_date', '<=', $filters['to']))
            ->when(
                in_array($filters['invoice_status'], ['open', 'partial', 'paid'], true),
                fn ($q) => $q->where('status', $filters['invoice_status'])
            )
            ->when($filters['invoice_q'] !== '', function ($q) use ($filters) {
                $search = $filters['invoice_q'];
                $q->where(function ($inner) use ($search) {
                    $inner->where('invoice_no', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%")
                        ->orWhereHas('student', function ($student) use ($search) {
                            $student->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('admission_no', 'like', "%{$search}%");
                        });
                });
            });
    }

    protected function receiptQuery(array $filters): Builder
    {
        return Receipt::query()
            ->when($filters['from'], fn ($q) => $q->whereDate('issued_on', '>=', $filters['from']))
            ->when($filters['to'], fn ($q) => $q->whereDate('issued_on', '<=', $filters['to']))
            ->when($filters['receipt_q'] !== '', function ($q) use ($filters) {
                $search = $filters['receipt_q'];
                $q->where(function ($inner) use ($search) {
                    $inner->where('receipt_no', 'like', "%{$search}%")
                        ->orWhereHas('student', function ($student) use ($search) {
                            $student->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('admission_no', 'like', "%{$search}%");
                        });
                });
            });
    }
}
