<?php

namespace App\Http\Controllers\App;

use App\Domain\Messaging\NotificationOutboxService;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessNotificationOutbox;
use App\Models\Announcement;
use App\Models\Batch;
use App\Models\Enrolment;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementController extends Controller
{
    public function __construct(protected NotificationOutboxService $notifications) {}

    public function index(): Response
    {
        return Inertia::render('Announcements/Index', [
            'announcements' => Announcement::query()->latest()->paginate(20),
            'batches' => Batch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string'],
            'scope' => ['required', 'in:organization,branch,batch,category'],
            'batch_id' => ['nullable', 'exists:batches,id'],
            'notify' => ['boolean'],
        ]);

        $announcement = Announcement::query()->create([
            'tenant_id' => $request->user()->tenant_id,
            'created_by' => $request->user()->id,
            'title' => $data['title'],
            'body' => $data['body'],
            'scope' => $data['scope'],
            'batch_id' => $data['batch_id'] ?? null,
            'status' => 'published',
            'published_at' => now(),
        ]);

        if ($request->boolean('notify', true)) {
            $studentQuery = Student::query()->where('status', 'active');

            if ($data['scope'] === 'batch' && ! empty($data['batch_id'])) {
                $ids = Enrolment::query()->where('batch_id', $data['batch_id'])->where('status', 'active')->pluck('student_id');
                $studentQuery->whereIn('id', $ids);
            }

            $studentQuery->with('guardians')->chunkById(50, function ($students) use ($announcement) {
                foreach ($students as $student) {
                    $this->notifications->enqueueForStudentGuardians($student, 'announcement', 'announcement', [
                        'title' => $announcement->title,
                        'body' => $announcement->body,
                        'student_name' => $student->full_name,
                    ]);
                }
            });

            // Dispatch pending outbox in background (sync/database queue friendly).
            \App\Models\NotificationOutbox::query()
                ->where('status', 'pending')
                ->where('event_type', 'announcement')
                ->latest('id')
                ->limit(200)
                ->pluck('id')
                ->each(fn ($id) => ProcessNotificationOutbox::dispatch($id));
        }

        return back()->with('success', 'Announcement published.');
    }
}
