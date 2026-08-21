<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AttendanceRecord;
use App\Models\Invoice;
use App\Models\Note;
use App\Models\Receipt;
use App\Models\Student;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ParentPortalController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Parent linked via guardian phone/email matching user, or student user account.
        $students = Student::query()
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhereHas('guardians', function ($g) use ($user) {
                        $g->where('email', $user->email)
                            ->orWhere('phone', $user->phone);
                    });
            })
            ->with(['enrolments.batch'])
            ->get();

        $studentIds = $students->pluck('id');

        $invoices = Invoice::query()
            ->whereIn('student_id', $studentIds)
            ->latest()
            ->limit(20)
            ->get();

        $openInvoices = Invoice::query()
            ->whereIn('student_id', $studentIds)
            ->whereIn('status', ['open', 'partial'])
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->get();

        $totalDue = round($openInvoices->sum(fn (Invoice $i) => (float) $i->total), 2);
        $totalPaidOnOpen = round($openInvoices->sum(fn (Invoice $i) => (float) $i->paid_total), 2);
        $remaining = round($openInvoices->sum(fn (Invoice $i) => $i->balance()), 2);

        $lifetimePaid = round((float) Invoice::query()
            ->whereIn('student_id', $studentIds)
            ->sum('paid_total'), 2);

        $nextDue = $openInvoices
            ->filter(fn (Invoice $i) => $i->due_date !== null)
            ->sortBy(fn (Invoice $i) => $i->due_date->timestamp)
            ->first();

        return Inertia::render('Parent/Index', [
            'students' => $students,
            'attendance' => AttendanceRecord::query()
                ->with(['classSession.batch', 'classSession.subject'])
                ->whereIn('student_id', $studentIds)
                ->latest('marked_at')
                ->limit(30)
                ->get(),
            'feeSummary' => [
                'total_due' => $totalDue,
                'paid_on_dues' => $totalPaidOnOpen,
                'lifetime_paid' => $lifetimePaid,
                'remaining' => $remaining,
                'next_due_date' => optional($nextDue?->due_date)->toDateString(),
                'next_due_amount' => $nextDue ? $nextDue->balance() : 0,
            ],
            'invoices' => $invoices->map(fn (Invoice $i) => $i->toLedgerArray())->values(),
            'receipts' => Receipt::query()->whereIn('student_id', $studentIds)->latest()->limit(20)->get(),
            'announcements' => Announcement::query()->where('status', 'published')->latest('published_at')->limit(10)->get(),
            'notes' => Note::query()->where('is_published', true)->latest('published_at')->limit(10)->get(),
        ]);
    }
}
