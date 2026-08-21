import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

export default function StaffIndex({ staff, assignments, batches, subjects }: any) {
    const form = useForm({ name: '', email: '', phone: '', role_label: 'teacher', password: 'password' });
    const assignForm = useForm({ user_id: '', batch_id: '', subject_id: '', role: 'teacher' });

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">Teacher / staff management</h2>}>
            <Head title="Staff" />
            <div className="mx-auto grid max-w-7xl gap-6 px-4 py-8 lg:grid-cols-2">
                <form onSubmit={(e: FormEvent) => { e.preventDefault(); form.post(route('staff.store'), { onSuccess: () => form.reset('name', 'email', 'phone') }); }} className="space-y-2 rounded-lg bg-white p-4 shadow-sm">
                    <h3 className="font-semibold">Add staff</h3>
                    <input className="w-full rounded border-gray-300" placeholder="Name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                    <input className="w-full rounded border-gray-300" placeholder="Email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
                    <input className="w-full rounded border-gray-300" placeholder="Phone" value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} />
                    <select className="w-full rounded border-gray-300" value={form.data.role_label} onChange={(e) => form.setData('role_label', e.target.value)}>
                        {['owner', 'branch_manager', 'teacher', 'accountant', 'receptionist'].map((r) => <option key={r} value={r}>{r}</option>)}
                    </select>
                    <input className="w-full rounded border-gray-300" type="password" placeholder="Password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} />
                    <button className="rounded bg-brand-700 px-3 py-2 text-white">Add</button>
                </form>

                <form onSubmit={(e: FormEvent) => { e.preventDefault(); assignForm.post(route('staff.assign')); }} className="space-y-2 rounded-lg bg-white p-4 shadow-sm">
                    <h3 className="font-semibold">Assign to batch/subject</h3>
                    <select className="w-full rounded border-gray-300" value={assignForm.data.user_id} onChange={(e) => assignForm.setData('user_id', e.target.value)}>
                        <option value="">Staff</option>
                        {staff.map((s: any) => <option key={s.id} value={s.id}>{s.name}</option>)}
                    </select>
                    <select className="w-full rounded border-gray-300" value={assignForm.data.batch_id} onChange={(e) => assignForm.setData('batch_id', e.target.value)}>
                        <option value="">Batch</option>
                        {batches.map((b: any) => <option key={b.id} value={b.id}>{b.name}</option>)}
                    </select>
                    <select className="w-full rounded border-gray-300" value={assignForm.data.subject_id} onChange={(e) => assignForm.setData('subject_id', e.target.value)}>
                        <option value="">Subject</option>
                        {subjects.map((s: any) => <option key={s.id} value={s.id}>{s.name}</option>)}
                    </select>
                    <button className="rounded bg-brand-700 px-3 py-2 text-white">Assign</button>
                </form>

                <div className="rounded-lg bg-white p-4 shadow-sm">
                    <h3 className="font-semibold">Staff directory</h3>
                    <ul className="mt-3 space-y-2 text-sm">{staff.map((s: any) => <li key={s.id} className="border-t py-2">{s.name} · {s.role_label} · {s.email}</li>)}</ul>
                </div>
                <div className="rounded-lg bg-white p-4 shadow-sm">
                    <h3 className="font-semibold">Assignments</h3>
                    <ul className="mt-3 space-y-2 text-sm">{assignments.map((a: any) => <li key={a.id} className="border-t py-2">{a.user?.name} → {a.batch?.name || '—'} / {a.subject?.name || '—'}</li>)}</ul>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
