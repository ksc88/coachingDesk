import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatDate } from '@/lib/formatDate';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEvent, useMemo, useState } from 'react';

export default function AttendanceShow({ session }: any) {
    const { flash } = usePage().props as any;
    const locked = session.status === 'completed' || (session.attendance_records || []).some((r: any) => r.is_locked);

    const initial = useMemo(
        () => (session.attendance_records || []).map((r: any) => ({
            student_id: r.student_id,
            status: r.status === 'unmarked' ? 'present' : r.status,
            remark: r.remark || '',
            name: `${r.student?.first_name || ''} ${r.student?.last_name || ''}`.trim(),
            admission_no: r.student?.admission_no,
        })),
        [session],
    );

    const [marks, setMarks] = useState(initial);
    const form = useForm({
        marks: initial,
        finalize: false,
        notify_absent: true,
        notify_present: false,
    });

    const setStatus = (studentId: number, status: string) => {
        if (locked) return;
        const next = marks.map((m: any) => (m.student_id === studentId ? { ...m, status } : m));
        setMarks(next);
        form.setData('marks', next.map(({ student_id, status, remark }: any) => ({ student_id, status, remark })));
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (locked) return;
        form.setData('marks', marks.map(({ student_id, status, remark }: any) => ({ student_id, status, remark })));
        form.post(route('attendance.mark', session.id));
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">Mark attendance · {session.batch?.name}</h2>}>
            <Head title="Mark attendance" />
            <form onSubmit={submit} className="mx-auto max-w-5xl space-y-4 px-4 py-8">
                {(flash?.success || flash?.error) && (
                    <div className={`rounded-md px-4 py-3 text-sm ${flash.error ? 'bg-red-50 text-red-800' : 'bg-emerald-50 text-emerald-800'}`}>
                        {flash.error || flash.success}
                    </div>
                )}
                <div className="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-white p-4 text-sm text-gray-600 shadow-sm">
                    <div>
                        {formatDate(session.session_date)} · {session.subject?.name || 'Class'} · {session.topic || 'No topic'}
                        {locked && <span className="ml-2 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">Locked</span>}
                    </div>
                    <Link href={route('alerts.index')} className="text-brand-700 underline">Parent alerts</Link>
                </div>
                {locked && (
                    <p className="text-sm text-amber-800">This class is finalized. Marks cannot be changed from this screen.</p>
                )}
                <div className="overflow-hidden rounded-lg bg-white shadow-sm">
                    <table className="min-w-full text-sm">
                        <thead className="bg-gray-50 text-left"><tr><th className="px-4 py-3">Student</th><th className="px-4 py-3">Status</th></tr></thead>
                        <tbody>
                            {marks.map((m: any) => (
                                <tr key={m.student_id} className="border-t">
                                    <td className="px-4 py-3">{m.admission_no} · {m.name}</td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-wrap gap-2">
                                            {['present', 'absent', 'late', 'leave'].map((status) => (
                                                <button type="button" key={status} disabled={locked} onClick={() => setStatus(m.student_id, status)}
                                                    className={`rounded px-2 py-1 text-xs disabled:opacity-50 ${m.status === status ? 'bg-brand-700 text-white' : 'bg-gray-100'}`}>
                                                    {status}
                                                </button>
                                            ))}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <div className="flex flex-wrap items-center gap-4 rounded-lg bg-white p-4 shadow-sm">
                    <label className="flex items-center gap-2 text-sm"><input type="checkbox" disabled={locked} checked={form.data.notify_absent} onChange={(e) => form.setData('notify_absent', e.target.checked)} /> Notify absent</label>
                    <label className="flex items-center gap-2 text-sm"><input type="checkbox" disabled={locked} checked={form.data.notify_present} onChange={(e) => form.setData('notify_present', e.target.checked)} /> Notify present</label>
                    <label className="flex items-center gap-2 text-sm"><input type="checkbox" disabled={locked} checked={form.data.finalize} onChange={(e) => form.setData('finalize', e.target.checked)} /> Finalize / lock</label>
                    <button disabled={locked || form.processing} className="ml-auto rounded bg-brand-700 px-4 py-2 text-white disabled:opacity-50">Save attendance</button>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
