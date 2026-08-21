<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Note;
use App\Models\Subject;
use App\Support\Contracts\FileStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NoteController extends Controller
{
    public function __construct(protected FileStorage $files) {}

    public function index(): Response
    {
        return Inertia::render('Notes/Index', [
            'notes' => Note::query()->with(['batch', 'subject'])->latest()->paginate(20),
            'batches' => Batch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'subjects' => Subject::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'batch_id' => ['nullable', 'exists:batches,id'],
            'subject_id' => ['nullable', 'exists:subjects,id'],
            'external_url' => ['nullable', 'url'],
            'file' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx'],
            'is_published' => ['boolean'],
        ]);

        $path = null;
        $mime = null;
        $size = null;

        if ($request->hasFile('file')) {
            $path = $this->files->storeTenantFile((int) $request->user()->tenant_id, 'notes', $request->file('file'));
            $mime = $request->file('file')->getMimeType();
            $size = $request->file('file')->getSize();
        }

        Note::query()->create([
            'tenant_id' => $request->user()->tenant_id,
            'batch_id' => $data['batch_id'] ?? null,
            'subject_id' => $data['subject_id'] ?? null,
            'uploaded_by' => $request->user()->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'file_path' => $path,
            'external_url' => $data['external_url'] ?? null,
            'mime_type' => $mime,
            'file_size' => $size,
            'is_published' => (bool) ($data['is_published'] ?? true),
            'published_at' => now(),
        ]);

        return back()->with('success', 'Note shared.');
    }
}
