<?php

namespace App\Http\Controllers\App;

use App\Domain\Academics\AcademicSessionResolver;
use App\Domain\Academics\BatchScheduleFormatter;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Category;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\Enrolment;
use App\Models\Invoice;
use App\Models\StaffAssignment;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AcademicsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Batches/Index', [
            'categories' => Category::query()->orderBy('name')->get(),
            'branches' => Branch::query()->withCount('batches')->orderBy('name')->get()
                ->map(function (Branch $branch) {
                    $branch->setAttribute(
                        'can_delete',
                        (int) $branch->batches_count === 0
                        && Branch::query()->count() > 1
                    );

                    return $branch;
                }),
            'courses' => Course::query()->with('category')->withCount('batches')->orderBy('name')->get()
                ->map(function (Course $course) {
                    $course->setAttribute('can_delete', (int) $course->batches_count === 0);

                    return $course;
                }),
            'subjects' => Subject::query()->orderBy('name')->get()->map(function (Subject $subject) {
                $inUse = $subject->batches()->exists()
                    || StaffAssignment::query()->where('subject_id', $subject->id)->exists();
                $subject->setAttribute('can_delete', ! $inUse);

                return $subject;
            }),
            'batches' => Batch::query()
                ->with(['branch', 'course', 'subjects'])
                ->withCount([
                    'enrolments as students_count' => fn ($query) => $query->where('status', 'active'),
                    'enrolments as enrolments_count',
                ])
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get()
                ->map(function (Batch $batch) {
                    $batch->setAttribute(
                        'can_delete',
                        (int) $batch->students_count === 0
                        && (int) $batch->enrolments_count === 0
                        && ! ClassSession::query()->where('batch_id', $batch->id)->exists()
                        && ! Invoice::query()->where('batch_id', $batch->id)->exists()
                    );
                    $batch->setAttribute('can_deactivate', (bool) $batch->is_active);
                    $batch->setAttribute('can_activate', ! $batch->is_active);

                    return $batch;
                }),
        ]);
    }

    public function updateEnrolmentRule(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'single_batch_mode' => ['required', 'boolean'],
        ]);

        $tenant = $request->user()->tenant;
        $tenant->settings = array_merge($tenant->settings ?? [], [
            'single_batch_mode' => $data['single_batch_mode'],
        ]);
        $tenant->save();

        return back()->with('success', $data['single_batch_mode']
            ? 'Students will now be kept in one batch only.'
            : 'Students can now be enrolled in several batches.');
    }

    public function storeBranch(Request $request): RedirectResponse
    {
        $tenantId = (int) $request->user()->tenant_id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable', 'string', 'max:32',
                Rule::unique('branches', 'code')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
        ], [
            'code.unique' => 'This branch code is already used. Pick a different code (or leave it blank).',
        ]);

        $code = trim((string) ($data['code'] ?? ''));

        Branch::query()->create([
            'name' => $data['name'],
            'code' => $code !== '' ? $code : null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'tenant_id' => $tenantId,
            'is_active' => true,
        ]);

        return back()->with('success', 'Branch created.');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'description' => ['nullable', 'string'],
        ]);

        Category::query()->create([
            'tenant_id' => $request->user()->tenant_id,
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'description' => $data['description'] ?? null,
            'is_active' => true,
        ]);

        return back()->with('success', 'Category created.');
    }

    public function storeCourse(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'code' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
        ]);

        Course::query()->create([
            ...$data,
            'tenant_id' => $request->user()->tenant_id,
            'is_active' => true,
        ]);

        return back()->with('success', 'Course created.');
    }

    public function storeBatch(Request $request, AcademicSessionResolver $sessions, BatchScheduleFormatter $schedule): RedirectResponse
    {
        $tenantId = (int) $request->user()->tenant_id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'branch_id' => [
                'nullable',
                Rule::exists('branches', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'course_id' => [
                'nullable',
                Rule::exists('courses', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'weekdays' => ['nullable', 'array'],
            'weekdays.*' => ['integer', 'between:1,7'],
            'starts_at' => ['nullable', 'date_format:H:i'],
            'ends_at' => ['nullable', 'date_format:H:i'],
            'shift' => ['nullable', Rule::in(BatchScheduleFormatter::SHIFTS)],
            'timing' => ['nullable', 'string', 'max:100'],
            'default_fee' => ['nullable', 'numeric', 'min:0'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'subject_ids' => ['nullable', 'array'],
            'subject_ids.*' => [
                'integer',
                Rule::exists('subjects', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
        ]);

        if (! empty($data['starts_at']) && ! empty($data['ends_at']) && $data['ends_at'] <= $data['starts_at']) {
            return back()->withErrors(['ends_at' => 'End time must be after start time.'])->withInput();
        }

        $weekdays = collect($data['weekdays'] ?? [])
            ->map(fn ($day) => (int) $day)
            ->filter(fn (int $day) => $day >= 1 && $day <= 7)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $startsAt = $schedule->normalizeTime($data['starts_at'] ?? null);
        $endsAt = $schedule->normalizeTime($data['ends_at'] ?? null);
        $shift = ($data['shift'] ?? null) ?: null;

        // Prefer structured schedule; fall back to free-text note for flexible batches.
        $timing = $schedule->format($weekdays, $startsAt, $endsAt, $shift)
            ?? (trim((string) ($data['timing'] ?? '')) ?: null);

        $batch = Batch::query()->create([
            'name' => $data['name'],
            'branch_id' => $data['branch_id'] ?? null,
            'course_id' => $data['course_id'] ?? null,
            'weekdays' => $weekdays === [] ? null : $weekdays,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'shift' => $shift === 'custom' ? null : $shift,
            'timing' => $timing,
            'default_fee' => $data['default_fee'] ?? 0,
            'capacity' => $data['capacity'] ?? null,
            'tenant_id' => $tenantId,
            'academic_session_id' => $sessions->current($tenantId)->id,
            'is_active' => true,
        ]);

        $subjectIds = collect($data['subject_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values()->all();
        if ($subjectIds !== []) {
            $batch->subjects()->sync($subjectIds);
        }

        return back()->with('success', 'Batch created. Now open Students and assign students to this batch.');
    }

    public function storeSubject(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'code' => ['nullable', 'string'],
        ]);

        Subject::query()->create([
            ...$data,
            'tenant_id' => $request->user()->tenant_id,
        ]);

        return back()->with('success', 'Subject created.');
    }

    public function updateBatch(Request $request, Batch $batch, BatchScheduleFormatter $schedule): RedirectResponse
    {
        $tenantId = (int) $request->user()->tenant_id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'branch_id' => [
                'nullable',
                Rule::exists('branches', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'course_id' => [
                'nullable',
                Rule::exists('courses', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'weekdays' => ['nullable', 'array'],
            'weekdays.*' => ['integer', 'between:1,7'],
            'starts_at' => ['nullable', 'date_format:H:i'],
            'ends_at' => ['nullable', 'date_format:H:i'],
            'shift' => ['nullable', Rule::in(BatchScheduleFormatter::SHIFTS)],
            'timing' => ['nullable', 'string', 'max:100'],
            'default_fee' => ['nullable', 'numeric', 'min:0'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'subject_ids' => ['nullable', 'array'],
            'subject_ids.*' => [
                'integer',
                Rule::exists('subjects', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (! empty($data['starts_at']) && ! empty($data['ends_at']) && $data['ends_at'] <= $data['starts_at']) {
            return back()->withErrors(['ends_at' => 'End time must be after start time.'])->withInput();
        }

        $weekdays = collect($data['weekdays'] ?? [])
            ->map(fn ($day) => (int) $day)
            ->filter(fn (int $day) => $day >= 1 && $day <= 7)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $startsAt = $schedule->normalizeTime($data['starts_at'] ?? null);
        $endsAt = $schedule->normalizeTime($data['ends_at'] ?? null);
        $shift = ($data['shift'] ?? null) ?: null;
        $timing = $schedule->format($weekdays, $startsAt, $endsAt, $shift)
            ?? (trim((string) ($data['timing'] ?? '')) ?: null);

        $batch->update([
            'name' => $data['name'],
            'branch_id' => $data['branch_id'] ?? null,
            'course_id' => $data['course_id'] ?? null,
            'weekdays' => $weekdays === [] ? null : $weekdays,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'shift' => $shift === 'custom' ? null : $shift,
            'timing' => $timing,
            'default_fee' => $data['default_fee'] ?? $batch->default_fee,
            'capacity' => $data['capacity'] ?? null,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $batch->is_active,
        ]);

        $subjectIds = collect($data['subject_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values()->all();
        $batch->subjects()->sync($subjectIds);

        return back()->with('success', 'Batch updated.');
    }

    public function destroyBatch(Batch $batch): RedirectResponse
    {
        $activeStudents = Enrolment::query()
            ->where('batch_id', $batch->id)
            ->where('status', 'active')
            ->count();

        if ($activeStudents > 0) {
            return back()->withErrors([
                'batch' => "Cannot delete \"{$batch->name}\": {$activeStudents} student(s) still enrolled. Move or remove them first, or deactivate the batch.",
            ]);
        }

        $hasHistory = Enrolment::query()->where('batch_id', $batch->id)->exists()
            || ClassSession::query()->where('batch_id', $batch->id)->exists()
            || Invoice::query()->where('batch_id', $batch->id)->exists();

        if ($hasHistory) {
            $batch->update(['is_active' => false]);

            return back()->with('success', "\"{$batch->name}\" was deactivated (history kept). Permanent delete is blocked while attendance/fees/old enrolments exist.");
        }

        $batch->subjects()->detach();
        StaffAssignment::query()->where('batch_id', $batch->id)->delete();
        $batch->delete();

        return back()->with('success', 'Batch deleted.');
    }

    public function updateCourse(Request $request, Course $course): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'code' => ['nullable', 'string', 'max:32'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $course->update([
            'name' => $data['name'],
            'category_id' => $data['category_id'] ?? $course->category_id,
            'code' => $data['code'] ?? $course->code,
            'description' => $data['description'] ?? $course->description,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $course->is_active,
        ]);

        return back()->with('success', 'Course updated.');
    }

    public function destroyCourse(Course $course): RedirectResponse
    {
        if ($course->batches()->exists()) {
            return back()->withErrors([
                'course' => "Cannot delete \"{$course->name}\": batches still use this course. Edit those batches first.",
            ]);
        }

        $course->delete();

        return back()->with('success', 'Course deleted.');
    }

    public function updateSubject(Request $request, Subject $subject): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:32'],
        ]);

        $subject->update($data);

        return back()->with('success', 'Subject updated.');
    }

    public function destroySubject(Subject $subject): RedirectResponse
    {
        if ($subject->batches()->exists()) {
            return back()->withErrors([
                'subject' => "Cannot delete \"{$subject->name}\": it is taught in one or more batches.",
            ]);
        }

        if (StaffAssignment::query()->where('subject_id', $subject->id)->exists()) {
            return back()->withErrors([
                'subject' => "Cannot delete \"{$subject->name}\": a teacher is assigned to it.",
            ]);
        }

        $subject->delete();

        return back()->with('success', 'Subject deleted.');
    }

    public function updateBranch(Request $request, Branch $branch): RedirectResponse
    {
        $tenantId = (int) $request->user()->tenant_id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable', 'string', 'max:32',
                Rule::unique('branches', 'code')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId))
                    ->ignore($branch->id),
            ],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        $code = trim((string) ($data['code'] ?? ''));
        $branch->update([
            'name' => $data['name'],
            'code' => $code !== '' ? $code : null,
            'phone' => $data['phone'] ?? $branch->phone,
            'address' => $data['address'] ?? $branch->address,
        ]);

        return back()->with('success', 'Branch updated.');
    }

    public function destroyBranch(Branch $branch): RedirectResponse
    {
        if (Branch::query()->count() <= 1) {
            return back()->withErrors(['branch' => 'Cannot delete the only branch.']);
        }

        if ($branch->batches()->exists()) {
            return back()->withErrors([
                'branch' => "Cannot delete \"{$branch->name}\": batches still use this branch.",
            ]);
        }

        $branch->delete();

        return back()->with('success', 'Branch deleted.');
    }
}
