<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Enquiry;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $today = today()->toDateString();

        $openQuery = Invoice::query()->whereIn('status', ['open', 'partial']);

        $duesAmount = (float) (clone $openQuery)
            ->selectRaw('COALESCE(SUM(total - paid_total),0) as dues')
            ->value('dues');

        $dueToday = Invoice::query()
            ->whereIn('status', ['open', 'partial'])
            ->whereDate('due_date', $today)
            ->get();

        $overdue = Invoice::query()
            ->whereIn('status', ['open', 'partial'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today)
            ->get();

        $partials = Invoice::query()->where('status', 'partial')->count();

        $collectedToday = (float) Payment::query()
            ->where('status', 'completed')
            ->whereDate('paid_on', $today)
            ->sum('amount');

        return Inertia::render('Dashboard', [
            'stats' => [
                'students' => Student::query()->where('status', 'active')->count(),
                'open_invoices' => (clone $openQuery)->count(),
                'dues_amount' => $duesAmount,
                'due_today_count' => $dueToday->count(),
                'due_today_amount' => round($dueToday->sum(fn (Invoice $i) => $i->balance()), 2),
                'overdue_count' => $overdue->count(),
                'overdue_amount' => round($overdue->sum(fn (Invoice $i) => $i->balance()), 2),
                'partial_count' => $partials,
                'collected_today' => $collectedToday,
                'enquiries_open' => Enquiry::query()->whereNotIn('status', ['admitted', 'lost'])->count(),
                'absent_today' => AttendanceRecord::query()
                    ->where('status', 'absent')
                    ->whereDate('marked_at', today())
                    ->count(),
            ],
        ]);
    }
}
