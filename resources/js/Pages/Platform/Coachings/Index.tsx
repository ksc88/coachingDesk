import PlatformLayout from '@/Layouts/PlatformLayout';
import { formatDate } from '@/lib/formatDate';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type Coaching = {
    id: number;
    name: string;
    code: string;
    slug: string;
    status: string;
    students_count: number;
    users_count: number;
    batches_count: number;
    created_at: string | null;
    owner: { id: number; name: string; email: string } | null;
    landing_url: string;
};

const slugify = (value: string) =>
    value
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');

export default function PlatformCoachings({ coachings, stats, credentials, sessionLabel }: any) {
    const [showForm, setShowForm] = useState(coachings.length === 0);

    const form = useForm({
        name: '',
        code: '',
        slug: '',
        owner_name: '',
        owner_email: '',
        owner_phone: '',
        password: '',
        branch: 'Main Campus',
        session: sessionLabel,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(route('platform.coachings.store'), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset('name', 'code', 'slug', 'owner_name', 'owner_email', 'owner_phone', 'password');
                setShowForm(false);
            },
        });
    };

    const setStatus = (coaching: Coaching, status: string) => {
        const verb = status === 'active' ? 'Activate' : 'Suspend';
        if (!confirm(`${verb} ${coaching.name}?`)) return;

        router.patch(route('platform.coachings.status', coaching.id), { status }, { preserveScroll: true });
    };

    const resetPassword = (coaching: Coaching) => {
        if (!confirm(`Generate a new owner password for ${coaching.name}?`)) return;

        router.post(route('platform.coachings.reset-password', coaching.id), {}, { preserveScroll: true });
    };

    return (
        <PlatformLayout
            header={
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="text-xl font-semibold text-gray-900">Coaching accounts</h2>
                        <p className="text-sm text-gray-500">Onboard a coaching, activate or suspend access, reset owner logins.</p>
                    </div>
                    <button
                        onClick={() => setShowForm((value) => !value)}
                        className="rounded-md bg-brand-700 px-4 py-2 text-sm font-medium text-white hover:bg-brand-800"
                    >
                        {showForm ? 'Close' : 'Onboard coaching'}
                    </button>
                </div>
            }
        >
            <Head title="Coachings" />

            <div className="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                <div className="grid gap-4 sm:grid-cols-4">
                    {[
                        { label: 'Coachings', value: stats.total },
                        { label: 'Active', value: stats.active },
                        { label: 'Suspended', value: stats.suspended },
                        { label: 'Students on platform', value: stats.students },
                    ].map((card) => (
                        <div key={card.label} className="rounded-lg bg-white p-4 shadow-sm">
                            <div className="text-xs uppercase tracking-wide text-gray-500">{card.label}</div>
                            <div className="mt-1 text-2xl font-semibold text-gray-900">{card.value}</div>
                        </div>
                    ))}
                </div>

                {credentials && (
                    <div className="rounded-lg border border-amber-300 bg-amber-50 p-4">
                        <h3 className="font-semibold text-amber-900">Credentials for {credentials.coaching}</h3>
                        <p className="mt-1 text-sm text-amber-800">
                            Shown once. Share over a secure channel and ask the owner to change it after first login.
                        </p>
                        <dl className="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                            <div>
                                <dt className="text-amber-700">Login email</dt>
                                <dd className="font-mono">{credentials.email}</dd>
                            </div>
                            <div>
                                <dt className="text-amber-700">Password</dt>
                                <dd className="font-mono">{credentials.password ?? '(as you set it)'}</dd>
                            </div>
                            <div>
                                <dt className="text-amber-700">Login URL</dt>
                                <dd className="font-mono break-all">{credentials.login_url}</dd>
                            </div>
                            <div>
                                <dt className="text-amber-700">Landing page</dt>
                                <dd className="font-mono break-all">{credentials.landing_url}</dd>
                            </div>
                        </dl>
                    </div>
                )}

                {showForm && (
                    <form onSubmit={submit} className="rounded-lg bg-white p-6 shadow-sm">
                        <h3 className="font-semibold text-gray-900">Onboard a coaching</h3>
                        <div className="mt-4 grid gap-4 sm:grid-cols-2">
                            <Field label="Coaching name" error={form.errors.name}>
                                <input
                                    className="w-full rounded border-gray-300"
                                    value={form.data.name}
                                    onChange={(e) => {
                                        const name = e.target.value;
                                        form.setData({
                                            ...form.data,
                                            name,
                                            slug: slugify(name),
                                            code: form.data.code || name.replace(/[^a-zA-Z0-9]/g, '').slice(0, 4).toUpperCase(),
                                        });
                                    }}
                                />
                            </Field>
                            <Field label="Receipt code (e.g. XYZ)" error={form.errors.code} hint="Permanent receipt prefix — cannot be changed later">
                                <input
                                    className="w-full rounded border-gray-300 uppercase"
                                    value={form.data.code}
                                    onChange={(e) => form.setData('code', e.target.value.toUpperCase())}
                                />
                            </Field>
                            <Field label="Landing page slug" error={form.errors.slug} hint={form.data.slug ? `/c/${form.data.slug}` : undefined}>
                                <input
                                    className="w-full rounded border-gray-300"
                                    value={form.data.slug}
                                    onChange={(e) => form.setData('slug', slugify(e.target.value))}
                                />
                            </Field>
                            <Field label="Primary branch" error={form.errors.branch}>
                                <input
                                    className="w-full rounded border-gray-300"
                                    value={form.data.branch}
                                    onChange={(e) => form.setData('branch', e.target.value)}
                                />
                            </Field>
                            <Field label="Owner name" error={form.errors.owner_name}>
                                <input
                                    className="w-full rounded border-gray-300"
                                    value={form.data.owner_name}
                                    onChange={(e) => form.setData('owner_name', e.target.value)}
                                />
                            </Field>
                            <Field label="Owner login email" error={form.errors.owner_email}>
                                <input
                                    type="email"
                                    className="w-full rounded border-gray-300"
                                    value={form.data.owner_email}
                                    onChange={(e) => form.setData('owner_email', e.target.value)}
                                />
                            </Field>
                            <Field label="Owner phone" error={form.errors.owner_phone}>
                                <input
                                    className="w-full rounded border-gray-300"
                                    value={form.data.owner_phone}
                                    onChange={(e) => form.setData('owner_phone', e.target.value)}
                                />
                            </Field>
                            <Field label="Academic session" error={form.errors.session} hint="April–March year, e.g. 2026-27">
                                <input
                                    className="w-full rounded border-gray-300"
                                    value={form.data.session}
                                    onChange={(e) => form.setData('session', e.target.value)}
                                />
                            </Field>
                            <Field label="Password" error={form.errors.password} hint="Leave blank to generate one">
                                <input
                                    className="w-full rounded border-gray-300"
                                    value={form.data.password}
                                    onChange={(e) => form.setData('password', e.target.value)}
                                />
                            </Field>
                        </div>
                        <button
                            disabled={form.processing}
                            className="mt-5 rounded-md bg-brand-700 px-4 py-2 text-sm font-medium text-white hover:bg-brand-800 disabled:opacity-50"
                        >
                            {form.processing ? 'Creating…' : 'Create coaching + owner login'}
                        </button>
                    </form>
                )}

                <div className="overflow-hidden rounded-lg bg-white shadow-sm">
                    <table className="min-w-full divide-y divide-gray-200 text-sm">
                        <thead className="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th className="px-4 py-3">Coaching</th>
                                <th className="px-4 py-3">Owner</th>
                                <th className="px-4 py-3">Students</th>
                                <th className="px-4 py-3">Batches</th>
                                <th className="px-4 py-3">Users</th>
                                <th className="px-4 py-3">Status</th>
                                <th className="px-4 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {coachings.length === 0 && (
                                <tr>
                                    <td colSpan={7} className="px-4 py-8 text-center text-gray-500">
                                        No coachings yet. Use “Onboard coaching” to create your first client.
                                    </td>
                                </tr>
                            )}
                            {coachings.map((coaching: Coaching) => (
                                <tr key={coaching.id}>
                                    <td className="px-4 py-3">
                                        <div className="font-medium text-gray-900">{coaching.name}</div>
                                        <div className="text-xs text-gray-500">
                                            {coaching.code} · joined {formatDate(coaching.created_at)}
                                        </div>
                                        <a href={coaching.landing_url} target="_blank" rel="noreferrer" className="text-xs text-brand-700 underline">
                                            /c/{coaching.slug}
                                        </a>
                                    </td>
                                    <td className="px-4 py-3">
                                        {coaching.owner ? (
                                            <>
                                                <div>{coaching.owner.name}</div>
                                                <div className="text-xs text-gray-500">{coaching.owner.email}</div>
                                            </>
                                        ) : (
                                            <span className="text-gray-400">—</span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">{coaching.students_count}</td>
                                    <td className="px-4 py-3">{coaching.batches_count}</td>
                                    <td className="px-4 py-3">{coaching.users_count}</td>
                                    <td className="px-4 py-3">
                                        <span
                                            className={
                                                'rounded-full px-2 py-1 text-xs font-medium ' +
                                                (coaching.status === 'active'
                                                    ? 'bg-emerald-100 text-emerald-800'
                                                    : 'bg-red-100 text-red-800')
                                            }
                                        >
                                            {coaching.status}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-wrap gap-2">
                                            {coaching.status === 'active' ? (
                                                <button
                                                    onClick={() => setStatus(coaching, 'suspended')}
                                                    className="rounded border border-red-300 px-2 py-1 text-xs text-red-700 hover:bg-red-50"
                                                >
                                                    Deactivate
                                                </button>
                                            ) : (
                                                <button
                                                    onClick={() => setStatus(coaching, 'active')}
                                                    className="rounded border border-emerald-300 px-2 py-1 text-xs text-emerald-700 hover:bg-emerald-50"
                                                >
                                                    Activate
                                                </button>
                                            )}
                                            <button
                                                onClick={() => resetPassword(coaching)}
                                                className="rounded border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-50"
                                            >
                                                Reset owner password
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </PlatformLayout>
    );
}

function Field({ label, error, hint, children }: any) {
    return (
        <label className="block text-sm">
            <span className="font-medium text-gray-700">{label}</span>
            <div className="mt-1">{children}</div>
            {hint && !error && <span className="text-xs text-gray-500">{hint}</span>}
            {error && <span className="text-xs text-red-600">{error}</span>}
        </label>
    );
}
