<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\TenantPaymentGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $tenant = $request->user()->tenant;
        $heroPath = $tenant->settings['landing_hero_path'] ?? null;
        $gateway = TenantPaymentGateway::query()->where('provider', 'razorpay')->first();

        $alerts = $tenant->settings['alerts'] ?? [];

        return Inertia::render('Settings/Index', [
            'coaching' => [
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'phone' => $tenant->phone,
                'email' => $tenant->email,
                'address' => $tenant->address,
                'primary_color' => $tenant->primary_color ?: '#0c4a6e',
                'landing_headline' => $tenant->settings['landing_headline'] ?? '',
                'landing_subheadline' => $tenant->settings['landing_subheadline'] ?? '',
                'landing_hero_url' => $heroPath ? asset('storage/'.$heroPath) : null,
                'public_url' => url('/c/'.$tenant->slug),
            ],
            'gateway' => $gateway ? [
                'key_id' => $gateway->key_id,
                'mode' => $gateway->mode,
                'is_active' => (bool) $gateway->is_active,
                'connected' => filled($gateway->key_id),
            ] : null,
            'alerts' => [
                'mode' => $alerts['mode'] ?? 'safe',
                'whatsapp_provider' => $alerts['whatsapp_provider'] ?? '',
                'whatsapp_from' => $alerts['whatsapp_from'] ?? '',
                'email_from' => $alerts['email_from'] ?? ($tenant->email ?: ''),
                'email_from_name' => $alerts['email_from_name'] ?? ($tenant->name ?: ''),
                'has_whatsapp_token' => filled($alerts['whatsapp_token'] ?? null),
                'sms_provider' => $alerts['sms_provider'] ?? 'brevo',
                'sms_sender' => $alerts['sms_sender'] ?? '',
                'has_sms_api_key' => filled($alerts['sms_api_key'] ?? null),
                'has_email_api_key' => filled($alerts['email_api_key'] ?? $alerts['sms_api_key'] ?? null),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $tenant = $request->user()->tenant;

        $data = $request->validate([
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:191'],
            'address' => ['nullable', 'string', 'max:500'],
            'primary_color' => ['nullable', 'string', 'max:16'],
            'landing_headline' => ['nullable', 'string', 'max:160'],
            'landing_subheadline' => ['nullable', 'string', 'max:400'],
            'landing_hero' => ['nullable', 'image', 'max:2048', 'mimes:jpg,jpeg,png,webp'],
            'remove_landing_hero' => ['nullable', 'boolean'],
        ]);

        $settings = $tenant->settings ?? [];

        if ($request->boolean('remove_landing_hero') && ! empty($settings['landing_hero_path'])) {
            Storage::disk('public')->delete($settings['landing_hero_path']);
            unset($settings['landing_hero_path']);
        }

        if ($request->hasFile('landing_hero')) {
            if (! empty($settings['landing_hero_path'])) {
                Storage::disk('public')->delete($settings['landing_hero_path']);
            }

            $directory = 'tenants/'.$tenant->id.'/landing';
            Storage::disk('public')->makeDirectory($directory);

            $settings['landing_hero_path'] = $request->file('landing_hero')
                ->store($directory, 'public');
        }

        $tenant->phone = $data['phone'] ?? null;
        $tenant->email = $data['email'] ?? null;
        $tenant->address = $data['address'] ?? null;
        $tenant->primary_color = ($data['primary_color'] ?? null) ?: '#0c4a6e';
        $tenant->settings = array_merge($settings, [
            'landing_headline' => trim((string) ($data['landing_headline'] ?? '')),
            'landing_subheadline' => trim((string) ($data['landing_subheadline'] ?? '')),
        ]);
        $tenant->save();

        return back()->with('success', 'Home page settings saved. Refresh the public page to see changes.');
    }

    public function saveGateway(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'key_id' => ['nullable', 'string'],
            'key_secret' => ['nullable', 'string'],
            'webhook_secret' => ['nullable', 'string'],
            'mode' => ['required', 'in:test,live'],
            'is_active' => ['boolean'],
        ]);

        $payload = [
            'mode' => $data['mode'],
            'onboarding_status' => filled($data['key_id'] ?? null) ? 'connected' : 'pending',
            'enabled_methods' => ['upi', 'card', 'netbanking'],
            'is_active' => (bool) ($data['is_active'] ?? false),
        ];

        if (array_key_exists('key_id', $data)) {
            $payload['key_id'] = $data['key_id'];
        }
        if (! empty($data['key_secret'])) {
            $payload['key_secret'] = $data['key_secret'];
        }
        if (array_key_exists('webhook_secret', $data)) {
            $payload['webhook_secret'] = $data['webhook_secret'];
        }

        TenantPaymentGateway::query()->updateOrCreate(
            [
                'tenant_id' => $request->user()->tenant_id,
                'provider' => 'razorpay',
            ],
            $payload
        );

        return back()->with('success', ($data['is_active'] ?? false)
            ? 'Online payments enabled for this coaching.'
            : 'Gateway saved. Fees stay on manual cash/UPI until you enable online pay.');
    }

    public function saveAlerts(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mode' => ['required', 'in:safe,live'],
            'whatsapp_provider' => ['nullable', 'string', 'max:80'],
            'whatsapp_from' => ['nullable', 'string', 'max:40'],
            'whatsapp_token' => ['nullable', 'string', 'max:500'],
            'email_from' => ['nullable', 'email', 'max:191'],
            'email_from_name' => ['nullable', 'string', 'max:70'],
            'sms_provider' => ['nullable', 'string', 'max:80'],
            'sms_sender' => ['nullable', 'string', 'max:15'],
            'sms_api_key' => ['nullable', 'string', 'max:500'],
        ]);

        $tenant = $request->user()->tenant;
        $settings = $tenant->settings ?? [];
        $alerts = $settings['alerts'] ?? [];

        $alerts['mode'] = $data['mode'];
        $alerts['whatsapp_provider'] = trim((string) ($data['whatsapp_provider'] ?? ''));
        $alerts['whatsapp_from'] = trim((string) ($data['whatsapp_from'] ?? ''));
        $alerts['email_from'] = trim((string) ($data['email_from'] ?? ''));
        $alerts['email_from_name'] = trim((string) ($data['email_from_name'] ?? ''));
        $alerts['email_provider'] = 'brevo';
        $alerts['sms_provider'] = strtolower(trim((string) ($data['sms_provider'] ?? 'brevo'))) ?: 'brevo';
        $alerts['sms_sender'] = trim((string) ($data['sms_sender'] ?? ''));

        if (filled($data['whatsapp_token'] ?? null)) {
            $alerts['whatsapp_token'] = $data['whatsapp_token'];
        }

        if (filled($data['sms_api_key'] ?? null)) {
            $alerts['sms_api_key'] = $data['sms_api_key'];
            // Same Brevo key powers SMS + transactional email.
            $alerts['email_api_key'] = $data['sms_api_key'];
        }

        $settings['alerts'] = $alerts;
        $tenant->settings = $settings;
        $tenant->save();

        return back()->with('success', $this->alertsSavedMessage($data['mode'], $alerts));
    }

    protected function alertsSavedMessage(string $mode, array $alerts): string
    {
        if ($mode !== 'live') {
            return 'Parent alerts stay in safe mode (logged locally, not sent to parents).';
        }

        $apiKey = $alerts['email_api_key'] ?? $alerts['sms_api_key'] ?? null;

        $emailReady = filled($apiKey) && filled($alerts['email_from'] ?? null);

        $smsReady = in_array(strtolower($alerts['sms_provider'] ?? ''), ['brevo', 'sendinblue'], true)
            && filled($alerts['sms_api_key'] ?? null)
            && filled($alerts['sms_sender'] ?? null);

        $whatsappReady = in_array(strtolower($alerts['whatsapp_provider'] ?? ''), ['waapi', 'wa-api', 'waapi.app'], true)
            && filled($alerts['whatsapp_token'] ?? null)
            && filled($alerts['whatsapp_from'] ?? null);

        if ($emailReady) {
            return 'Live email (Brevo) ready. Add a parent email, uncheck WhatsApp + SMS on the student, then mark absent/present to test.';
        }

        if ($smsReady) {
            return 'Live SMS (Brevo) saved. Uncheck WhatsApp on the student, then mark absent or present to send a real SMS.';
        }

        if ($whatsappReady) {
            return 'Live WhatsApp (WaAPI) saved. Mark a student absent to send a real test to the parent phone.';
        }

        return 'Live mode saved. Add a Brevo From email + API key for email alerts, or fill SMS / WhatsApp settings.';
    }
}
