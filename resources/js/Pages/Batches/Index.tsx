import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { FormEvent, PropsWithChildren, ReactNode, useMemo, useState } from 'react';

type Branch = { id: number; name: string; code?: string | null; can_delete?: boolean };
type Category = { id: number; name: string };
type Course = { id: number; name: string; category_id?: number | null; category?: Category | null; can_delete?: boolean };
type Subject = { id: number; name: string; code?: string | null; can_delete?: boolean };
type Batch = {
    id: number;
    name: string;
    timing?: string | null;
    weekdays?: number[] | null;
    starts_at?: string | null;
    ends_at?: string | null;
    shift?: string | null;
    default_fee?: number | string | null;
    capacity?: number | null;
    students_count?: number;
    is_active?: boolean;
    can_delete?: boolean;
    can_deactivate?: boolean;
    can_activate?: boolean;
    branch_id?: number | null;
    course_id?: number | null;
    branch?: Branch | null;
    course?: Course | null;
    subjects?: Subject[];
};

function timeInput(value?: string | null): string {
    if (!value) return '';
    return String(value).slice(0, 5);
}

const WEEKDAYS = [
    { value: 1, label: 'Mon' },
    { value: 2, label: 'Tue' },
    { value: 3, label: 'Wed' },
    { value: 4, label: 'Thu' },
    { value: 5, label: 'Fri' },
    { value: 6, label: 'Sat' },
    { value: 7, label: 'Sun' },
] as const;

const SHIFTS = [
    { value: '', label: '—' },
    { value: 'morning', label: 'Morning' },
    { value: 'afternoon', label: 'Afternoon' },
    { value: 'evening', label: 'Evening' },
] as const;

const input = 'w-full rounded border-gray-300 text-sm';

function previewSchedule(weekdays: number[], startsAt: string, endsAt: string, shift: string): string {
    const labels = WEEKDAYS.filter((d) => weekdays.includes(d.value)).map((d) => d.label);
    let days = '';
    if (labels.length >= 3) {
        const sorted = [...weekdays].sort((a, b) => a - b);
        const consecutive = sorted.every((d, i) => i === 0 || d === sorted[i - 1] + 1);
        days = consecutive ? `${labels[0]}–${labels[labels.length - 1]}` : labels.join('/');
    } else {
        days = labels.join('/');
    }

    const time = startsAt && endsAt ? `${startsAt}–${endsAt}` : (startsAt || endsAt || '');
    const shiftLabel = SHIFTS.find((s) => s.value === shift)?.label;
    return [days, time, shiftLabel && shiftLabel !== '—' ? shiftLabel : ''].filter(Boolean).join(' · ') || 'Schedule not set';
}

export default function BatchesIndex({
    branches,
    categories,
    courses,
    batches,
    subjects,
}: {
    branches: Branch[];
    categories: Category[];
    courses: Course[];
    batches: Batch[];
    subjects: Subject[];
}) {
    const { flash, tenant, errors } = usePage().props as any;
    const [tab, setTab] = useState<'batches' | 'setup'>(batches.length === 0 && courses.length === 0 ? 'setup' : 'batches');
    const [editingBatchId, setEditingBatchId] = useState<number | null>(null);

    const batchForm = useForm({
        name: '',
        branch_id: branches[0]?.id?.toString() || '',
        course_id: '',
        weekdays: [1, 2, 3, 4, 5, 6] as number[],
        starts_at: '',
        ends_at: '',
        shift: '',
        timing: '',
        default_fee: '',
        capacity: '',
        subject_ids: [] as number[],
        is_active: true as boolean,
    });

    const branchForm = useForm({ name: '', code: '', phone: '', address: '' });
    const categoryForm = useForm({ name: '', description: '' });
    const courseForm = useForm({ name: '', category_id: '', code: '', description: '' });
    const subjectForm = useForm({ name: '', code: '' });

    const singleBatchMode = Boolean(tenant?.single_batch_mode);

    const setEnrolmentRule = (single: boolean) => {
        if (single === singleBatchMode) return;

        router.post(route('academics.enrolment-rule'), { single_batch_mode: single }, { preserveScroll: true });
    };

    const resetBatchForm = () => {
        setEditingBatchId(null);
        batchForm.reset();
        batchForm.clearErrors();
        batchForm.setData({
            name: '',
            branch_id: branches[0]?.id?.toString() || '',
            course_id: '',
            weekdays: [1, 2, 3, 4, 5, 6],
            starts_at: '',
            ends_at: '',
            shift: '',
            timing: '',
            default_fee: '',
            capacity: '',
            subject_ids: [],
            is_active: true,
        });
    };

    const startEditBatch = (batch: Batch) => {
        setTab('batches');
        setEditingBatchId(batch.id);
        batchForm.setData({
            name: batch.name,
            branch_id: String(batch.branch_id || batch.branch?.id || ''),
            course_id: String(batch.course_id || batch.course?.id || ''),
            weekdays: batch.weekdays?.length ? [...batch.weekdays] : [],
            starts_at: timeInput(batch.starts_at),
            ends_at: timeInput(batch.ends_at),
            shift: batch.shift || '',
            timing: batch.timing || '',
            default_fee: batch.default_fee != null ? String(batch.default_fee) : '',
            capacity: batch.capacity != null ? String(batch.capacity) : '',
            subject_ids: (batch.subjects || []).map((s) => s.id),
            is_active: batch.is_active !== false,
        });
        batchForm.clearErrors();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const submitBatch = (e: FormEvent) => {
        e.preventDefault();
        if (editingBatchId) {
            batchForm.put(route('academics.batches.update', editingBatchId), {
                preserveScroll: true,
                onSuccess: () => resetBatchForm(),
            });
            return;
        }
        batchForm.post(route('academics.batches.store'), {
            preserveScroll: true,
            onSuccess: () => resetBatchForm(),
        });
    };

    const deactivateBatch = (batch: Batch) => {
        if (!confirm(`Deactivate "${batch.name}"? It will hide from new enrolments but keep history.`)) return;
        router.put(route('academics.batches.update', batch.id), {
            name: batch.name,
            branch_id: batch.branch_id || batch.branch?.id || '',
            course_id: batch.course_id || batch.course?.id || '',
            weekdays: batch.weekdays || [],
            starts_at: timeInput(batch.starts_at),
            ends_at: timeInput(batch.ends_at),
            shift: batch.shift || '',
            timing: batch.timing || '',
            default_fee: batch.default_fee ?? 0,
            capacity: batch.capacity ?? null,
            subject_ids: (batch.subjects || []).map((s) => s.id),
            is_active: false,
        }, { preserveScroll: true });
    };

    const activateBatch = (batch: Batch) => {
        router.put(route('academics.batches.update', batch.id), {
            name: batch.name,
            branch_id: batch.branch_id || batch.branch?.id || '',
            course_id: batch.course_id || batch.course?.id || '',
            weekdays: batch.weekdays || [],
            starts_at: timeInput(batch.starts_at),
            ends_at: timeInput(batch.ends_at),
            shift: batch.shift || '',
            timing: batch.timing || '',
            default_fee: batch.default_fee ?? 0,
            capacity: batch.capacity ?? null,
            subject_ids: (batch.subjects || []).map((s) => s.id),
            is_active: true,
        }, { preserveScroll: true });
    };

    const deleteBatch = (batch: Batch) => {
        if (!confirm(`Delete "${batch.name}"? Only empty batches with no history can be deleted.`)) return;
        router.delete(route('academics.batches.destroy', batch.id), { preserveScroll: true });
    };

    const renameItem = (kind: 'branch' | 'course' | 'subject', id: number, current: string) => {
        const name = window.prompt(`Rename ${kind}`, current)?.trim();
        if (!name || name === current) return;
        const routes = {
            branch: route('academics.branches.update', id),
            course: route('academics.courses.update', id),
            subject: route('academics.subjects.update', id),
        } as const;
        router.put(routes[kind], { name }, { preserveScroll: true });
    };

    const deleteItem = (kind: 'branch' | 'course' | 'subject', id: number, name: string, canDelete?: boolean) => {
        if (!canDelete) {
            alert(`Cannot delete "${name}" — it is still in use. See safety rules on this page.`);
            return;
        }
        if (!confirm(`Delete ${kind} "${name}"?`)) return;
        const routes = {
            branch: route('academics.branches.destroy', id),
            course: route('academics.courses.destroy', id),
            subject: route('academics.subjects.destroy', id),
        } as const;
        router.delete(routes[kind], { preserveScroll: true });
    };

    const toggleWeekday = (day: number) => {
        const current = batchForm.data.weekdays;
        batchForm.setData(
            'weekdays',
            current.includes(day) ? current.filter((d) => d !== day) : [...current, day].sort((a, b) => a - b),
        );
    };

    const toggleSubject = (id: number) => {
        const current = batchForm.data.subject_ids;
        batchForm.setData(
            'subject_ids',
            current.includes(id) ? current.filter((s) => s !== id) : [...current, id],
        );
    };

    const schedulePreview = previewSchedule(
        batchForm.data.weekdays,
        batchForm.data.starts_at,
        batchForm.data.ends_at,
        batchForm.data.shift,
    );

    const readiness = useMemo(() => ([
        { label: 'Branch', ready: branches.length > 0, hint: 'Where classes run (city / campus)' },
        { label: 'Course', ready: courses.length > 0, hint: 'What you teach (JEE, Class X Maths…)' },
        { label: 'Batch', ready: batches.length > 0, hint: 'Timing group students sit in' },
    ]), [branches.length, courses.length, batches.length]);

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h2 className="text-xl font-semibold text-gray-800">Batches</h2>
                    <p className="text-sm text-gray-500">Create the groups students join for attendance and fees.</p>
                </div>
            }
        >
            <Head title="Batches" />

            <div className="mx-auto max-w-7xl space-y-6 px-4 py-8">
                {(flash?.success || flash?.error) && (
                    <div className={'rounded-md px-4 py-3 text-sm ' + (flash.error
                        ? 'bg-red-50 text-red-800 ring-1 ring-red-200'
                        : 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200')}>
                        {flash.error || flash.success}
                    </div>
                )}

                <div className="grid gap-3 sm:grid-cols-3">
                    {readiness.map((item) => (
                        <div key={item.label} className="rounded-lg bg-white p-4 shadow-sm">
                            <div className="flex items-center justify-between">
                                <span className="text-sm font-medium text-gray-800">{item.label}</span>
                                <span className={'rounded-full px-2 py-0.5 text-xs font-medium ' + (item.ready
                                    ? 'bg-emerald-100 text-emerald-800'
                                    : 'bg-amber-100 text-amber-800')}>
                                    {item.ready ? 'Ready' : 'Needed'}
                                </span>
                            </div>
                            <p className="mt-1 text-xs text-gray-500">{item.hint}</p>
                        </div>
                    ))}
                </div>

                <div className="flex gap-2 border-b border-gray-200">
                    <TabButton active={tab === 'batches'} onClick={() => setTab('batches')}>
                        Batches ({batches.length})
                    </TabButton>
                    <TabButton active={tab === 'setup'} onClick={() => setTab('setup')}>
                        Setup branch / course / subject
                    </TabButton>
                </div>

                {tab === 'batches' ? (
                    <div className="grid gap-6 lg:grid-cols-3">
                        <form
                            onSubmit={submitBatch}
                            className="space-y-3 rounded-lg bg-white p-5 shadow-sm lg:col-span-1"
                        >
                            <div className="flex items-start justify-between gap-2">
                                <h3 className="font-semibold text-gray-900">{editingBatchId ? 'Edit batch' : 'Create batch'}</h3>
                                {editingBatchId && (
                                    <button type="button" onClick={resetBatchForm} className="text-xs text-slate-600 underline">Cancel</button>
                                )}
                            </div>
                            <p className="text-xs text-gray-500">
                                Students join a <strong>batch</strong> (not a subject). Subjects below are only what is taught inside this batch for attendance.
                            </p>
                            {errors?.batch && (
                                <p className="rounded bg-red-50 px-3 py-2 text-xs text-red-700">{errors.batch}</p>
                            )}

                            <Field label="Batch name" error={batchForm.errors.name}>
                                <input className={input} value={batchForm.data.name} onChange={(e) => batchForm.setData('name', e.target.value)} placeholder="JEE Morning" />
                            </Field>
                            <Field label="Branch" error={batchForm.errors.branch_id} hint={branches.length === 0 ? 'Create a branch in Setup first' : undefined}>
                                <select className={input} value={batchForm.data.branch_id} onChange={(e) => batchForm.setData('branch_id', e.target.value)}>
                                    <option value="">—</option>
                                    {branches.map((branch) => (
                                        <option key={branch.id} value={branch.id}>{branch.name}{branch.code ? ` (${branch.code})` : ''}</option>
                                    ))}
                                </select>
                            </Field>
                            <Field label="Course" error={batchForm.errors.course_id} hint={courses.length === 0 ? 'Create a course in Setup first (optional)' : undefined}>
                                <select className={input} value={batchForm.data.course_id} onChange={(e) => batchForm.setData('course_id', e.target.value)}>
                                    <option value="">—</option>
                                    {courses.map((course) => (
                                        <option key={course.id} value={course.id}>
                                            {course.name}{course.category?.name ? ` · ${course.category.name}` : ''}
                                        </option>
                                    ))}
                                </select>
                            </Field>

                            <div>
                                <span className="text-xs font-medium text-gray-600">Subjects taught in this batch</span>
                                <p className="text-[11px] text-gray-500">Tick all / some / none. This does not enrol students — it only lists topics for attendance.</p>
                                {subjects.length === 0 ? (
                                    <p className="mt-1 text-xs text-amber-700">No subjects yet — add them in Setup (optional).</p>
                                ) : (
                                    <div className="mt-1 flex flex-wrap gap-1.5">
                                        {subjects.map((subject) => {
                                            const on = batchForm.data.subject_ids.includes(subject.id);
                                            return (
                                                <button
                                                    key={subject.id}
                                                    type="button"
                                                    onClick={() => toggleSubject(subject.id)}
                                                    className={'rounded px-2 py-1 text-xs font-medium ' + (on
                                                        ? 'bg-brand-700 text-white'
                                                        : 'bg-gray-100 text-gray-600 hover:bg-gray-200')}
                                                >
                                                    {subject.name}
                                                </button>
                                            );
                                        })}
                                    </div>
                                )}
                                {batchForm.errors.subject_ids && <p className="mt-1 text-xs text-red-600">{batchForm.errors.subject_ids}</p>}
                            </div>

                            <div>
                                <span className="text-xs font-medium text-gray-600">Weekdays</span>
                                <div className="mt-1 flex flex-wrap gap-1.5">
                                    {WEEKDAYS.map((day) => {
                                        const on = batchForm.data.weekdays.includes(day.value);
                                        return (
                                            <button
                                                key={day.value}
                                                type="button"
                                                onClick={() => toggleWeekday(day.value)}
                                                className={'rounded px-2 py-1 text-xs font-medium ' + (on
                                                    ? 'bg-brand-700 text-white'
                                                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200')}
                                            >
                                                {day.label}
                                            </button>
                                        );
                                    })}
                                </div>
                                {batchForm.errors.weekdays && <p className="mt-1 text-xs text-red-600">{batchForm.errors.weekdays}</p>}
                            </div>

                            <div className="grid grid-cols-2 gap-2">
                                <Field label="Start time" error={batchForm.errors.starts_at}>
                                    <input type="time" className={input} value={batchForm.data.starts_at} onChange={(e) => batchForm.setData('starts_at', e.target.value)} />
                                </Field>
                                <Field label="End time" error={batchForm.errors.ends_at}>
                                    <input type="time" className={input} value={batchForm.data.ends_at} onChange={(e) => batchForm.setData('ends_at', e.target.value)} />
                                </Field>
                            </div>

                            <Field label="Shift (optional)" error={batchForm.errors.shift}>
                                <select className={input} value={batchForm.data.shift} onChange={(e) => batchForm.setData('shift', e.target.value)}>
                                    {SHIFTS.map((s) => <option key={s.value || 'none'} value={s.value}>{s.label}</option>)}
                                </select>
                            </Field>

                            <p className="rounded bg-slate-50 px-3 py-2 text-xs text-slate-700">
                                Preview: <span className="font-medium">{schedulePreview}</span>
                            </p>

                            <div className="grid grid-cols-2 gap-2">
                                <Field label="Default fee (₹)" error={batchForm.errors.default_fee}>
                                    <input type="number" min="0" step="0.01" className={input} value={batchForm.data.default_fee} onChange={(e) => batchForm.setData('default_fee', e.target.value)} />
                                </Field>
                                <Field label="Capacity" error={batchForm.errors.capacity}>
                                    <input type="number" min="1" className={input} value={batchForm.data.capacity} onChange={(e) => batchForm.setData('capacity', e.target.value)} />
                                </Field>
                            </div>

                            <div className="flex gap-2">
                                <button disabled={batchForm.processing} className="flex-1 rounded bg-brand-700 px-3 py-2 text-sm font-medium text-white disabled:opacity-50">
                                    {batchForm.processing ? 'Saving…' : (editingBatchId ? 'Save changes' : 'Create batch')}
                                </button>
                            </div>
                        </form>

                        <div className="overflow-hidden rounded-lg bg-white shadow-sm lg:col-span-2">
                            <div className="flex items-center justify-between border-b px-4 py-3">
                                <div>
                                    <h3 className="font-semibold text-gray-900">Your batches</h3>
                                    <p className="text-xs text-gray-500">Edit anytime. Delete only if empty; otherwise deactivate to keep history safe.</p>
                                </div>
                                {batches.length > 0 && (
                                    <Link href={route('students.index')} className="text-sm text-brand-700 underline">
                                        Assign students
                                    </Link>
                                )}
                            </div>

                            {batches.length === 0 ? (
                                <div className="px-4 py-12 text-center text-sm text-gray-500">
                                    <p className="font-medium text-gray-700">No batches yet</p>
                                    <p className="mt-1">Create one on the left. If Branch/Course are missing, open the Setup tab first.</p>
                                    <button type="button" onClick={() => setTab('setup')} className="mt-4 text-brand-700 underline">
                                        Go to Setup
                                    </button>
                                </div>
                            ) : (
                                <table className="min-w-full text-sm">
                                    <thead className="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                                        <tr>
                                            <th className="px-4 py-3">Batch</th>
                                            <th className="px-4 py-3">Branch</th>
                                            <th className="px-4 py-3">Course</th>
                                            <th className="px-4 py-3">Students</th>
                                            <th className="px-4 py-3">Fee</th>
                                            <th className="px-4 py-3">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {batches.map((batch) => (
                                            <tr key={batch.id} className={'border-t ' + (batch.is_active === false ? 'bg-slate-50 opacity-80' : '')}>
                                                <td className="px-4 py-3">
                                                    <div className="font-medium text-gray-900">
                                                        {batch.name}
                                                        {batch.is_active === false && (
                                                            <span className="ml-2 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-medium uppercase text-amber-800">Inactive</span>
                                                        )}
                                                    </div>
                                                    <div className="text-xs text-gray-500">{batch.timing || 'Timing not set'}</div>
                                                    {batch.subjects && batch.subjects.length > 0 && (
                                                        <div className="mt-1 text-xs text-gray-500">
                                                            Subjects: {batch.subjects.map((s) => s.name).join(', ')}
                                                        </div>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3">{batch.branch?.name || '—'}</td>
                                                <td className="px-4 py-3">{batch.course?.name || '—'}</td>
                                                <td className="px-4 py-3">
                                                    {batch.students_count ?? 0}
                                                    {batch.capacity ? <span className="text-xs text-gray-500"> / {batch.capacity}</span> : null}
                                                </td>
                                                <td className="px-4 py-3">₹{Number(batch.default_fee || 0).toLocaleString('en-IN')}</td>
                                                <td className="px-4 py-3">
                                                    <div className="flex flex-wrap gap-2 text-xs">
                                                        <button type="button" onClick={() => startEditBatch(batch)} className="text-brand-700 underline">Edit</button>
                                                        {batch.is_active !== false ? (
                                                            <button type="button" onClick={() => deactivateBatch(batch)} className="text-amber-700 underline">Deactivate</button>
                                                        ) : (
                                                            <button type="button" onClick={() => activateBatch(batch)} className="text-emerald-700 underline">Activate</button>
                                                        )}
                                                        <button
                                                            type="button"
                                                            onClick={() => deleteBatch(batch)}
                                                            className={batch.can_delete ? 'text-red-700 underline' : 'text-gray-400'}
                                                            title={batch.can_delete ? 'Delete permanently' : 'Blocked: students or history exist — deactivate instead'}
                                                        >
                                                            Delete
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </div>
                    </div>
                ) : (
                    <div className="space-y-4">
                        <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                            Setup order: <strong>Branch</strong> → <strong>Category</strong> (optional) → <strong>Course</strong> → <strong>Subject</strong> → then create a <strong>Batch</strong>.
                        </div>

                        <div className="rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-950">
                            <strong>Remember:</strong> a student joins a <strong>batch</strong>. Subjects are topics inside that batch.
                            “One / several” below means one or several <strong>batches</strong> — not subjects.
                        </div>

                        <div className="rounded-lg bg-white p-5 shadow-sm">
                            <h3 className="font-semibold text-gray-900">How many batches can one student join?</h3>
                            <p className="mt-1 text-xs text-gray-500">
                                This is only about batches. Subjects (Physics, Maths…) are chosen when you create each batch.
                            </p>
                            <div className="mt-3 space-y-2">
                                <RuleOption
                                    checked={singleBatchMode}
                                    onSelect={() => setEnrolmentRule(true)}
                                    title="One batch per student"
                                    description="School style: child is only in Class X A. If you assign Class X B, they leave X A. Subjects inside that one batch can still be Physics+Chem+Maths (all or some)."
                                />
                                <RuleOption
                                    checked={!singleBatchMode}
                                    onSelect={() => setEnrolmentRule(false)}
                                    title="Several batches per student"
                                    description="Subject-wise style: child can be in a Maths batch and a Test series at the same time (two enrolments)."
                                />
                            </div>
                        </div>

                        {(errors?.branch || errors?.course || errors?.subject) && (
                            <div className="rounded-md bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200">
                                {errors.branch || errors.course || errors.subject}
                            </div>
                        )}

                        <div className="grid gap-4 lg:grid-cols-2">
                            <SetupCard
                                title="1. Branch"
                                description="Campus / city location. You already have one if onboarding created Main Campus."
                                items={branches.map((b) => ({
                                    id: b.id,
                                    label: `${b.name}${b.code ? ` (${b.code})` : ''}`,
                                    canDelete: b.can_delete,
                                    onRename: () => renameItem('branch', b.id, b.name),
                                    onDelete: () => deleteItem('branch', b.id, b.name, b.can_delete),
                                }))}
                                form={
                                    <form onSubmit={(e) => { e.preventDefault(); branchForm.post(route('academics.branches.store'), { preserveScroll: true, onSuccess: () => branchForm.reset() }); }} className="space-y-2">
                                        <input className={input} placeholder="Name" value={branchForm.data.name} onChange={(e) => branchForm.setData('name', e.target.value)} />
                                        <input className={input} placeholder="Code (unique, optional)" value={branchForm.data.code} onChange={(e) => branchForm.setData('code', e.target.value)} />
                                        {branchForm.errors.code && <p className="text-xs text-red-600">{branchForm.errors.code}</p>}
                                        <button className="rounded bg-brand-700 px-3 py-1.5 text-sm text-white">Add branch</button>
                                    </form>
                                }
                            />

                            <SetupCard
                                title="2. Category"
                                description="Optional grouping, e.g. Competition, School, Spoken English."
                                items={categories.map((c) => ({ id: c.id, label: c.name }))}
                                form={
                                    <form onSubmit={(e) => { e.preventDefault(); categoryForm.post(route('academics.categories.store'), { preserveScroll: true, onSuccess: () => categoryForm.reset() }); }} className="space-y-2">
                                        <input className={input} placeholder="Name" value={categoryForm.data.name} onChange={(e) => categoryForm.setData('name', e.target.value)} />
                                        <button className="rounded bg-brand-700 px-3 py-1.5 text-sm text-white">Add category</button>
                                    </form>
                                }
                            />

                            <SetupCard
                                title="3. Course"
                                description="What you teach, e.g. JEE Main, Class X Maths, NEET. Cannot delete while batches use it."
                                items={courses.map((c) => ({
                                    id: c.id,
                                    label: `${c.name}${c.category?.name ? ` · ${c.category.name}` : ''}`,
                                    canDelete: c.can_delete,
                                    onRename: () => renameItem('course', c.id, c.name),
                                    onDelete: () => deleteItem('course', c.id, c.name, c.can_delete),
                                }))}
                                form={
                                    <form onSubmit={(e) => { e.preventDefault(); courseForm.post(route('academics.courses.store'), { preserveScroll: true, onSuccess: () => courseForm.reset() }); }} className="space-y-2">
                                        <input className={input} placeholder="Course name" value={courseForm.data.name} onChange={(e) => courseForm.setData('name', e.target.value)} />
                                        <select className={input} value={courseForm.data.category_id} onChange={(e) => courseForm.setData('category_id', e.target.value)}>
                                            <option value="">Category (optional)</option>
                                            {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                                        </select>
                                        <button className="rounded bg-brand-700 px-3 py-1.5 text-sm text-white">Add course</button>
                                    </form>
                                }
                            />

                            <SetupCard
                                title="4. Subject (topic list)"
                                description="Shared topic names. Cannot delete if linked to a batch or teacher."
                                items={subjects.map((s) => ({
                                    id: s.id,
                                    label: s.name,
                                    canDelete: s.can_delete,
                                    onRename: () => renameItem('subject', s.id, s.name),
                                    onDelete: () => deleteItem('subject', s.id, s.name, s.can_delete),
                                }))}
                                form={
                                    <form onSubmit={(e) => { e.preventDefault(); subjectForm.post(route('academics.subjects.store'), { preserveScroll: true, onSuccess: () => subjectForm.reset() }); }} className="space-y-2">
                                        <input className={input} placeholder="Subject name" value={subjectForm.data.name} onChange={(e) => subjectForm.setData('name', e.target.value)} />
                                        <button className="rounded bg-brand-700 px-3 py-1.5 text-sm text-white">Add subject</button>
                                    </form>
                                }
                            />
                        </div>

                        <div className="text-center">
                            <button type="button" onClick={() => setTab('batches')} className="rounded bg-slate-900 px-4 py-2 text-sm text-white">
                                Done with setup — create a batch
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}

function TabButton({ active, onClick, children }: PropsWithChildren<{ active: boolean; onClick: () => void }>) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={
                'border-b-2 px-4 py-2 text-sm font-medium ' +
                (active ? 'border-brand-600 text-brand-800' : 'border-transparent text-gray-500 hover:text-gray-800')
            }
        >
            {children}
        </button>
    );
}

function Field({ label, error, hint, children }: PropsWithChildren<{ label: string; error?: string; hint?: string }>) {
    return (
        <label className="block">
            <span className="text-xs font-medium text-gray-600">{label}</span>
            <div className="mt-1">{children}</div>
            {hint && !error && <span className="text-xs text-amber-700">{hint}</span>}
            {error && <span className="text-xs text-red-600">{error}</span>}
        </label>
    );
}

function RuleOption({
    checked,
    onSelect,
    title,
    description,
}: {
    checked: boolean;
    onSelect: () => void;
    title: string;
    description: string;
}) {
    return (
        <label
            className={'flex cursor-pointer gap-3 rounded-lg border p-3 ' + (checked
                ? 'border-brand-500 bg-brand-50'
                : 'border-gray-200 hover:border-gray-300')}
        >
            <input type="radio" name="enrolment-rule" className="mt-1" checked={checked} onChange={onSelect} />
            <span>
                <span className="block text-sm font-medium text-gray-900">{title}</span>
                <span className="block text-xs text-gray-600">{description}</span>
            </span>
        </label>
    );
}

function SetupCard({
    title,
    description,
    items,
    form,
}: {
    title: string;
    description: string;
    items: { id: number; label: string; canDelete?: boolean; onRename?: () => void; onDelete?: () => void }[];
    form: ReactNode;
}) {
    return (
        <div className="rounded-lg bg-white p-4 shadow-sm">
            <h3 className="font-semibold text-gray-900">{title}</h3>
            <p className="mt-1 text-xs text-gray-500">{description}</p>
            <div className="mt-3">{form}</div>
            <ul className="mt-4 max-h-48 space-y-1 overflow-y-auto border-t pt-3 text-sm text-gray-700">
                {items.length === 0 && <li className="text-gray-400">None yet</li>}
                {items.map((item) => (
                    <li key={item.id} className="flex items-center justify-between gap-2 border-t py-1.5 first:border-0">
                        <span>{item.label}</span>
                        {(item.onRename || item.onDelete) && (
                            <span className="flex shrink-0 gap-2 text-xs">
                                {item.onRename && (
                                    <button type="button" onClick={item.onRename} className="text-brand-700 underline">Rename</button>
                                )}
                                {item.onDelete && (
                                    <button
                                        type="button"
                                        onClick={item.onDelete}
                                        className={item.canDelete ? 'text-red-700 underline' : 'text-gray-400'}
                                        title={item.canDelete ? 'Delete' : 'In use — cannot delete'}
                                    >
                                        Delete
                                    </button>
                                )}
                            </span>
                        )}
                    </li>
                ))}
            </ul>
        </div>
    );
}
