import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

export default function NotesIndex({ notes, batches, subjects }: any) {
    const form = useForm({
        title: '',
        description: '',
        batch_id: '',
        subject_id: '',
        external_url: '',
        file: null as File | null,
        is_published: true,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(route('notes.store'), { forceFormData: true, onSuccess: () => form.reset('title', 'description', 'external_url', 'file') });
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">Shared notes</h2>}>
            <Head title="Notes" />
            <div className="mx-auto grid max-w-7xl gap-6 px-4 py-8 lg:grid-cols-3">
                <form onSubmit={submit} className="space-y-3 rounded-lg bg-white p-5 shadow-sm">
                    <input className="w-full rounded border-gray-300" placeholder="Title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                    <textarea className="w-full rounded border-gray-300" rows={3} placeholder="Description" value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                    <select className="w-full rounded border-gray-300" value={form.data.batch_id} onChange={(e) => form.setData('batch_id', e.target.value)}>
                        <option value="">Batch</option>
                        {batches.map((b: any) => <option key={b.id} value={b.id}>{b.name}</option>)}
                    </select>
                    <select className="w-full rounded border-gray-300" value={form.data.subject_id} onChange={(e) => form.setData('subject_id', e.target.value)}>
                        <option value="">Subject</option>
                        {subjects.map((s: any) => <option key={s.id} value={s.id}>{s.name}</option>)}
                    </select>
                    <input className="w-full rounded border-gray-300" placeholder="External URL" value={form.data.external_url} onChange={(e) => form.setData('external_url', e.target.value)} />
                    <input type="file" onChange={(e) => form.setData('file', e.target.files?.[0] || null)} />
                    <button className="w-full rounded bg-brand-700 px-3 py-2 text-white">Share note</button>
                </form>
                <div className="space-y-3 lg:col-span-2">
                    {notes.data.map((n: any) => (
                        <div key={n.id} className="rounded-lg bg-white p-4 shadow-sm">
                            <div className="font-semibold">{n.title}</div>
                            <div className="text-sm text-gray-600">{n.description}</div>
                            <div className="mt-2 text-xs text-gray-400">{n.batch?.name || 'All'} · {n.subject?.name || 'General'}</div>
                            {n.external_url && <a className="text-sm text-brand-700" href={n.external_url} target="_blank">Open link</a>}
                        </div>
                    ))}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
