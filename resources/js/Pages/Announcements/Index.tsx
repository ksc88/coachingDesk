import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatDate } from '@/lib/formatDate';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

export default function AnnouncementsIndex({ announcements, batches }: any) {
    const form = useForm({ title: '', body: '', scope: 'organization', batch_id: '', notify: true });

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">Coaching news</h2>}>
            <Head title="Announcements" />
            <div className="mx-auto grid max-w-7xl gap-6 px-4 py-8 lg:grid-cols-3">
                <form onSubmit={(e: FormEvent) => { e.preventDefault(); form.post(route('announcements.store'), { onSuccess: () => form.reset('title', 'body') }); }} className="space-y-3 rounded-lg bg-white p-5 shadow-sm">
                    <input className="w-full rounded border-gray-300" placeholder="Title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                    <textarea className="w-full rounded border-gray-300" rows={5} placeholder="Announcement" value={form.data.body} onChange={(e) => form.setData('body', e.target.value)} />
                    <select className="w-full rounded border-gray-300" value={form.data.scope} onChange={(e) => form.setData('scope', e.target.value)}>
                        <option value="organization">Whole coaching</option>
                        <option value="batch">Batch wise</option>
                    </select>
                    {form.data.scope === 'batch' && (
                        <select className="w-full rounded border-gray-300" value={form.data.batch_id} onChange={(e) => form.setData('batch_id', e.target.value)}>
                            <option value="">Batch</option>
                            {batches.map((b: any) => <option key={b.id} value={b.id}>{b.name}</option>)}
                        </select>
                    )}
                    <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={form.data.notify} onChange={(e) => form.setData('notify', e.target.checked)} /> Notify parents</label>
                    <button className="w-full rounded bg-brand-700 px-3 py-2 text-white">Publish</button>
                </form>
                <div className="space-y-3 lg:col-span-2">
                    {announcements.data.map((a: any) => (
                        <div key={a.id} className="rounded-lg bg-white p-4 shadow-sm">
                            <div className="font-semibold">{a.title}</div>
                            <div className="mt-1 text-sm text-gray-600">{a.body}</div>
                            <div className="mt-2 text-xs text-gray-400">{a.scope} · {formatDate(a.published_at)}</div>
                        </div>
                    ))}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
