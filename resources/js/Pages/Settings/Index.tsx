import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

const input = 'w-full rounded border-gray-300 text-sm';

type Coaching = {
    name: string;
    slug: string;
    phone?: string | null;
    email?: string | null;
    address?: string | null;
    primary_color: string;
    landing_headline: string;
    landing_subheadline: string;
    landing_hero_url?: string | null;
    public_url: string;
};

export default function SettingsIndex({ coaching, gateway, alerts }: { coaching: Coaching; gateway?: any; alerts?: any }) {
    const { flash } = usePage().props as any;
    const [preview, setPreview] = useState<string | null>(coaching.landing_hero_url || null);

    const form = useForm({
        phone: coaching.phone || '',
        email: coaching.email || '',
        address: coaching.address || '',
        primary_color: coaching.primary_color || '#0c4a6e',
        landing_headline: coaching.landing_headline || '',
        landing_subheadline: coaching.landing_subheadline || '',
        landing_hero: null as File | null,
        remove_landing_hero: false as boolean,
    });

    const alertsForm = useForm({
        mode: alerts?.mode || 'safe',
        whatsapp_provider: alerts?.whatsapp_provider || '',
        whatsapp_from: alerts?.whatsapp_from || '',
        whatsapp_token: '',
        email_from: alerts?.email_from || '',
        email_from_name: alerts?.email_from_name || '',
        sms_provider: alerts?.sms_provider || 'brevo',
        sms_sender: alerts?.sms_sender || '',
        sms_api_key: '',
    });

    const gatewayForm = useForm({
        key_id: gateway?.key_id || '',
        key_secret: '',
        webhook_secret: '',
        mode: gateway?.mode || 'test',
        is_active: Boolean(gateway?.is_active),
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(route('settings.update'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => form.setData('landing_hero', null),
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h2 className="text-xl font-semibold text-gray-800">Settings</h2>
                    <p className="text-sm text-gray-500">Edit what visitors see on your public home page.</p>
                </div>
            }
        >
            <Head title="Settings" />

            <div className="mx-auto max-w-3xl space-y-6 px-4 py-8">
                {(flash?.success || flash?.error) && (
                    <div className={'rounded-md px-4 py-3 text-sm ' + (flash.error
                        ? 'bg-red-50 text-red-800'
                        : 'bg-emerald-50 text-emerald-800')}>
                        {flash.error || flash.success}
                    </div>
                )}

                <div className="rounded-lg bg-white p-5 shadow-sm">
                    <h3 className="font-semibold text-gray-900">{coaching.name}</h3>
                    <p className="mt-1 text-sm text-gray-500">
                        Public page:{' '}
                        <a href={coaching.public_url} target="_blank" rel="noreferrer" className="text-brand-700 underline">
                            {coaching.public_url}
                        </a>
                    </p>
                </div>

                <form onSubmit={submit} className="space-y-5 rounded-lg bg-white p-5 shadow-sm">
                    <h3 className="font-semibold text-gray-900">Home page text</h3>
                    <p className="text-xs text-gray-500">
                        Headline and supporting line under your coaching name.
                    </p>

                    <label className="block">
                        <span className="text-xs font-medium text-gray-600">Headline</span>
                        <input
                            className={input + ' mt-1'}
                            value={form.data.landing_headline}
                            onChange={(e) => form.setData('landing_headline', e.target.value)}
                            placeholder="Speak with clarity. Learn with confidence."
                        />
                        {form.errors.landing_headline && <p className="mt-1 text-xs text-red-600">{form.errors.landing_headline}</p>}
                    </label>

                    <label className="block">
                        <span className="text-xs font-medium text-gray-600">Supporting line</span>
                        <textarea
                            rows={3}
                            className={input + ' mt-1'}
                            value={form.data.landing_subheadline}
                            onChange={(e) => form.setData('landing_subheadline', e.target.value)}
                            placeholder="Spoken English courses in focused batches…"
                        />
                        {form.errors.landing_subheadline && <p className="mt-1 text-xs text-red-600">{form.errors.landing_subheadline}</p>}
                    </label>

                    <hr className="border-gray-100" />

                    <div>
                        <h3 className="font-semibold text-gray-900">Hero image (optional)</h3>
                        <p className="mt-1 text-xs text-gray-500">
                            Leave empty for the clean light layout. Upload a photo to use a full-bleed hero.
                            Best size: <strong>1920×1080</strong> (landscape), JPG/PNG/WebP, max 2 MB.
                            Wrong ratios are cropped to fit — they will not stretch.
                        </p>

                        {preview && (
                            <div className="mt-3 overflow-hidden rounded-lg border border-gray-200">
                                <img src={preview} alt="Hero preview" className="h-40 w-full object-cover" />
                            </div>
                        )}

                        <input
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            className="mt-3 block w-full text-sm text-gray-600"
                            onChange={(e) => {
                                const file = e.target.files?.[0] || null;
                                form.setData({
                                    ...form.data,
                                    landing_hero: file,
                                    remove_landing_hero: false,
                                });
                                setPreview(file ? URL.createObjectURL(file) : (coaching.landing_hero_url || null));
                            }}
                        />
                        {form.errors.landing_hero && <p className="mt-1 text-xs text-red-600">{form.errors.landing_hero}</p>}

                        {(preview || coaching.landing_hero_url) && (
                            <button
                                type="button"
                                className="mt-2 text-sm text-red-600 underline"
                                onClick={() => {
                                    form.setData({
                                        ...form.data,
                                        landing_hero: null,
                                        remove_landing_hero: true,
                                    });
                                    setPreview(null);
                                }}
                            >
                                Remove hero image (use clean layout)
                            </button>
                        )}
                    </div>

                    <hr className="border-gray-100" />

                    <h3 className="font-semibold text-gray-900">Contact on home page</h3>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <label className="block">
                            <span className="text-xs font-medium text-gray-600">Phone (Call button)</span>
                            <input
                                className={input + ' mt-1'}
                                value={form.data.phone}
                                onChange={(e) => form.setData('phone', e.target.value)}
                            />
                        </label>
                        <label className="block">
                            <span className="text-xs font-medium text-gray-600">Email</span>
                            <input
                                type="email"
                                className={input + ' mt-1'}
                                value={form.data.email}
                                onChange={(e) => form.setData('email', e.target.value)}
                            />
                        </label>
                    </div>

                    <label className="block">
                        <span className="text-xs font-medium text-gray-600">Address (footer)</span>
                        <textarea
                            rows={2}
                            className={input + ' mt-1'}
                            value={form.data.address}
                            onChange={(e) => form.setData('address', e.target.value)}
                            placeholder="Campus / city address"
                        />
                    </label>

                    <label className="block max-w-[12rem]">
                        <span className="text-xs font-medium text-gray-600">Accent color</span>
                        <input
                            type="color"
                            className="mt-1 h-10 w-full cursor-pointer rounded border border-gray-200"
                            value={form.data.primary_color}
                            onChange={(e) => form.setData('primary_color', e.target.value)}
                        />
                        <span className="mt-1 block text-[11px] text-gray-500">
                            Soft page tint only. Phone / Call stay dark so yellow or light accents stay readable.
                        </span>
                    </label>

                    <button
                        type="submit"
                        disabled={form.processing}
                        className="rounded bg-brand-700 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                    >
                        {form.processing ? 'Saving…' : 'Save home page'}
                    </button>
                </form>

                <form
                    onSubmit={(e: FormEvent) => {
                        e.preventDefault();
                        gatewayForm.post(route('settings.gateway.save'), { preserveScroll: true });
                    }}
                    className="space-y-4 rounded-lg bg-white p-5 shadow-sm"
                >
                    <div className="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <h3 className="font-semibold text-gray-900">Online payments (optional)</h3>
                            <p className="mt-1 text-xs text-gray-500">
                                Fees can always be recorded manually (cash / UPI). Enable Razorpay only if this coaching wants online collection into their own account.
                            </p>
                        </div>
                        <span className={'rounded-full px-2.5 py-1 text-xs font-medium ' + (gateway?.is_active && gateway?.connected
                            ? 'bg-emerald-100 text-emerald-800'
                            : 'bg-slate-100 text-slate-700')}>
                            {gateway?.is_active && gateway?.connected ? 'Online ON' : 'Manual only'}
                        </span>
                    </div>
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={gatewayForm.data.is_active}
                            onChange={(e) => gatewayForm.setData('is_active', e.target.checked)}
                        />
                        Enable Razorpay for this coaching
                    </label>
                    <div className="grid gap-3 sm:grid-cols-3">
                        <input className={input} placeholder="Key ID" value={gatewayForm.data.key_id} onChange={(e) => gatewayForm.setData('key_id', e.target.value)} />
                        <input className={input} placeholder="Key Secret (leave blank to keep)" value={gatewayForm.data.key_secret} onChange={(e) => gatewayForm.setData('key_secret', e.target.value)} />
                        <input className={input} placeholder="Webhook Secret" value={gatewayForm.data.webhook_secret} onChange={(e) => gatewayForm.setData('webhook_secret', e.target.value)} />
                    </div>
                    <select className={input + ' max-w-xs'} value={gatewayForm.data.mode} onChange={(e) => gatewayForm.setData('mode', e.target.value)}>
                        <option value="test">Test mode</option>
                        <option value="live">Live mode</option>
                    </select>
                    <button type="submit" disabled={gatewayForm.processing} className="rounded bg-brand-700 px-4 py-2 text-sm font-medium text-white disabled:opacity-50">
                        Save payment settings
                    </button>
                </form>

                <form
                    onSubmit={(e: FormEvent) => {
                        e.preventDefault();
                        alertsForm.post(route('settings.alerts.save'), { preserveScroll: true });
                    }}
                    className="space-y-4 rounded-lg bg-white p-5 shadow-sm"
                >
                    <div className="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <h3 className="font-semibold text-gray-900">Parent alerts</h3>
                            <p className="mt-1 text-xs text-gray-500">
                                One channel only: WhatsApp → SMS → Email. Easiest live test is email (Brevo free tier):
                                save From + API key, turn Live on, uncheck WhatsApp and SMS on the student, then mark absent/present.
                            </p>
                        </div>
                        <span className={'rounded-full px-2.5 py-1 text-xs font-medium ' + (alertsForm.data.mode === 'live'
                            ? 'bg-amber-100 text-amber-800'
                            : 'bg-slate-100 text-slate-700')}>
                            {alertsForm.data.mode === 'live' ? 'Live' : 'Safe mode'}
                        </span>
                    </div>
                    <label className="block max-w-xs text-sm">
                        Send mode
                        <select className={input + ' mt-1'} value={alertsForm.data.mode} onChange={(e) => alertsForm.setData('mode', e.target.value)}>
                            <option value="safe">Safe — log only, do not message parents</option>
                            <option value="live">Live — send via Email / SMS / WhatsApp</option>
                        </select>
                    </label>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <label className="block text-sm">
                            Provider
                            <input
                                className={input + ' mt-1'}
                                placeholder="Type: waapi"
                                value={alertsForm.data.whatsapp_provider}
                                onChange={(e) => alertsForm.setData('whatsapp_provider', e.target.value)}
                            />
                        </label>
                        <label className="block text-sm">
                            Instance ID
                            <input
                                className={input + ' mt-1'}
                                placeholder="e.g. 102486 from WaAPI"
                                value={alertsForm.data.whatsapp_from}
                                onChange={(e) => alertsForm.setData('whatsapp_from', e.target.value)}
                            />
                        </label>
                        <label className="block text-sm sm:col-span-2">
                            <span className="flex flex-wrap items-center gap-2">
                                Access token
                                {alerts?.has_whatsapp_token ? (
                                    <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">
                                        Saved — field stays blank on purpose
                                    </span>
                                ) : null}
                            </span>
                            <input
                                className={input + ' mt-1'}
                                type="password"
                                autoComplete="off"
                                placeholder={alerts?.has_whatsapp_token ? 'Leave blank to keep saved token' : 'Paste from WaAPI → API Tokens'}
                                value={alertsForm.data.whatsapp_token}
                                onChange={(e) => alertsForm.setData('whatsapp_token', e.target.value)}
                            />
                            <span className="mt-1 block text-xs text-gray-500">
                                {alerts?.has_whatsapp_token
                                    ? 'Your token is stored securely. Only paste a new one if you want to replace it.'
                                    : 'Paste once and save. It will look empty after save — that means it worked.'}
                            </span>
                        </label>
                        <label className="block text-sm sm:col-span-2">
                            Email from (verified in Brevo)
                            <input
                                className={input + ' mt-1'}
                                placeholder="desk@yourcoaching.com"
                                value={alertsForm.data.email_from}
                                onChange={(e) => alertsForm.setData('email_from', e.target.value)}
                            />
                            <span className="mt-1 block text-xs text-gray-500">
                                Must be a sender verified in Brevo → Senders, Domains & Dedicated IPs.
                            </span>
                        </label>
                        <label className="block text-sm sm:col-span-2">
                            Email from name (optional)
                            <input
                                className={input + ' mt-1'}
                                placeholder="Your coaching name"
                                value={alertsForm.data.email_from_name}
                                onChange={(e) => alertsForm.setData('email_from_name', e.target.value)}
                            />
                        </label>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <label className="block text-sm">
                            SMS provider
                            <input
                                className={input + ' mt-1'}
                                placeholder="brevo"
                                value={alertsForm.data.sms_provider}
                                onChange={(e) => alertsForm.setData('sms_provider', e.target.value)}
                            />
                        </label>
                        <label className="block text-sm">
                            SMS sender ID
                            <input
                                className={input + ' mt-1'}
                                placeholder="e.g. MYCOACH (max 11 letters)"
                                value={alertsForm.data.sms_sender}
                                onChange={(e) => alertsForm.setData('sms_sender', e.target.value)}
                            />
                        </label>
                        <label className="block text-sm sm:col-span-2">
                            <span className="flex flex-wrap items-center gap-2">
                                Brevo API key (email + SMS)
                                {alerts?.has_sms_api_key ? (
                                    <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">
                                        Saved — field stays blank on purpose
                                    </span>
                                ) : null}
                            </span>
                            <input
                                className={input + ' mt-1'}
                                type="password"
                                autoComplete="off"
                                placeholder={alerts?.has_sms_api_key ? 'Leave blank to keep saved key' : 'Paste from Brevo → SMTP & API'}
                                value={alertsForm.data.sms_api_key}
                                onChange={(e) => alertsForm.setData('sms_api_key', e.target.value)}
                            />
                            <span className="mt-1 block text-xs text-gray-500">
                                One Brevo key works for transactional email and SMS. Get it from Brevo → SMTP & API.
                            </span>
                        </label>
                    </div>
                    <div className="flex flex-wrap items-center gap-3">
                        <button type="submit" disabled={alertsForm.processing} className="rounded bg-brand-700 px-4 py-2 text-sm font-medium text-white disabled:opacity-50">
                            Save alert settings
                        </button>
                        <a href={route('alerts.index')} className="text-sm text-brand-700 underline">Open alert queue</a>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
