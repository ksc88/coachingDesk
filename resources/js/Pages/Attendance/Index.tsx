import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import DateInput from '@/Components/DateInput';
import { formatDate } from '@/lib/formatDate';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEvent } from 'react';

export default function AttendanceIndex({ sessions, batches, subjects }: any) {
    const { flash } = usePage().props as any;
    const form = useForm({
        batch_id: '',
        subject_id: '',
        session_date: new Date().toISOString().slice(0, 10),
        topic: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(route('attendance.sessions.store'));
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">Attendance</h2>}>
            <Head title="Attendance" />
            <div className="mx-auto grid max-w-7xl gap-6 px-4 py-8 lg:grid-cols-3">
                {flash?.success && (
                    <div className="lg:col-span-3 rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{flash.success}</div>
                )}
                <form onSubmit={submit} className="space-y-3 rounded-lg bg-white p-5 shadow-sm">
                    <h3 className="font-semibold">New class session</h3>
                    <select className="w-full rounded border-gray-300" value={form.data.batch_id} onChange={(e) => form.setData('batch_id', e.target.value)} required>
                        <option value="">Batch</option>
                        {batches.map((b: any) => <option key={b.id} value={b.id}>{b.name}</option>)}
                    </select>
                    {form.errors.batch_id && <p className="text-xs text-red-600">{form.errors.batch_id}</p>}
                    <select className="w-full rounded border-gray-300" value={form.data.subject_id} onChange={(e) => form.setData('subject_id', e.target.value)} required>
                        <option value="">Subject (required)</option>
                        {subjects.map((s: any) => <option key={s.id} value={s.id}>{s.name}</option>)}
                    </select>
                    {form.errors.subject_id && <p className="text-xs text-red-600">{form.errors.subject_id}</p>}
                    <label className="block text-sm text-gray-600">
                        Session date
                        <DateInput className="mt-1 w-full rounded border-gray-300" value={form.data.session_date} onChange={(v) => form.setData('session_date', v)} />
                    </label>
                    <input className="w-full rounded border-gray-300" placeholder="Topic (optional, e.g. Chapter 3)" value={form.data.topic} onChange={(e) => form.setData('topic', e.target.value)} />
                    <p className="text-xs text-gray-500">
                        Subject is required. Topic is optional — if filled, it appears on the sheet and in parent emails.
                    </p>
                    <button className="w-full rounded bg-brand-700 px-3 py-2 text-white">Create sheet</button>
                </form>
                <div className="rounded-lg bg-white shadow-sm lg:col-span-2">
                    <div className="flex items-center justify-between border-b px-4 py-3">
                        <div className="font-semibold">Class sessions</div>
                        <div className="flex items-center gap-3 text-xs">
                            <Link href={route('reports.index', { report: 'attendance' })} className="text-brand-700 underline">Attendance report</Link>
                            <Link href={route('alerts.index')} className="text-brand-700 underline">Parent alerts</Link>
                        </div>
                    </div>
                    <table className="min-w-full text-sm">
                        <thead className="bg-gray-50 text-left"><tr><th className="px-4 py-3">Date</th><th className="px-4 py-3">Batch</th><th className="px-4 py-3">Subject</th><th className="px-4 py-3">Topic</th><th className="px-4 py-3">Status</th><th className="px-4 py-3"></th></tr></thead>
                        <tbody>
                            {sessions.data.map((s: any) => (
                                <tr key={s.id} className="border-t">
                                    <td className="px-4 py-3">{formatDate(s.session_date)}</td>
                                    <td className="px-4 py-3">{s.batch?.name}</td>
                                    <td className="px-4 py-3">{s.subject?.name || '—'}</td>
                                    <td className="px-4 py-3 text-gray-600">{s.topic || '—'}</td>
                                    <td className="px-4 py-3">{s.status}</td>
                                    <td className="px-4 py-3"><Link className="text-brand-700" href={route('attendance.show', s.id)}>{s.status === 'completed' ? 'View' : 'Mark'}</Link></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
