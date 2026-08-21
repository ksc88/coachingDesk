import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

const SOURCES: Record<string, string> = {
    'walk-in': 'Walk-in',
    landing_page: 'Landing page',
    referral: 'Referral',
    phone: 'Phone',
    other: 'Other',
};

const STATUSES: Record<string, string> = {
    new: 'New',
    contacted: 'Contacted',
    interested: 'Interested',
    demo_scheduled: 'Demo scheduled',
    admitted: 'Admitted',
    lost: 'Lost',
};

const VIEWS: { key: string; label: string }[] = [
    { key: 'open', label: 'Open' },
    { key: 'new', label: 'New' },
    { key: 'contacted', label: 'Contacted' },
    { key: 'interested', label: 'Interested' },
    { key: 'demo_scheduled', label: 'Demo' },
    { key: 'admitted', label: 'Admitted' },
    { key: 'lost', label: 'Lost' },
    { key: 'all', label: 'All' },
];

export default function EnquiriesIndex({ enquiries, courses, batches, filters, counts }: any) {
    const [q, setQ] = useState(filters?.q || '');
    const createForm = useForm({
        name: '',
        phone: '',
        email: '',
        source: 'walk-in',
        course_id: '',
        batch_id: '',
        notes: '',
        next_follow_up_at: '',
    });

    const applyFilters = (view: string, search = q) => {
        router.get(
            route('enquiries.index'),
            { view, q: search || undefined },
            { preserveState: true, replace: true },
        );
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">Enquiry CRM</h2>}>
            <Head title="Enquiries" />
            <div className="mx-auto grid max-w-7xl gap-6 px-4 py-8 lg:grid-cols-3">
                <form
                    onSubmit={(e: FormEvent) => {
                        e.preventDefault();
                        createForm.post(route('enquiries.store'), { onSuccess: () => createForm.reset() });
                    }}
                    className="h-fit space-y-2 rounded-lg bg-white p-4 shadow-sm"
                >
                    <h3 className="font-semibold">New enquiry</h3>
                    <input className="w-full rounded border-gray-300" placeholder="Name" value={createForm.data.name} onChange={(e) => createForm.setData('name', e.target.value)} />
                    {createForm.errors.name && <p className="text-xs text-red-600">{createForm.errors.name}</p>}
                    <input className="w-full rounded border-gray-300" placeholder="Phone" value={createForm.data.phone} onChange={(e) => createForm.setData('phone', e.target.value)} />
                    {createForm.errors.phone && <p className="text-xs text-red-600">{createForm.errors.phone}</p>}
                    <input className="w-full rounded border-gray-300" placeholder="Email" value={createForm.data.email} onChange={(e) => createForm.setData('email', e.target.value)} />
                    <select className="w-full rounded border-gray-300" value={createForm.data.source} onChange={(e) => createForm.setData('source', e.target.value)}>
                        {Object.entries(SOURCES).map(([value, label]) => (
                            <option key={value} value={value}>{label}</option>
                        ))}
                    </select>
                    <select className="w-full rounded border-gray-300" value={createForm.data.batch_id} onChange={(e) => createForm.setData('batch_id', e.target.value)}>
                        <option value="">Batch interest</option>
                        {batches.map((b: any) => <option key={b.id} value={b.id}>{b.name}</option>)}
                    </select>
                    {batches.length === 0 && (
                        <select className="w-full rounded border-gray-300" value={createForm.data.course_id} onChange={(e) => createForm.setData('course_id', e.target.value)}>
                            <option value="">Course</option>
                            {courses.map((c: any) => <option key={c.id} value={c.id}>{c.name}</option>)}
                        </select>
                    )}
                    <button className="w-full rounded bg-brand-700 px-3 py-2 text-white">Save</button>
                </form>

                <div className="space-y-3 lg:col-span-2">
                    <div className="rounded-lg bg-white p-3 shadow-sm">
                        <div className="flex flex-wrap gap-2">
                            {VIEWS.map((tab) => {
                                const active = (filters?.view || 'open') === tab.key;
                                const count = counts?.[tab.key] ?? 0;
                                return (
                                    <button
                                        key={tab.key}
                                        type="button"
                                        onClick={() => applyFilters(tab.key)}
                                        className={`rounded-full px-3 py-1 text-sm ${active ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'}`}
                                    >
                                        {tab.label}
                                        <span className={`ml-1 ${active ? 'text-slate-300' : 'text-slate-500'}`}>{count}</span>
                                    </button>
                                );
                            })}
                        </div>
                        <form
                            className="mt-3 flex gap-2"
                            onSubmit={(e: FormEvent) => {
                                e.preventDefault();
                                applyFilters(filters?.view || 'open', q);
                            }}
                        >
                            <input
                                className="w-full rounded border-gray-300"
                                placeholder="Search name or phone"
                                value={q}
                                onChange={(e) => setQ(e.target.value)}
                            />
                            <button type="submit" className="rounded bg-slate-800 px-4 py-2 text-sm text-white">Search</button>
                        </form>
                        <p className="mt-2 text-xs text-gray-500">
                            Default shows <strong>Open</strong> leads only (not admitted/lost). 15 per page — never all 1000 at once.
                        </p>
                    </div>

                    {enquiries.data.length === 0 && (
                        <div className="rounded-lg bg-white p-8 text-center text-sm text-gray-500 shadow-sm">
                            No enquiries in this view. Try <button type="button" className="text-brand-700 underline" onClick={() => applyFilters('all')}>All</button> or clear search.
                        </div>
                    )}

                    {enquiries.data.map((enquiry: any) => (
                        <EnquiryCard key={enquiry.id} enquiry={enquiry} batches={batches} />
                    ))}

                    {(enquiries.prev_page_url || enquiries.next_page_url) && (
                        <div className="flex items-center justify-between rounded-lg bg-white px-4 py-3 text-sm shadow-sm">
                            <span className="text-gray-500">
                                Page {enquiries.current_page} of {enquiries.last_page} · {enquiries.total} total
                            </span>
                            <div className="flex gap-2">
                                {enquiries.prev_page_url ? (
                                    <Link href={enquiries.prev_page_url} preserveScroll className="rounded bg-slate-100 px-3 py-1.5 hover:bg-slate-200">Previous</Link>
                                ) : (
                                    <span className="rounded bg-slate-50 px-3 py-1.5 text-gray-400">Previous</span>
                                )}
                                {enquiries.next_page_url ? (
                                    <Link href={enquiries.next_page_url} preserveScroll className="rounded bg-slate-100 px-3 py-1.5 hover:bg-slate-200">Next</Link>
                                ) : (
                                    <span className="rounded bg-slate-50 px-3 py-1.5 text-gray-400">Next</span>
                                )}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function EnquiryCard({ enquiry, batches }: any) {
    const followForm = useForm({ notes: '', status: enquiry.status, next_follow_up_at: '', type: 'call' });
    const convertForm = useForm({
        admission_no: '',
        batch_id: enquiry.batch_id || '',
        guardian_name: '',
        guardian_phone: enquiry.phone,
        whatsapp_opt_in: Boolean(enquiry.whatsapp_opt_in),
    });

    const sourceLabel = SOURCES[enquiry.source] || enquiry.source || '—';
    const statusLabel = STATUSES[enquiry.status] || enquiry.status;
    const canConvert = enquiry.status !== 'admitted';

    return (
        <div className="rounded-lg bg-white p-4 shadow-sm">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <div className="font-semibold">{enquiry.name} · {enquiry.phone}</div>
                    <div className="text-sm text-gray-500">
                        {statusLabel} · {sourceLabel}
                        {enquiry.batch?.name ? ` · ${enquiry.batch.name}` : ''}
                        {enquiry.course?.name && !enquiry.batch?.name ? ` · ${enquiry.course.name}` : ''}
                    </div>
                    {(enquiry.whatsapp_opt_in || enquiry.sms_opt_in) && (
                        <div className="mt-1 text-xs text-brand-700">WhatsApp/SMS updates: opted in</div>
                    )}
                    {enquiry.notes && <p className="mt-1 text-sm text-gray-600">{enquiry.notes}</p>}
                </div>
            </div>
            <form
                onSubmit={(e: FormEvent) => {
                    e.preventDefault();
                    followForm.post(route('enquiries.followup', enquiry.id), { preserveScroll: true });
                }}
                className="mt-3 grid gap-2 md:grid-cols-4"
            >
                <input className="rounded border-gray-300 md:col-span-2" placeholder="Follow-up notes (optional if only changing status)" value={followForm.data.notes} onChange={(e) => followForm.setData('notes', e.target.value)} />
                <select className="rounded border-gray-300" value={followForm.data.status} onChange={(e) => followForm.setData('status', e.target.value)}>
                    {Object.entries(STATUSES).map(([value, label]) => (
                        <option key={value} value={value}>{label}</option>
                    ))}
                </select>
                <button className="rounded bg-slate-800 px-3 py-2 text-sm text-white">Follow up</button>
                {followForm.errors.notes && <p className="text-xs text-red-600 md:col-span-4">{followForm.errors.notes}</p>}
            </form>
            {canConvert && (
                <form
                    onSubmit={(e: FormEvent) => {
                        e.preventDefault();
                        convertForm.post(route('enquiries.convert', enquiry.id), { preserveScroll: true });
                    }}
                    className="mt-2 grid gap-2 md:grid-cols-4"
                >
                    <input className="rounded border-gray-300" placeholder="Admission no" value={convertForm.data.admission_no} onChange={(e) => convertForm.setData('admission_no', e.target.value)} />
                    <select className="rounded border-gray-300" value={convertForm.data.batch_id} onChange={(e) => convertForm.setData('batch_id', e.target.value)}>
                        <option value="">Batch</option>
                        {batches.map((b: any) => <option key={b.id} value={b.id}>{b.name}</option>)}
                    </select>
                    <input className="rounded border-gray-300" placeholder="Guardian" value={convertForm.data.guardian_name} onChange={(e) => convertForm.setData('guardian_name', e.target.value)} />
                    <button className="rounded bg-brand-700 px-3 py-2 text-sm text-white">Convert to admission</button>
                </form>
            )}
        </div>
    );
}
