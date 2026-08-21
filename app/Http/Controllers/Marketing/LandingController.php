<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Enquiry;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use App\Support\Validation\ContactRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LandingController extends Controller
{
    public function platform(): View
    {
        return view('marketing.platform', [
            'solutions' => [
                [
                    'title' => 'Batches & student records',
                    'body' => 'Organize competition and foundation classes by batch, with clean student categories and attendance-ready rolls.',
                ],
                [
                    'title' => 'Attendance with parent alerts',
                    'body' => 'Mark daily attendance and notify parents over WhatsApp or SMS when a student is absent — same day, without manual follow-ups.',
                ],
                [
                    'title' => 'Fees, dues & receipts',
                    'body' => 'Track invoices, discounts, partial payments, and collections. Connect Razorpay with your own keys when you are ready.',
                ],
                [
                    'title' => 'Enquiries, news & notes',
                    'body' => 'Capture landing-page leads, share coaching news by batch or org, and keep shared notes for teachers in one place.',
                ],
            ],
            'benefits' => [
                [
                    'title' => 'Fewer fee follow-ups',
                    'body' => 'See due today, overdue, and collected amounts on one dashboard so staff know whom to call next.',
                ],
                [
                    'title' => 'Parents stay informed',
                    'body' => 'Attendance and news alerts reduce “did my child come today?” calls and build trust with families.',
                ],
                [
                    'title' => 'One desk for every coaching',
                    'body' => 'Multi-tenant from day one — run multiple centres on one platform with separate branding and data.',
                ],
            ],
        ]);
    }

    public function coaching(string $slug): View
    {
        $tenant = Tenant::query()->where('slug', $slug)->where('status', 'active')->firstOrFail();
        TenantContext::set($tenant);

        $courses = Course::query()
            ->with('category')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $batches = Batch::query()
            ->with('course')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('marketing.coaching', [
            'tenant' => $tenant,
            'courses' => $courses,
            'batches' => $batches,
            'landing' => $this->landingCopy($tenant, $courses),
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Course>  $courses
     * @return array{headline: string, subheadline: string, hero_url: ?string}
     */
    protected function landingCopy(Tenant $tenant, $courses): array
    {
        $settings = $tenant->settings ?? [];

        $headline = trim((string) ($settings['landing_headline'] ?? ''));
        $subheadline = trim((string) ($settings['landing_subheadline'] ?? ''));

        if ($headline === '' || $subheadline === '') {
            $defaults = $this->defaultLandingCopy($tenant, $courses);
            $headline = $headline !== '' ? $headline : $defaults['headline'];
            $subheadline = $subheadline !== '' ? $subheadline : $defaults['subheadline'];
        }

        $heroPath = trim((string) ($settings['landing_hero_path'] ?? ''));
        $heroUrl = $heroPath !== '' ? asset('storage/'.$heroPath) : null;

        return [
            'headline' => $headline,
            'subheadline' => $subheadline,
            'hero_url' => $heroUrl,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Course>  $courses
     * @return array{headline: string, subheadline: string}
     */
    protected function defaultLandingCopy(Tenant $tenant, $courses): array
    {
        $haystack = strtolower($tenant->name.' '.$courses->pluck('name')->implode(' '));

        if (str_contains($haystack, 'spoken') || str_contains($haystack, 'speak') || str_contains($haystack, 'english')) {
            return [
                'headline' => 'Speak with clarity. Learn with confidence.',
                'subheadline' => 'Enquire for the right spoken English course, get a personal follow-up, and join a batch that fits your pace.',
            ];
        }

        if (str_contains($haystack, 'jee') || str_contains($haystack, 'neet') || str_contains($haystack, 'competition')) {
            return [
                'headline' => 'Competition classes in focused batches.',
                'subheadline' => 'Enquire for courses, get follow-up from our team, and join the right batch.',
            ];
        }

        return [
            'headline' => 'Focused classes. The right batch for you.',
            'subheadline' => 'Enquire for courses, get a personal follow-up, and join a batch that fits your goals.',
        ];
    }

    public function enquiry(Request $request, string $slug): RedirectResponse
    {
        $tenant = Tenant::query()->where('slug', $slug)->where('status', 'active')->firstOrFail();
        TenantContext::set($tenant);

        $hasBatches = Batch::query()->where('tenant_id', $tenant->id)->where('is_active', true)->exists();
        $hasCourses = Course::query()->where('tenant_id', $tenant->id)->where('is_active', true)->count() > 1;

        $validator = Validator::make($request->all(), [
            'name' => ContactRules::personName(),
            'phone' => ContactRules::indianMobile(),
            'email' => ['nullable', 'email:filter', 'max:191'],
            'course_id' => [
                $hasCourses && ! $hasBatches ? 'required' : 'nullable',
                Rule::exists('courses', 'id')->where(fn ($q) => $q->where('tenant_id', $tenant->id)),
            ],
            'batch_id' => [
                $hasBatches ? 'required' : 'nullable',
                Rule::exists('batches', 'id')->where(fn ($q) => $q
                    ->where('tenant_id', $tenant->id)
                    ->where('is_active', true)),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
            'whatsapp_opt_in' => ['nullable', 'boolean'],
        ], [
            'name.regex' => 'Name may only include letters, spaces, and . \' -',
            'batch_id.required' => 'Please select a class / batch.',
            'course_id.required' => 'Please select a course.',
            'email.email' => 'Enter a valid email address.',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray())
                ->redirectTo(route('marketing.coaching', $slug).'#enquire');
        }

        $data = $validator->validated();

        $courseId = $data['course_id'] ?? null;
        $batchId = $data['batch_id'] ?? null;

        if ($batchId && ! $courseId) {
            $courseId = Batch::query()->whereKey($batchId)->value('course_id');
        }

        Enquiry::query()->create([
            'tenant_id' => $tenant->id,
            'name' => trim($data['name']),
            'phone' => ContactRules::normalizeIndianMobile($data['phone']),
            'email' => $data['email'] ?? null,
            'course_id' => $courseId,
            'batch_id' => $batchId,
            'notes' => $data['notes'] ?? null,
            'whatsapp_opt_in' => (bool) ($data['whatsapp_opt_in'] ?? false),
            'sms_opt_in' => (bool) ($data['whatsapp_opt_in'] ?? false),
            'source' => 'landing_page',
            'status' => 'new',
        ]);

        return redirect()
            ->to(route('marketing.coaching', $slug).'#enquire')
            ->with('success', 'Thanks! Our team will contact you shortly.');
    }
}
