<?php

namespace App\Http\Controllers\App;

use App\Domain\Attendance\AttendanceService;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\ClassSession;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    public function __construct(protected AttendanceService $attendance) {}

    public function index(Request $request): Response
    {
        $sessions = ClassSession::query()
            ->with(['batch', 'subject', 'teacher'])
            ->latest('session_date')
            ->paginate(20);

        return Inertia::render('Attendance/Index', [
            'sessions' => $sessions,
            'batches' => Batch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'subjects' => Subject::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function storeSession(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'batch_id' => ['required', 'exists:batches,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'session_date' => ['required', 'date'],
            'topic' => ['nullable', 'string', 'max:255'],
        ]);

        $existing = ClassSession::query()
            ->where('batch_id', $data['batch_id'])
            ->whereDate('session_date', $data['session_date'])
            ->when(
                filled($data['subject_id'] ?? null),
                fn ($q) => $q->where('subject_id', $data['subject_id']),
                fn ($q) => $q->whereNull('subject_id'),
            )
            ->where('status', 'scheduled')
            ->latest('id')
            ->first();

        if ($existing) {
            $this->attendance->ensureSheet($existing);

            return redirect()
                ->route('attendance.show', $existing)
                ->with('success', 'Open sheet already exists for this batch and date — continue marking here.');
        }

        $session = ClassSession::query()->create([
            ...$data,
            'tenant_id' => $request->user()->tenant_id,
            'teacher_id' => $request->user()->id,
            'status' => 'scheduled',
        ]);

        $this->attendance->ensureSheet($session);

        return redirect()->route('attendance.show', $session)->with('success', 'Attendance sheet ready.');
    }

    public function show(ClassSession $session): Response
    {
        $this->attendance->ensureSheet($session);

        $session->load(['batch', 'subject', 'attendanceRecords.student']);

        return Inertia::render('Attendance/Show', [
            'session' => $session,
        ]);
    }

    public function mark(Request $request, ClassSession $session): RedirectResponse
    {
        if ($session->status === 'completed') {
            return back()->with('error', 'This class is finalized and locked. Marks cannot be changed.');
        }

        $data = $request->validate([
            'marks' => ['required', 'array'],
            'marks.*.student_id' => ['required', 'integer'],
            'marks.*.status' => ['required', 'in:present,absent,late,leave,unmarked'],
            'marks.*.remark' => ['nullable', 'string'],
            'finalize' => ['boolean'],
            'notify_absent' => ['boolean'],
            'notify_present' => ['boolean'],
        ]);

        $this->attendance->markBulk(
            $session,
            $data['marks'],
            (bool) ($data['finalize'] ?? false),
            (bool) ($data['notify_absent'] ?? true),
            (bool) ($data['notify_present'] ?? false),
        );

        $pendingIds = \App\Models\NotificationOutbox::query()
            ->where('payload->class_session_id', $session->id)
            ->where('status', 'pending')
            ->pluck('id');

        foreach ($pendingIds as $id) {
            try {
                \App\Jobs\ProcessNotificationOutbox::dispatchSync($id);
            } catch (\Throwable) {
                // Outbox row keeps pending/failed; attendance save must still succeed.
            }
        }

        $queued = $pendingIds->count();
        $live = $request->user()->tenant->fresh()->alertsAreLive();

        $message = 'Attendance saved.';
        if ($queued > 0) {
            $message .= $live
                ? " {$queued} parent alert(s) sent — open Parent alerts to confirm delivery."
                : " {$queued} parent alert(s) logged in safe mode (not sent to parents).";
        }
        if (! empty($data['finalize'])) {
            $message .= ' Class is now locked.';
        }

        return back()->with('success', $message);
    }
}
