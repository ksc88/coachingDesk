<?php

namespace App\Domain\Attendance;

use App\Domain\Identity\AuditLogger;
use App\Domain\Messaging\NotificationOutboxService;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceRecord;
use App\Models\ClassSession;
use App\Models\Enrolment;
use App\Models\Student;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    public function __construct(
        protected NotificationOutboxService $notifications,
        protected AuditLogger $audit,
    ) {}

    public function ensureSheet(ClassSession $session): void
    {
        $studentIds = Enrolment::query()
            ->where('batch_id', $session->batch_id)
            ->where('status', 'active')
            ->pluck('student_id');

        foreach ($studentIds as $studentId) {
            AttendanceRecord::query()->firstOrCreate(
                [
                    'class_session_id' => $session->id,
                    'student_id' => $studentId,
                ],
                [
                    'tenant_id' => $session->tenant_id,
                    'status' => 'unmarked',
                ]
            );
        }
    }

    public function markBulk(ClassSession $session, array $marks, bool $finalize = false, bool $notifyAbsent = true, bool $notifyPresent = false): void
    {
        if ($session->status === 'completed' && ! $finalize) {
            return;
        }

        DB::transaction(function () use ($session, $marks, $finalize, $notifyAbsent, $notifyPresent) {
            $session = ClassSession::query()->lockForUpdate()->findOrFail($session->id);

            if ($session->status === 'completed') {
                return;
            }

            $this->ensureSheet($session);

            foreach ($marks as $mark) {
                /** @var AttendanceRecord $record */
                $record = AttendanceRecord::query()
                    ->where('class_session_id', $session->id)
                    ->where('student_id', $mark['student_id'])
                    ->firstOrFail();

                if ($record->is_locked) {
                    continue;
                }

                $from = $record->status;
                $to = $mark['status'];

                $record->update([
                    'status' => $to,
                    'remark' => $mark['remark'] ?? $record->remark,
                    'marked_by' => Auth::id(),
                    'marked_at' => now(),
                    'is_locked' => $finalize,
                ]);

                if ($from !== $to && $from !== 'unmarked') {
                    AttendanceCorrection::query()->create([
                        'tenant_id' => $session->tenant_id,
                        'attendance_record_id' => $record->id,
                        'from_status' => $from,
                        'to_status' => $to,
                        'reason' => $mark['reason'] ?? 'Bulk update',
                        'corrected_by' => Auth::id() ?? 0,
                    ]);
                }

                if ($to === 'absent' && $notifyAbsent && $from !== 'absent') {
                    $this->notify($session, $record->student_id, 'attendance.absent');
                }

                if ($to === 'present' && $notifyPresent && $from !== 'present') {
                    $this->notify($session, $record->student_id, 'attendance.present');
                }
            }

            if ($finalize) {
                $session->update(['status' => 'completed']);
                $this->audit->log('attendance.finalized', $session);
            }
        });
    }

    public function correct(AttendanceRecord $record, string $toStatus, string $reason): AttendanceRecord
    {
        return DB::transaction(function () use ($record, $toStatus, $reason) {
            $from = $record->status;

            AttendanceCorrection::query()->create([
                'tenant_id' => $record->tenant_id,
                'attendance_record_id' => $record->id,
                'from_status' => $from,
                'to_status' => $toStatus,
                'reason' => $reason,
                'corrected_by' => Auth::id(),
            ]);

            $record->update([
                'status' => $toStatus,
                'marked_by' => Auth::id(),
                'marked_at' => now(),
            ]);

            $this->audit->log('attendance.corrected', $record, ['status' => $from], ['status' => $toStatus, 'reason' => $reason]);

            return $record->fresh();
        });
    }

    protected function notify(ClassSession $session, int $studentId, string $templateKey): void
    {
        $student = Student::query()->with('guardians')->find($studentId);
        if (! $student) {
            return;
        }

        $session->loadMissing(['batch', 'subject']);

        $topic = trim((string) ($session->topic ?? ''));
        $subject = $session->subject?->name ?? 'Class';

        $this->notifications->enqueueForStudentGuardians($student, $templateKey, $templateKey, [
            'student_name' => trim($student->first_name.' '.$student->last_name),
            'batch_name' => $session->batch?->name ?? '',
            'date' => optional($session->session_date)->format('d M Y'),
            'subject' => $subject,
            'topic' => $topic,
            'topic_part' => $topic !== '' ? ' · '.$topic : '',
            'class_session_id' => $session->id,
        ]);
    }
}
