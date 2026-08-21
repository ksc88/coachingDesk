<?php

namespace App\Http\Controllers\App;

use App\Domain\CRM\EnquiryService;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Enquiry;
use App\Support\Validation\ContactRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EnquiryController extends Controller
{
    public function __construct(protected EnquiryService $enquiries) {}

    public function index(Request $request): Response
    {
        // Default = open pipeline only (not 1000 closed leads on one screen).
        $view = $request->string('view')->toString() ?: 'open';
        $search = trim($request->string('q')->toString());
        $openStatuses = ['new', 'contacted', 'interested', 'demo_scheduled'];

        $base = Enquiry::query();

        $counts = [
            'open' => (clone $base)->whereIn('status', $openStatuses)->count(),
            'all' => (clone $base)->count(),
            'new' => (clone $base)->where('status', 'new')->count(),
            'contacted' => (clone $base)->where('status', 'contacted')->count(),
            'interested' => (clone $base)->where('status', 'interested')->count(),
            'demo_scheduled' => (clone $base)->where('status', 'demo_scheduled')->count(),
            'admitted' => (clone $base)->where('status', 'admitted')->count(),
            'lost' => (clone $base)->where('status', 'lost')->count(),
        ];

        $items = Enquiry::query()
            ->with(['course', 'batch', 'owner'])
            ->when($view === 'open', fn ($q) => $q->whereIn('status', $openStatuses))
            ->when(in_array($view, ['new', 'contacted', 'interested', 'demo_scheduled', 'admitted', 'lost'], true), fn ($q) => $q->where('status', $view))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Enquiries/Index', [
            'enquiries' => $items,
            'filters' => [
                'view' => $view,
                'q' => $search,
            ],
            'counts' => $counts,
            'courses' => Course::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'batches' => Batch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ContactRules::personName(),
            'phone' => ContactRules::indianMobile(),
            'email' => ['nullable', 'email:filter', 'max:191'],
            'source' => ['nullable', 'string', 'max:50', 'in:walk-in,landing_page,referral,phone,other'],
            'course_id' => ['nullable', 'exists:courses,id'],
            'batch_id' => ['nullable', 'exists:batches,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'next_follow_up_at' => ['nullable', 'date'],
        ], [
            'name.regex' => 'Name may only include letters, spaces, and . \' -',
        ]);

        $data['phone'] = ContactRules::normalizeIndianMobile($data['phone']);
        $data['name'] = trim($data['name']);
        $data['source'] = $data['source'] ?? 'walk-in';

        if (! empty($data['batch_id'])) {
            $batch = Batch::query()->find($data['batch_id']);
            if ($batch) {
                $data['course_id'] = $data['course_id'] ?: $batch->course_id;
            }
        }

        Enquiry::query()->create([
            ...$data,
            'tenant_id' => $request->user()->tenant_id,
            'owner_id' => $request->user()->id,
            'status' => 'new',
        ]);

        return back()->with('success', 'Enquiry captured.');
    }

    public function followUp(Request $request, Enquiry $enquiry): RedirectResponse
    {
        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
            'type' => ['nullable', 'string'],
            'outcome' => ['nullable', 'string'],
            'status' => ['nullable', 'in:new,contacted,interested,demo_scheduled,admitted,lost'],
            'next_follow_up_at' => ['nullable', 'date'],
        ]);

        // Status-only updates are allowed; notes stay optional.
        if (blank($data['notes'] ?? null) && blank($data['status'] ?? null)) {
            return back()->withErrors(['notes' => 'Add follow-up notes or choose a new status.']);
        }

        $this->enquiries->addFollowUp($enquiry, $data);

        return back()->with('success', 'Follow-up saved.');
    }

    public function convert(Request $request, Enquiry $enquiry): RedirectResponse
    {
        $data = $request->validate([
            'admission_no' => ['nullable', 'string'],
            'batch_id' => ['nullable', 'exists:batches,id'],
            'guardian_name' => ['nullable', 'string'],
            'guardian_phone' => ['nullable', 'string'],
            'whatsapp_opt_in' => ['boolean'],
        ]);

        $this->enquiries->convertToAdmission($enquiry, $data);

        return back()->with('success', 'Enquiry converted to admission.');
    }
}
