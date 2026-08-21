import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEvent } from 'react';

const moneyStatus = (status: string) => {
    if (status === 'sent') return 'bg-emerald-50 text-emerald-800';
    if (status === 'failed') return 'bg-rose-50 text-rose-800';
    return 'bg-amber-50 text-amber-800';
};

export default function AlertsIndex({ rows, counts, mode }: any) {
    const { flash } = usePage().props as any;
    const dispatchForm = useForm({});

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">Parent alerts</h2>}>
            <Head title="Parent alerts" />
            <div className="mx-auto max-w-6xl space-y-4 px-4 py-8">
                {flash?.success && (
                    <div className="rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{flash.success}</div>
                )}

                <div className="flex flex-wrap items-start justify-between gap-3">
                    <p className="max-w-xl text-sm text-gray-600">
                        {mode === 'live'
                            ? 'Live mode sends email/SMS through Brevo or WhatsApp through WaAPI. If a row is still pending, click Send pending now.'
                            : 'Safe mode only logs alerts. Switch to Live in Settings after Brevo email/SMS or WaAPI is connected.'}
                    </p>
                    <div className="flex flex-wrap items-center gap-3">
                        {counts.pending > 0 && (
                            <form
                                onSubmit={(e: FormEvent) => {
                                    e.preventDefault();
                                    dispatchForm.post(route('alerts.dispatch'), { preserveScroll: true });
                                }}
                            >
                                <button
                                    type="submit"
                                    disabled={dispatchForm.processing}
                                    className="rounded bg-brand-700 px-3 py-1.5 text-sm font-medium text-white disabled:opacity-50"
                                >
                                    Send pending now
                                </button>
                            </form>
                        )}
                        <Link href={route('settings.index')} className="text-sm text-brand-700 underline">Alert settings</Link>
                    </div>
                </div>

                <div className="grid gap-3 sm:grid-cols-4">
                    <div className="rounded-lg bg-white p-4 shadow-sm">
                        <div className="text-xs uppercase text-gray-400">Mode</div>
                        <div className="mt-1 font-semibold capitalize">{mode === 'live' ? 'Live' : 'Safe'}</div>
                    </div>
                    <div className="rounded-lg bg-white p-4 shadow-sm">
                        <div className="text-xs uppercase text-gray-400">Pending</div>
                        <div className="mt-1 font-semibold">{counts.pending}</div>
                    </div>
                    <div className="rounded-lg bg-white p-4 shadow-sm">
                        <div className="text-xs uppercase text-gray-400">Sent / logged</div>
                        <div className="mt-1 font-semibold">{counts.sent}</div>
                    </div>
                    <div className="rounded-lg bg-white p-4 shadow-sm">
                        <div className="text-xs uppercase text-gray-400">Failed</div>
                        <div className="mt-1 font-semibold">{counts.failed}</div>
                    </div>
                </div>

                <div className="overflow-hidden rounded-lg bg-white shadow-sm">
                    <table className="min-w-full text-sm">
                        <thead className="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th className="px-4 py-3">When</th>
                                <th className="px-4 py-3">Student</th>
                                <th className="px-4 py-3">Parent</th>
                                <th className="px-4 py-3">Channel</th>
                                <th className="px-4 py-3">Message</th>
                                <th className="px-4 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {(rows.data || []).length === 0 && (
                                <tr><td colSpan={6} className="px-4 py-8 text-center text-gray-500">No alerts yet. Mark a student absent to queue one.</td></tr>
                            )}
                            {(rows.data || []).map((row: any) => (
                                <tr key={row.id} className="border-t align-top">
                                    <td className="whitespace-nowrap px-4 py-3 text-xs text-gray-500">{row.created_at}</td>
                                    <td className="px-4 py-3">
                                        {row.student ? `${row.student.admission_no} · ${row.student.name}` : '—'}
                                        <div className="text-xs text-gray-400">{row.event_type}</div>
                                    </td>
                                    <td className="px-4 py-3">
                                        {row.recipient_name || '—'}
                                        <div className="text-xs text-gray-400">{row.recipient_phone || row.recipient_email || ''}</div>
                                    </td>
                                    <td className="px-4 py-3 capitalize">{row.channel}</td>
                                    <td className="max-w-sm px-4 py-3 text-xs text-gray-700">{row.body}</td>
                                    <td className="px-4 py-3">
                                        <span className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${moneyStatus(row.status)}`}>
                                            {row.status}
                                            {row.delivery_mode === 'safe' && row.status === 'sent' ? ' · log' : ''}
                                        </span>
                                        {row.failure_reason && (
                                            <div className="mt-1 max-w-[12rem] text-[11px] text-rose-700">{row.failure_reason}</div>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
