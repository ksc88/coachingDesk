<?php

namespace App\Http\Controllers\App;

use App\Domain\Billing\BillingService;
use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Batch;
use App\Models\Enrolment;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString() ?: 'active';

        $students = Student::query()
            ->with([
                'enrolments' => fn ($query) => $query->where('status', 'active')->with('batch:id,name,default_fee'),
                'guardians',
            ])
            ->when($status === 'active', fn ($q) => $q->where('status', 'active'))
            ->when($status === 'left', fn ($q) => $q->where('status', 'left'))
            ->when($status === 'all', fn ($q) => $q->whereIn('status', ['active', 'left', 'inactive']))
            ->when($request->search, function ($q, $search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('admission_no', 'like', "%{$search}%")
                        ->orWhere('school_name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($request->class_level, fn ($q, $class) => $q->where('class_level', $class))
            ->when($request->batch_id, function ($q, $batchId) {
                if ($batchId === 'unassigned') {
                    $q->whereDoesntHave('enrolments', fn ($enrolments) => $enrolments->where('status', 'active'));

                    return;
                }

                $q->whereHas('enrolments', fn ($enrolments) => $enrolments
                    ->where('status', 'active')
                    ->where('batch_id', $batchId));
            })
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(function (Student $student) {
                $student->setAttribute(
                    'can_delete',
                    ! Invoice::query()->where('student_id', $student->id)->exists()
                    && ! AttendanceRecord::query()->where('student_id', $student->id)->exists()
                );

                return $student;
            });

        return Inertia::render('Students/Index', [
            'students' => $students,
            'filters' => [
                'search' => $request->search,
                'class_level' => $request->class_level,
                'batch_id' => $request->batch_id,
                'status' => $status,
            ],
            'batches' => Batch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'default_fee']),
            'classLevels' => Student::query()
                ->whereNotNull('class_level')
                ->distinct()
                ->orderBy('class_level')
                ->pluck('class_level'),
            'nextAdmissionNo' => $this->nextAdmissionNo(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'admission_no' => [
                'nullable', 'string', 'max:50',
                Rule::unique('students', 'admission_no')->where('tenant_id', $request->user()->tenant_id),
            ],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'class_level' => ['nullable', 'string', 'max:32'],
            'school_name' => ['nullable', 'string', 'max:191'],
            'target_exam_year' => ['nullable', 'string', 'max:16'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'in:male,female,other'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email'],
            'address' => ['nullable', 'string', 'max:500'],
            'source' => ['nullable', 'string', 'max:64'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'joined_on' => ['nullable', 'date'],
            'batch_id' => ['nullable', 'exists:batches,id'],
            'fee_style' => ['nullable', 'in:monthly,term,installments,custom'],
            'fee_amount' => ['nullable', 'numeric', 'min:0'],
            'fee_installments' => ['nullable', 'integer', 'min:2', 'max:12'],
            'fee_due_day' => ['nullable', 'integer', 'min:1', 'max:28'],
            'fee_first_due_date' => ['nullable', 'date'],
            'admission_fee' => ['nullable', 'numeric', 'min:0'],
            'raise_first_invoice' => ['sometimes', 'boolean'],
            'guardian_name' => ['nullable', 'string', 'max:100'],
            'guardian_relation' => ['nullable', 'in:father,mother,guardian'],
            'guardian_occupation' => ['nullable', 'string', 'max:100'],
            'guardian_phone' => ['nullable', 'string', 'max:20'],
            'guardian_alternate_phone' => ['nullable', 'string', 'max:20'],
            'guardian_email' => ['nullable', 'email'],
            'whatsapp_opt_in' => ['boolean'],
            'sms_opt_in' => ['boolean'],
        ]);

        $student = Student::query()->create([
            'admission_no' => ($data['admission_no'] ?? null) ?: $this->nextAdmissionNo(),
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'] ?? null,
            'class_level' => $data['class_level'] ?? null,
            'school_name' => $data['school_name'] ?? null,
            'target_exam_year' => $data['target_exam_year'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'gender' => $data['gender'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            'source' => $data['source'] ?? null,
            'remarks' => $data['remarks'] ?? null,
            'status' => 'active',
            'joined_on' => $data['joined_on'] ?? now()->toDateString(),
            'tenant_id' => $request->user()->tenant_id,
            'branch_id' => $request->user()->branch_id,
        ]);

        if (! empty($data['guardian_name']) && ! empty($data['guardian_phone'])) {
            $guardian = Guardian::query()->create([
                'tenant_id' => $request->user()->tenant_id,
                'name' => $data['guardian_name'],
                'relation' => $data['guardian_relation'] ?? 'guardian',
                'occupation' => $data['guardian_occupation'] ?? null,
                'phone' => $data['guardian_phone'],
                'alternate_phone' => $data['guardian_alternate_phone'] ?? null,
                'email' => $data['guardian_email'] ?? null,
                'whatsapp_opt_in' => $request->boolean('whatsapp_opt_in'),
                'sms_opt_in' => $request->boolean('sms_opt_in'),
                'email_opt_in' => filled($data['guardian_email'] ?? null),
                'consent_at' => now(),
            ]);
            $student->guardians()->attach($guardian->id, ['is_primary' => true]);
        }

        $message = "Admitted {$student->first_name} as {$student->admission_no}.";

        if (! empty($data['batch_id'])) {
            $batch = Batch::query()->find($data['batch_id']);
            $feeStyle = $data['fee_style'] ?? 'monthly';
            $feeAmount = array_key_exists('fee_amount', $data) && $data['fee_amount'] !== null && $data['fee_amount'] !== ''
                ? (float) $data['fee_amount']
                : ($batch?->default_fee !== null ? (float) $batch->default_fee : null);
            $feeInstallments = $feeStyle === 'installments'
                ? max(2, (int) ($data['fee_installments'] ?? 3))
                : null;
            $enrolledOn = $data['joined_on'] ?? now()->toDateString();
            $billing = app(BillingService::class);
            $schedule = $this->feeScheduleFields($data, $enrolledOn);

            Enrolment::query()->create([
                'tenant_id' => $request->user()->tenant_id,
                'student_id' => $student->id,
                'batch_id' => $data['batch_id'],
                'enrolled_on' => $enrolledOn,
                'status' => 'active',
                'fee_style' => $feeStyle,
                'fee_amount' => $feeAmount,
                'fee_installments' => $feeInstallments,
                'fee_due_day' => $schedule['fee_due_day'],
                'fee_first_due_date' => $schedule['fee_first_due_date'],
            ]);

            $firstDue = $schedule['fee_first_due_date'] ?? $enrolledOn;

            $billsRaised = 0;

            if ($request->filled('admission_fee') && (float) $data['admission_fee'] > 0) {
                $billing->createInvoices($student, [
                    'batch_id' => $data['batch_id'],
                    'plan_type' => 'custom',
                    'total' => (float) $data['admission_fee'],
                    'discount_total' => 0,
                    'due_date' => $enrolledOn,
                    'notes' => 'Admission fee',
                ]);
                $billsRaised++;
                $message .= ' Admission fee bill raised.';
            }

            if ($request->boolean('raise_first_invoice') && $feeAmount !== null && $feeAmount > 0) {
                if ($feeStyle === 'monthly') {
                    $periodYm = \Carbon\Carbon::parse($enrolledOn)->format('Y-m');
                    $billing->ensureMonthlyInvoice($student, (int) $data['batch_id'], $periodYm);
                    $billsRaised++;
                    $message .= ' First month tuition bill raised.';
                } else {
                    $invoices = $billing->createInvoices($student, [
                        'batch_id' => $data['batch_id'],
                        'plan_type' => $feeStyle,
                        'installments' => $feeInstallments ?? 1,
                        'total' => $feeAmount,
                        'discount_total' => 0,
                        'due_date' => $firstDue,
                        'notes' => 'First bill on admission',
                    ]);
                    $billsRaised += count($invoices);
                    $message .= count($invoices) > 1
                        ? ' Raised '.count($invoices).' instalment invoices.'
                        : ' First fee bill raised.';
                }
            }

            if ($billsRaised > 0) {
                $message .= ' Collect payment on Fees page.';
            }
        }

        return back()->with('success', $message);
    }

    public function update(Request $request, Student $student): RedirectResponse
    {
        $data = $request->validate([
            'admission_no' => [
                'nullable', 'string', 'max:50',
                Rule::unique('students', 'admission_no')
                    ->where('tenant_id', $request->user()->tenant_id)
                    ->ignore($student->id),
            ],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'class_level' => ['nullable', 'string', 'max:32'],
            'school_name' => ['nullable', 'string', 'max:191'],
            'target_exam_year' => ['nullable', 'string', 'max:16'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'in:male,female,other'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email'],
            'address' => ['nullable', 'string', 'max:500'],
            'source' => ['nullable', 'string', 'max:64'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'joined_on' => ['nullable', 'date'],
            'fee_style' => ['nullable', 'in:monthly,term,installments,custom'],
            'fee_amount' => ['nullable', 'numeric', 'min:0'],
            'fee_installments' => ['nullable', 'integer', 'min:2', 'max:12'],
            'fee_due_day' => ['nullable', 'integer', 'min:1', 'max:28'],
            'fee_first_due_date' => ['nullable', 'date'],
            'guardian_name' => ['nullable', 'string', 'max:100'],
            'guardian_relation' => ['nullable', 'in:father,mother,guardian'],
            'guardian_occupation' => ['nullable', 'string', 'max:100'],
            'guardian_phone' => ['nullable', 'string', 'max:20'],
            'guardian_alternate_phone' => ['nullable', 'string', 'max:20'],
            'guardian_email' => ['nullable', 'email'],
            'whatsapp_opt_in' => ['boolean'],
            'sms_opt_in' => ['boolean'],
        ]);

        $student->update([
            'admission_no' => ($data['admission_no'] ?? null) ?: $student->admission_no,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'] ?? null,
            'class_level' => $data['class_level'] ?? null,
            'school_name' => $data['school_name'] ?? null,
            'target_exam_year' => $data['target_exam_year'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'gender' => $data['gender'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            'source' => $data['source'] ?? null,
            'remarks' => $data['remarks'] ?? null,
            'joined_on' => $data['joined_on'] ?? $student->joined_on,
        ]);

        $primary = $student->guardians()->wherePivot('is_primary', true)->first()
            ?? $student->guardians()->first();

        $guardianName = trim((string) ($data['guardian_name'] ?? ''));
        $guardianPhone = trim((string) ($data['guardian_phone'] ?? ''));
        $guardianEmail = trim((string) ($data['guardian_email'] ?? ''));
        $touchedGuardian = $guardianName !== '' || $guardianPhone !== '' || $guardianEmail !== ''
            || $request->exists('whatsapp_opt_in') || $request->exists('sms_opt_in');

        if ($primary && $touchedGuardian) {
            $name = $guardianName !== '' ? $guardianName : (string) $primary->name;
            $phone = $guardianPhone !== '' ? $guardianPhone : (string) $primary->phone;

            if ($name === '' || $phone === '') {
                return back()->withErrors([
                    'guardian_name' => 'Guardian name and phone are required to keep parent alerts.',
                    'guardian_phone' => 'Guardian phone is required.',
                ]);
            }

            $primary->update([
                'name' => $name,
                'relation' => $data['guardian_relation'] ?? $primary->relation ?? 'guardian',
                'occupation' => array_key_exists('guardian_occupation', $data)
                    ? ($data['guardian_occupation'] ?: null)
                    : $primary->occupation,
                'phone' => $phone,
                'alternate_phone' => array_key_exists('guardian_alternate_phone', $data)
                    ? ($data['guardian_alternate_phone'] ?: null)
                    : $primary->alternate_phone,
                'email' => $guardianEmail !== '' ? $guardianEmail : null,
                'whatsapp_opt_in' => $request->boolean('whatsapp_opt_in'),
                'sms_opt_in' => $request->boolean('sms_opt_in'),
                'email_opt_in' => $guardianEmail !== '',
            ]);
        } elseif (! $primary && ($guardianName !== '' || $guardianPhone !== '' || $guardianEmail !== '')) {
            if ($guardianName === '' || $guardianPhone === '') {
                return back()->withErrors([
                    'guardian_name' => 'Enter guardian name and phone to save email / alerts.',
                    'guardian_phone' => 'Phone is required with guardian name.',
                ]);
            }

            $guardian = Guardian::query()->create([
                'tenant_id' => $student->tenant_id,
                'name' => $guardianName,
                'relation' => $data['guardian_relation'] ?? 'guardian',
                'occupation' => $data['guardian_occupation'] ?? null,
                'phone' => $guardianPhone,
                'alternate_phone' => $data['guardian_alternate_phone'] ?? null,
                'email' => $guardianEmail !== '' ? $guardianEmail : null,
                'whatsapp_opt_in' => $request->boolean('whatsapp_opt_in'),
                'sms_opt_in' => $request->boolean('sms_opt_in'),
                'email_opt_in' => $guardianEmail !== '',
                'consent_at' => now(),
            ]);
            $student->guardians()->attach($guardian->id, ['is_primary' => true]);
        }

        $enrolment = Enrolment::query()
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->orderBy('id')
            ->first();

        if ($enrolment && ($data['fee_style'] ?? null)) {
            $feeStyle = $data['fee_style'];
            $enrolledOn = ($data['joined_on'] ?? $enrolment->enrolled_on?->toDateString()) ?: now()->toDateString();
            $schedule = $this->feeScheduleFields($data, $enrolledOn);
            $enrolment->update([
                'fee_style' => $feeStyle,
                'fee_amount' => array_key_exists('fee_amount', $data) ? $data['fee_amount'] : $enrolment->fee_amount,
                'fee_installments' => $feeStyle === 'installments'
                    ? max(2, (int) ($data['fee_installments'] ?? $enrolment->fee_installments ?? 3))
                    : null,
                'fee_due_day' => $schedule['fee_due_day'],
                'fee_first_due_date' => $schedule['fee_first_due_date'],
            ]);
        }

        return back()->with('success', "Updated {$student->first_name}.");
    }

    public function updateStatus(Request $request, Student $student): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:active,left'],
        ]);

        if ($data['status'] === 'left') {
            Enrolment::query()
                ->where('student_id', $student->id)
                ->where('status', 'active')
                ->get()
                ->each(fn (Enrolment $enrolment) => $this->closeEnrolment($enrolment, 'left'));

            $student->update(['status' => 'left']);

            return back()->with('success', "{$student->first_name} marked as left. Removed from active batches; history kept.");
        }

        $student->update(['status' => 'active']);

        return back()->with('success', "{$student->first_name} reactivated. Assign a batch if needed.");
    }

    public function destroy(Student $student): RedirectResponse
    {
        $hasFees = Invoice::query()->where('student_id', $student->id)->exists();
        $hasAttendance = AttendanceRecord::query()->where('student_id', $student->id)->exists();

        if ($hasFees || $hasAttendance) {
            return back()->withErrors([
                'student' => "Cannot delete {$student->first_name}: fees or attendance history exists. Mark as Left instead.",
            ]);
        }

        Enrolment::query()
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->get()
            ->each(fn (Enrolment $enrolment) => $this->closeEnrolment($enrolment, 'left'));

        $student->delete(); // soft delete

        return back()->with('success', "{$student->first_name} removed from the active list.");
    }

    public function bulkEnrol(Request $request): RedirectResponse
    {
        $tenantId = (int) $request->user()->tenant_id;

        $data = $request->validate([
            'student_ids' => ['required', 'array', 'min:1', 'max:200'],
            'student_ids.*' => [
                'integer',
                Rule::exists('students', 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')),
            ],
            'batch_id' => [
                'required',
                Rule::exists('batches', 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $tenantId)
                    ->where('is_active', true)),
            ],
            // "add" keeps other batches (subject-wise coaching); "move" replaces them (one-batch coaching).
            'mode' => ['nullable', 'in:add,move'],
        ]);

        // Single-batch coachings can never hold a student in two batches,
        // whatever the form sent.
        $mode = $request->user()->tenant?->usesSingleBatch()
            ? 'move'
            : ($data['mode'] ?? 'add');
        $added = 0;
        $alreadyEnrolled = 0;
        $removed = 0;

        foreach (array_unique($data['student_ids']) as $studentId) {
            if ($mode === 'move') {
                $others = Enrolment::query()
                    ->where('tenant_id', $tenantId)
                    ->where('student_id', $studentId)
                    ->where('status', 'active')
                    ->where('batch_id', '!=', $data['batch_id'])
                    ->get();

                foreach ($others as $other) {
                    $this->closeEnrolment($other, 'transferred');
                    $removed++;
                }
            }

            $enrolment = Enrolment::query()->firstOrCreate([
                'tenant_id' => $tenantId,
                'student_id' => $studentId,
                'batch_id' => $data['batch_id'],
                'status' => 'active',
            ], [
                'enrolled_on' => now()->toDateString(),
            ]);

            $enrolment->wasRecentlyCreated ? $added++ : $alreadyEnrolled++;
        }

        $message = $mode === 'move'
            ? "Moved {$added} student(s) into the batch."
            : "Added {$added} student(s) to the batch.";

        if ($alreadyEnrolled > 0) {
            $message .= " {$alreadyEnrolled} already in this batch.";
        }

        if ($removed > 0) {
            $message .= " Removed from {$removed} other batch(es).";
        }

        return back()->with('success', $message);
    }

    public function unenrol(Request $request, Student $student, Batch $batch): RedirectResponse
    {
        $tenantId = (int) $request->user()->tenant_id;

        abort_unless($student->tenant_id === $tenantId && $batch->tenant_id === $tenantId, 404);

        $enrolment = Enrolment::query()
            ->where('tenant_id', $tenantId)
            ->where('student_id', $student->id)
            ->where('batch_id', $batch->id)
            ->where('status', 'active')
            ->first();

        if (! $enrolment) {
            return back()->with('error', $student->first_name.' is not in '.$batch->name.'.');
        }

        $this->closeEnrolment($enrolment, 'left');

        return back()->with('success', "Removed {$student->first_name} from {$batch->name}.");
    }

    /**
     * Due schedule captured at admission — used when generating bills.
     *
     * @return array{fee_due_day: int|null, fee_first_due_date: string|null}
     */
    protected function feeScheduleFields(array $data, string $enrolledOn): array
    {
        $feeStyle = $data['fee_style'] ?? 'monthly';
        $billing = app(BillingService::class);

        if ($feeStyle === 'monthly') {
            return [
                'fee_due_day' => $billing->normalizeDueDay(
                    isset($data['fee_due_day']) && $data['fee_due_day'] !== '' && $data['fee_due_day'] !== null
                        ? (int) $data['fee_due_day']
                        : null
                ),
                'fee_first_due_date' => null,
            ];
        }

        if (in_array($feeStyle, ['term', 'installments', 'custom'], true)) {
            return [
                'fee_due_day' => null,
                'fee_first_due_date' => $data['fee_first_due_date'] ?? $enrolledOn,
            ];
        }

        return ['fee_due_day' => null, 'fee_first_due_date' => null];
    }

    /**
     * Keep enrolment history instead of deleting, while respecting the
     * unique (student, batch, status) constraint.
     */
    protected function closeEnrolment(Enrolment $enrolment, string $status): void
    {
        Enrolment::query()
            ->where('tenant_id', $enrolment->tenant_id)
            ->where('student_id', $enrolment->student_id)
            ->where('batch_id', $enrolment->batch_id)
            ->where('status', $status)
            ->whereKeyNot($enrolment->id)
            ->delete();

        $enrolment->update([
            'status' => $status,
            'left_on' => now()->toDateString(),
        ]);
    }

    /**
     * Admission numbers run as {academic start year}-{sequence}, e.g. 2026-0007.
     */
    protected function nextAdmissionNo(): string
    {
        $prefix = (int) now()->format('n') >= 4 ? now()->year : now()->year - 1;

        $last = Student::query()
            ->withTrashed()
            ->where('admission_no', 'like', $prefix.'-%')
            ->orderByDesc('admission_no')
            ->value('admission_no');

        $next = $last ? ((int) substr((string) $last, strlen((string) $prefix) + 1)) + 1 : 1;

        return sprintf('%s-%04d', $prefix, $next);
    }
}
