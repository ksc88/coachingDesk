import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import DateInput from '@/Components/DateInput';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { FormEvent, PropsWithChildren, useState } from 'react';

type Student = {
    id: number;
    admission_no: string;
    first_name: string;
    last_name?: string;
    class_level?: string;
    school_name?: string;
    target_exam_year?: string;
    date_of_birth?: string;
    gender?: string;
    phone?: string;
    email?: string;
    address?: string;
    source?: string;
    remarks?: string;
    joined_on?: string;
    status?: string;
    can_delete?: boolean;
    enrolments?: {
        id: number;
        fee_style?: string | null;
        fee_amount?: number | string | null;
        fee_installments?: number | null;
        fee_due_day?: number | null;
        fee_first_due_date?: string | null;
        batch?: { id: number; name: string; default_fee?: number | string | null };
    }[];
    guardians?: { name: string; phone: string; relation?: string; alternate_phone?: string; occupation?: string; email?: string; whatsapp_opt_in?: boolean; sms_opt_in?: boolean }[];
};

const FEE_STYLES = [
    { value: 'monthly', label: 'Monthly' },
    { value: 'term', label: 'Term / lump sum' },
    { value: 'installments', label: 'Instalments' },
    { value: 'custom', label: 'One-time / custom' },
] as const;

const feeAmountField = (style: string) => {
    switch (style) {
        case 'term':
            return { label: 'Term fee amount', hint: 'Total lump-sum fee for the term' };
        case 'installments':
            return { label: 'Total fee (split into instalments)', hint: 'Full amount to be split across instalments' };
        case 'custom':
            return { label: 'Fee amount', hint: 'One-time or custom fee amount' };
        default:
            return { label: 'Monthly tuition', hint: 'Regular class fee per month' };
    }
};

const DUE_DAYS = Array.from({ length: 28 }, (_, i) => i + 1);

const input = 'w-full rounded border-gray-300 text-sm';

export default function StudentsIndex({
    students,
    batches,
    filters,
    classLevels,
    nextAdmissionNo,
}: {
    students: { data: Student[] };
    batches: { id: number; name: string; default_fee?: number | string | null }[];
    filters: { search?: string; class_level?: string; batch_id?: string; status?: string };
    classLevels: string[];
    nextAdmissionNo: string;
}) {
    const [search, setSearch] = useState(filters.search || '');
    const [selected, setSelected] = useState<number[]>([]);
    const [editingId, setEditingId] = useState<number | null>(null);

    const form = useForm({
        admission_no: '',
        first_name: '',
        last_name: '',
        class_level: '',
        school_name: '',
        target_exam_year: '',
        date_of_birth: '',
        gender: '',
        phone: '',
        email: '',
        address: '',
        source: '',
        remarks: '',
        joined_on: '',
        batch_id: '',
        fee_style: 'monthly',
        fee_amount: '',
        fee_installments: '3',
        fee_due_day: '5',
        fee_first_due_date: '',
        admission_fee: '',
        raise_first_invoice: true as boolean,
        guardian_name: '',
        guardian_relation: 'father',
        guardian_occupation: '',
        guardian_phone: '',
        guardian_alternate_phone: '',
        guardian_email: '',
        whatsapp_opt_in: true as boolean,
        sms_opt_in: true as boolean,
    });

    const { tenant, errors } = usePage().props as any;
    const singleBatchMode = Boolean(tenant?.single_batch_mode);

    const importForm = useForm({ file: null as File | null });
    const bulkForm = useForm({
        student_ids: [] as number[],
        batch_id: '',
        mode: 'add' as 'add' | 'move',
    });

    const resetAdmitForm = () => {
        setEditingId(null);
        form.reset();
        form.clearErrors();
        form.setData('guardian_relation', 'father');
        form.setData('whatsapp_opt_in', true);
        form.setData('sms_opt_in', true);
        form.setData('fee_style', 'monthly');
        form.setData('fee_installments', '3');
        form.setData('fee_due_day', '5');
        form.setData('fee_first_due_date', '');
        form.setData('admission_fee', '');
        form.setData('raise_first_invoice', true);
    };

    const applyBatchFeeDefaults = (batchId: string) => {
        form.setData('batch_id', batchId);
        const batch = batches.find((b) => String(b.id) === String(batchId));
        if (batch?.default_fee != null && batch.default_fee !== '') {
            form.setData('fee_amount', String(batch.default_fee));
        }
        if (!form.data.fee_style) {
            form.setData('fee_style', 'monthly');
        }
    };

    const alertChannelFor = (student: Student): string => {
        const g = student.guardians?.[0];
        if (!g) return 'None';
        if (g.whatsapp_opt_in && g.phone) return 'WhatsApp';
        if (g.sms_opt_in && g.phone) return 'SMS';
        if (g.email) return 'Email';
        return 'None';
    };

    const startEdit = (student: Student) => {
        const g = student.guardians?.[0];
        const enrolment = student.enrolments?.[0];
        setEditingId(student.id);
        form.setData({
            admission_no: student.admission_no || '',
            first_name: student.first_name || '',
            last_name: student.last_name || '',
            class_level: student.class_level || '',
            school_name: student.school_name || '',
            target_exam_year: student.target_exam_year || '',
            date_of_birth: student.date_of_birth ? String(student.date_of_birth).slice(0, 10) : '',
            gender: student.gender || '',
            phone: student.phone || '',
            email: student.email || '',
            address: student.address || '',
            source: student.source || '',
            remarks: student.remarks || '',
            joined_on: student.joined_on ? String(student.joined_on).slice(0, 10) : '',
            batch_id: enrolment?.batch?.id ? String(enrolment.batch.id) : '',
            fee_style: enrolment?.fee_style || 'monthly',
            fee_amount: enrolment?.fee_amount != null ? String(enrolment.fee_amount) : (enrolment?.batch?.default_fee != null ? String(enrolment.batch.default_fee) : ''),
            fee_installments: enrolment?.fee_installments ? String(enrolment.fee_installments) : '3',
            fee_due_day: enrolment?.fee_due_day != null ? String(enrolment.fee_due_day) : '5',
            fee_first_due_date: enrolment?.fee_first_due_date ? String(enrolment.fee_first_due_date).slice(0, 10) : '',
            admission_fee: '',
            raise_first_invoice: false,
            guardian_name: g?.name || '',
            guardian_relation: (g?.relation as string) || 'father',
            guardian_occupation: g?.occupation || '',
            guardian_phone: g?.phone || '',
            guardian_alternate_phone: g?.alternate_phone || '',
            guardian_email: g?.email || '',
            whatsapp_opt_in: g?.whatsapp_opt_in === true,
            sms_opt_in: g?.sms_opt_in === true,
        });
        form.clearErrors();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const savedId = editingId;
        form.transform((data) => ({
            ...data,
            whatsapp_opt_in: data.whatsapp_opt_in ? 1 : 0,
            sms_opt_in: data.sms_opt_in ? 1 : 0,
        }));

        if (savedId) {
            form.put(route('students.update', savedId), {
                preserveScroll: true,
                onSuccess: (page) => {
                    form.transform((data) => data);
                    const list = ((page.props as any).students?.data || []) as Student[];
                    const updated = list.find((s) => s.id === savedId);
                    if (updated) {
                        startEdit(updated);
                    } else {
                        setEditingId(null);
                    }
                },
                onFinish: () => form.transform((data) => data),
            });
            return;
        }

        form.post(route('students.store'), {
            preserveScroll: true,
            onSuccess: () => resetAdmitForm(),
            onFinish: () => form.transform((data) => data),
        });
    };

    const markLeft = (student: Student) => {
        if (!confirm(`Mark ${student.first_name} as left? They leave active batches; fees/attendance stay.`)) return;
        router.post(route('students.status', student.id), { status: 'left' }, { preserveScroll: true });
    };

    const reactivate = (student: Student) => {
        router.post(route('students.status', student.id), { status: 'active' }, { preserveScroll: true });
    };

    const deleteStudent = (student: Student) => {
        if (!student.can_delete) {
            alert(`Cannot delete ${student.first_name}: fees or attendance exist. Mark as Left instead.`);
            return;
        }
        if (!confirm(`Delete ${student.first_name}? Only for mistakes with no fees/attendance.`)) return;
        router.delete(route('students.destroy', student.id), { preserveScroll: true });
    };

    const submitImport = (e: FormEvent) => {
        e.preventDefault();
        importForm.post(route('students.import'), {
            forceFormData: true,
            onSuccess: () => importForm.reset('file'),
        });
    };

    const applyFilters = (next: Partial<{ search: string; class_level: string; batch_id: string; status: string }>) => {
        setSelected([]);
        router.get(
            route('students.index'),
            {
                search,
                class_level: filters.class_level || '',
                batch_id: filters.batch_id || '',
                status: filters.status || 'active',
                ...next,
            },
            { preserveState: true, replace: true },
        );
    };

    const assignBatch = () => {
        bulkForm.transform((data) => ({ ...data, student_ids: selected }));
        bulkForm.post(route('students.bulk-enrol'), {
            preserveScroll: true,
            onSuccess: () => {
                setSelected([]);
                bulkForm.reset();
            },
        });
    };

    const removeFromBatch = (student: Student, batch: { id: number; name: string }) => {
        if (!confirm(`Remove ${student.first_name} from ${batch.name}?`)) return;

        router.delete(route('students.unenrol', [student.id, batch.id]), { preserveScroll: true });
    };

    const allVisibleSelected =
        students.data.length > 0 &&
        students.data.every((student) => selected.includes(student.id));

    const toggleAllVisible = () => {
        const visibleIds = students.data.map((student) => student.id);
        setSelected((current) =>
            allVisibleSelected
                ? current.filter((id) => !visibleIds.includes(id))
                : Array.from(new Set([...current, ...visibleIds])),
        );
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">Students</h2>}>
            <Head title="Students" />
            <div className="mx-auto grid max-w-7xl gap-6 px-4 py-8 lg:grid-cols-3">
                <form onSubmit={submit} className="space-y-4 rounded-lg bg-white p-5 shadow-sm lg:col-span-1">
                    <div className="flex items-start justify-between gap-2">
                        <h3 className="font-semibold">{editingId ? 'Edit student' : 'Admit student'}</h3>
                        {editingId && (
                            <button type="button" onClick={resetAdmitForm} className="text-xs text-slate-600 underline">Cancel</button>
                        )}
                    </div>
                    {!editingId ? (
                        <p className="rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-900">
                            This blank form is for a <strong>new</strong> student (boxes default on).
                            To change Vikram / alerts, click <strong>Edit</strong> on the right first — name and phone must fill in.
                        </p>
                    ) : (
                        <p className="rounded-md bg-sky-50 px-3 py-2 text-xs text-sky-900">
                            Editing <strong>{form.data.first_name} {form.data.last_name}</strong>.
                            Uncheck WhatsApp + SMS here, keep parent email, then click Save changes.
                        </p>
                    )}
                    {errors?.student && <p className="rounded bg-red-50 px-3 py-2 text-xs text-red-700">{errors.student}</p>}

                    <Section title="Student">
                        <Field label="Admission no" error={form.errors.admission_no} hint={`Leave blank for ${nextAdmissionNo}`}>
                            <input className={input} placeholder={nextAdmissionNo} value={form.data.admission_no} onChange={(e) => form.setData('admission_no', e.target.value)} />
                        </Field>
                        <div className="grid grid-cols-2 gap-2">
                            <Field label="First name" error={form.errors.first_name}>
                                <input className={input} value={form.data.first_name} onChange={(e) => form.setData('first_name', e.target.value)} />
                            </Field>
                            <Field label="Last name" error={form.errors.last_name}>
                                <input className={input} value={form.data.last_name} onChange={(e) => form.setData('last_name', e.target.value)} />
                            </Field>
                        </div>
                        <div className="grid grid-cols-2 gap-2">
                            <Field label="Class / level" error={form.errors.class_level} hint="VIII, XII, Dropper">
                                <input className={input} value={form.data.class_level} onChange={(e) => form.setData('class_level', e.target.value)} />
                            </Field>
                            <Field label="Target exam year" error={form.errors.target_exam_year} hint="e.g. 2027">
                                <input className={input} value={form.data.target_exam_year} onChange={(e) => form.setData('target_exam_year', e.target.value)} />
                            </Field>
                        </div>
                        <Field label="School / college" error={form.errors.school_name}>
                            <input className={input} value={form.data.school_name} onChange={(e) => form.setData('school_name', e.target.value)} />
                        </Field>
                        <div className="grid grid-cols-2 gap-2">
                            <Field label="Date of birth" error={form.errors.date_of_birth}>
                                <DateInput className={input} value={form.data.date_of_birth} onChange={(v) => form.setData('date_of_birth', v)} />
                            </Field>
                            <Field label="Gender" error={form.errors.gender}>
                                <select className={input} value={form.data.gender} onChange={(e) => form.setData('gender', e.target.value)}>
                                    <option value="">—</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                </select>
                            </Field>
                        </div>
                        <div className="grid grid-cols-2 gap-2">
                            <Field label="Student phone" error={form.errors.phone}>
                                <input className={input} value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} />
                            </Field>
                            <Field label="Email" error={form.errors.email}>
                                <input className={input} value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
                            </Field>
                        </div>
                        <Field label="Address" error={form.errors.address}>
                            <textarea className={input} rows={2} value={form.data.address} onChange={(e) => form.setData('address', e.target.value)} />
                        </Field>
                    </Section>

                    <Section title="Guardian">
                        <p className="text-xs text-gray-500">
                            To save parent email for alerts, fill <strong>Name</strong>, <strong>Phone</strong>, and a full email (example: name@gmail.com).
                        </p>
                        <div className="grid grid-cols-2 gap-2">
                            <Field label="Name" error={form.errors.guardian_name}>
                                <input className={input} value={form.data.guardian_name} onChange={(e) => form.setData('guardian_name', e.target.value)} />
                            </Field>
                            <Field label="Relation" error={form.errors.guardian_relation}>
                                <select className={input} value={form.data.guardian_relation} onChange={(e) => form.setData('guardian_relation', e.target.value)}>
                                    <option value="father">Father</option>
                                    <option value="mother">Mother</option>
                                    <option value="guardian">Guardian</option>
                                </select>
                            </Field>
                        </div>
                        <div className="grid grid-cols-2 gap-2">
                            <Field label="Phone" error={form.errors.guardian_phone}>
                                <input className={input} value={form.data.guardian_phone} onChange={(e) => form.setData('guardian_phone', e.target.value)} />
                            </Field>
                            <Field label="Alternate phone" error={form.errors.guardian_alternate_phone}>
                                <input className={input} value={form.data.guardian_alternate_phone} onChange={(e) => form.setData('guardian_alternate_phone', e.target.value)} />
                            </Field>
                        </div>
                        <div className="grid grid-cols-2 gap-2">
                            <Field label="Occupation" error={form.errors.guardian_occupation}>
                                <input className={input} value={form.data.guardian_occupation} onChange={(e) => form.setData('guardian_occupation', e.target.value)} />
                            </Field>
                            <Field label="Email" error={form.errors.guardian_email}>
                                <input
                                    type="email"
                                    className={input}
                                    placeholder="parent@gmail.com"
                                    value={form.data.guardian_email}
                                    onChange={(e) => form.setData('guardian_email', e.target.value)}
                                />
                            </Field>
                        </div>
                        <label className="flex items-center gap-2 text-sm">
                            <input type="checkbox" checked={form.data.whatsapp_opt_in} onChange={(e) => form.setData('whatsapp_opt_in', e.target.checked)} />
                            WhatsApp opt-in for parent
                        </label>
                        <label className="flex items-center gap-2 text-sm">
                            <input type="checkbox" checked={form.data.sms_opt_in} onChange={(e) => form.setData('sms_opt_in', e.target.checked)} />
                            SMS opt-in for parent
                        </label>
                        <p className="text-xs text-gray-500">
                            Priority: WhatsApp → SMS → Email. For a Brevo email test, uncheck WhatsApp and SMS, keep a parent email, then mark absent/present.
                        </p>
                    </Section>

                    <Section title="Admission">
                        {!editingId && (
                            <Field label="Batch" error={form.errors.batch_id}>
                                <select
                                    className={input}
                                    value={form.data.batch_id}
                                    onChange={(e) => applyBatchFeeDefaults(e.target.value)}
                                >
                                    <option value="">—</option>
                                    {batches.map((b) => (
                                        <option key={b.id} value={b.id}>
                                            {b.name}
                                            {b.default_fee != null ? ` · ₹${Number(b.default_fee).toLocaleString('en-IN')}` : ''}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                        )}
                        {(form.data.batch_id || editingId) && (
                            <div className="space-y-2 rounded border border-slate-100 bg-slate-50 p-3">
                                <p className="text-xs font-medium text-slate-700">How does this student pay?</p>
                                <Field label="Fee style" error={form.errors.fee_style}>
                                    <select className={input} value={form.data.fee_style} onChange={(e) => form.setData('fee_style', e.target.value)}>
                                        {FEE_STYLES.map((s) => (
                                            <option key={s.value} value={s.value}>{s.label}</option>
                                        ))}
                                    </select>
                                </Field>
                                <Field
                                    label={feeAmountField(form.data.fee_style).label}
                                    error={form.errors.fee_amount}
                                    hint={feeAmountField(form.data.fee_style).hint}
                                >
                                    <input className={input} value={form.data.fee_amount} onChange={(e) => form.setData('fee_amount', e.target.value)} placeholder="Amount" />
                                </Field>
                                <Field label="Admission fee (one-time)" error={form.errors.admission_fee} hint="Separate from the fee above">
                                    <input className={input} value={form.data.admission_fee} onChange={(e) => form.setData('admission_fee', e.target.value)} placeholder="Optional" />
                                </Field>
                                {form.data.fee_style === 'monthly' && (
                                    <Field label="Tuition due day each month" error={form.errors.fee_due_day} hint="e.g. 5 = due on 5th of every month">
                                        <select className={input} value={form.data.fee_due_day} onChange={(e) => form.setData('fee_due_day', e.target.value)}>
                                            {DUE_DAYS.map((d) => (
                                                <option key={d} value={d}>{d}{d === 1 ? 'st' : d === 2 ? 'nd' : d === 3 ? 'rd' : 'th'} of month</option>
                                            ))}
                                        </select>
                                    </Field>
                                )}
                                {(form.data.fee_style === 'term' || form.data.fee_style === 'installments' || form.data.fee_style === 'custom') && (
                                    <Field label="Fee due date" error={form.errors.fee_first_due_date} hint="When this fee is expected">
                                        <DateInput
                                            className={input}
                                            value={form.data.fee_first_due_date || form.data.joined_on}
                                            onChange={(v) => form.setData('fee_first_due_date', v)}
                                        />
                                    </Field>
                                )}
                                {form.data.fee_style === 'installments' && (
                                    <Field label="Instalments" error={form.errors.fee_installments}>
                                        <select className={input} value={form.data.fee_installments} onChange={(e) => form.setData('fee_installments', e.target.value)}>
                                            {[2, 3, 4, 5, 6, 8, 10, 12].map((n) => (
                                                <option key={n} value={n}>{n} parts</option>
                                            ))}
                                        </select>
                                    </Field>
                                )}
                                {!editingId && (
                                    <label className="flex items-center gap-2 text-sm text-slate-700">
                                        <input
                                            type="checkbox"
                                            checked={form.data.raise_first_invoice}
                                            onChange={(e) => form.setData('raise_first_invoice', e.target.checked)}
                                        />
                                        Raise bills on admission (admission fee + first fee bill)
                                    </label>
                                )}
                            </div>
                        )}
                        <div className="grid grid-cols-2 gap-2">
                            <Field label="Joined on" error={form.errors.joined_on} hint="Defaults to today">
                                <DateInput
                                    className={input}
                                    value={form.data.joined_on}
                                    onChange={(v) => {
                                        form.setData('joined_on', v);
                                        if (!form.data.fee_first_due_date && v) {
                                            form.setData('fee_first_due_date', v);
                                        }
                                    }}
                                />
                            </Field>
                            <Field label="Enquiry source" error={form.errors.source} hint="Referral, pamphlet…">
                                <input className={input} value={form.data.source} onChange={(e) => form.setData('source', e.target.value)} />
                            </Field>
                        </div>
                        <Field label="Remarks" error={form.errors.remarks}>
                            <textarea className={input} rows={2} value={form.data.remarks} onChange={(e) => form.setData('remarks', e.target.value)} />
                        </Field>
                    </Section>

                    <button disabled={form.processing} className="w-full rounded bg-brand-700 px-3 py-2 text-white disabled:opacity-50">
                        {form.processing ? 'Saving…' : (editingId ? 'Save changes' : 'Save')}
                    </button>
                </form>

                <div className="space-y-4 lg:col-span-2">
                    <form onSubmit={submitImport} className="flex flex-wrap items-center gap-3 rounded-lg bg-white p-4 shadow-sm">
                        <span className="font-semibold">Bulk import / export</span>
                        <a className="text-sm text-brand-700 underline" href={route('students.import-template')}>
                            Download CSV template
                        </a>
                        <input
                            type="file"
                            accept=".csv"
                            className="text-sm"
                            onChange={(e) => importForm.setData('file', e.target.files?.[0] || null)}
                        />
                        <button className="rounded bg-slate-800 px-3 py-1.5 text-sm text-white" disabled={importForm.processing}>
                            Import CSV
                        </button>
                        <a className="text-sm text-brand-700" href={route('students.export')}>Export CSV</a>
                    </form>

                    <div className="flex flex-wrap items-center gap-3 rounded-lg bg-white p-4 shadow-sm">
                        <input
                            className={input + ' max-w-xs'}
                            placeholder="Search name, admission no, school, phone"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && applyFilters({ search })}
                        />
                        <select
                            className={input + ' max-w-[10rem]'}
                            value={filters.status || 'active'}
                            onChange={(e) => applyFilters({ status: e.target.value })}
                        >
                            <option value="active">Active</option>
                            <option value="left">Left</option>
                            <option value="all">All</option>
                        </select>
                        <select
                            className={input + ' max-w-[10rem]'}
                            value={filters.class_level || ''}
                            onChange={(e) => applyFilters({ class_level: e.target.value })}
                        >
                            <option value="">All classes</option>
                            {classLevels.map((level) => (
                                <option key={level} value={level}>{level}</option>
                            ))}
                        </select>
                        <select
                            className={input + ' max-w-[12rem]'}
                            value={filters.batch_id || ''}
                            onChange={(e) => applyFilters({ batch_id: e.target.value })}
                        >
                            <option value="">All batches</option>
                            <option value="unassigned">Unassigned students</option>
                            {batches.map((batch) => (
                                <option key={batch.id} value={batch.id}>{batch.name}</option>
                            ))}
                        </select>
                        <button onClick={() => applyFilters({ search })} className="rounded bg-slate-800 px-3 py-1.5 text-sm text-white">
                            Search
                        </button>
                    </div>

                    <div className="flex flex-wrap items-center gap-3 rounded-lg border border-brand-200 bg-brand-50 p-4">
                        <div className="mr-auto">
                            <div className="font-semibold text-brand-900">Assign students to a batch</div>
                            <div className="text-xs text-brand-700">
                                {singleBatchMode
                                    ? 'Select students below, then choose a batch. Your coaching keeps each student in one batch, so this replaces their current batch.'
                                    : 'Select students below, then choose a batch and whether to add or move.'}
                            </div>
                        </div>
                        <span className="text-sm font-medium text-brand-900">{selected.length} selected</span>
                        {!singleBatchMode && (
                            <div className="flex items-center gap-3 text-xs text-brand-900">
                                <label className="flex items-center gap-1">
                                    <input
                                        type="radio"
                                        name="enrol-mode"
                                        checked={bulkForm.data.mode === 'add'}
                                        onChange={() => bulkForm.setData('mode', 'add')}
                                    />
                                    Add (keep other batches)
                                </label>
                                <label className="flex items-center gap-1">
                                    <input
                                        type="radio"
                                        name="enrol-mode"
                                        checked={bulkForm.data.mode === 'move'}
                                        onChange={() => bulkForm.setData('mode', 'move')}
                                    />
                                    Move (replace batches)
                                </label>
                            </div>
                        )}
                        <select
                            className={input + ' max-w-[13rem] bg-white'}
                            value={bulkForm.data.batch_id}
                            onChange={(e) => bulkForm.setData('batch_id', e.target.value)}
                        >
                            <option value="">Choose batch</option>
                            {batches.map((batch) => (
                                <option key={batch.id} value={batch.id}>{batch.name}</option>
                            ))}
                        </select>
                        <button
                            type="button"
                            disabled={selected.length === 0 || !bulkForm.data.batch_id || bulkForm.processing}
                            onClick={assignBatch}
                            className="rounded bg-brand-700 px-3 py-2 text-sm text-white disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {bulkForm.processing ? 'Adding…' : 'Add to batch'}
                        </button>
                        {bulkForm.errors.student_ids && <p className="w-full text-xs text-red-600">{bulkForm.errors.student_ids}</p>}
                        {bulkForm.errors.batch_id && <p className="w-full text-xs text-red-600">{bulkForm.errors.batch_id}</p>}
                        {batches.length === 0 && (
                            <p className="w-full text-xs text-amber-700">
                                Create a batch under Batches first.
                            </p>
                        )}
                    </div>

                    <div className="overflow-x-auto rounded-lg bg-white shadow-sm">
                        <table className="min-w-full text-sm">
                            <thead className="bg-gray-50 text-left">
                                <tr>
                                    <th className="px-4 py-3">
                                        <input
                                            type="checkbox"
                                            aria-label="Select all visible students"
                                            checked={allVisibleSelected}
                                            onChange={toggleAllVisible}
                                        />
                                    </th>
                                    <th className="px-4 py-3">Admission</th>
                                    <th className="px-4 py-3">Name</th>
                                    <th className="px-4 py-3">Class</th>
                                    <th className="px-4 py-3">School</th>
                                    <th className="px-4 py-3">Batch</th>
                                    <th className="px-4 py-3">Guardian</th>
                                    <th className="px-4 py-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {students.data.length === 0 && (
                                    <tr>
                                        <td colSpan={8} className="px-4 py-8 text-center text-gray-500">
                                            No students in this view. Try status All, or admit one on the left.
                                        </td>
                                    </tr>
                                )}
                                {students.data.map((s) => (
                                    <tr key={s.id} className={'border-t ' + (s.status === 'left' ? 'bg-slate-50' : '')}>
                                        <td className="px-4 py-3">
                                            <input
                                                type="checkbox"
                                                aria-label={`Select ${s.first_name} ${s.last_name || ''}`}
                                                checked={selected.includes(s.id)}
                                                onChange={() => setSelected((current) =>
                                                    current.includes(s.id)
                                                        ? current.filter((id) => id !== s.id)
                                                        : [...current, s.id],
                                                )}
                                            />
                                        </td>
                                        <td className="px-4 py-3">{s.admission_no}</td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-2">
                                                <span>{s.first_name} {s.last_name}</span>
                                                {s.status === 'left' && (
                                                    <span className="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-medium uppercase text-amber-800">Left</span>
                                                )}
                                            </div>
                                            {s.phone && <div className="text-xs text-gray-500">{s.phone}</div>}
                                        </td>
                                        <td className="px-4 py-3">
                                            {s.class_level || '—'}
                                            {s.target_exam_year && <div className="text-xs text-gray-500">Target {s.target_exam_year}</div>}
                                        </td>
                                        <td className="px-4 py-3">{s.school_name || '—'}</td>
                                        <td className="px-4 py-3">
                                            {s.enrolments?.length ? (
                                                <div className="flex flex-wrap gap-1">
                                                    {s.enrolments.map((enrolment) => enrolment.batch && (
                                                        <span
                                                            key={enrolment.id}
                                                            className="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-800"
                                                        >
                                                            {enrolment.batch.name}
                                                            {enrolment.fee_style && (
                                                                <span className="text-gray-500">· {enrolment.fee_style}</span>
                                                            )}
                                                            {enrolment.fee_amount != null && (
                                                                <span className="text-gray-500">· ₹{Number(enrolment.fee_amount).toLocaleString('en-IN')}</span>
                                                            )}
                                                            <button
                                                                type="button"
                                                                title={`Remove from ${enrolment.batch.name}`}
                                                                onClick={() => removeFromBatch(s, enrolment.batch!)}
                                                                className="text-gray-500 hover:text-red-600"
                                                            >
                                                                ×
                                                            </button>
                                                        </span>
                                                    ))}
                                                </div>
                                            ) : (
                                                <span className="text-xs text-amber-700">Not assigned</span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            {s.guardians?.[0]?.name || '—'}
                                            {s.guardians?.[0]?.phone && (
                                                <div className="text-xs text-gray-500">
                                                    {s.guardians[0].relation} · {s.guardians[0].phone}
                                                </div>
                                            )}
                                            <div className="mt-0.5 text-xs text-slate-500">
                                                Alerts: <span className="font-medium text-slate-700">{alertChannelFor(s)}</span>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex flex-wrap gap-2 text-xs">
                                                <button type="button" onClick={() => startEdit(s)} className="text-brand-700 underline">Edit</button>
                                                {s.status === 'left' ? (
                                                    <button type="button" onClick={() => reactivate(s)} className="text-emerald-700 underline">Reactivate</button>
                                                ) : (
                                                    <button type="button" onClick={() => markLeft(s)} className="text-amber-700 underline">Mark left</button>
                                                )}
                                                <button
                                                    type="button"
                                                    onClick={() => deleteStudent(s)}
                                                    className={s.can_delete ? 'text-red-700 underline' : 'text-gray-400'}
                                                    title={s.can_delete ? 'Delete' : 'Blocked — use Mark left'}
                                                >
                                                    Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function Section({ title, children }: PropsWithChildren<{ title: string }>) {
    return (
        <fieldset className="space-y-2 border-t pt-3">
            <legend className="text-xs font-semibold uppercase tracking-wide text-gray-500">{title}</legend>
            {children}
        </fieldset>
    );
}

function Field({ label, error, hint, children }: PropsWithChildren<{ label: string; error?: string; hint?: string }>) {
    return (
        <label className="block">
            <span className="text-xs font-medium text-gray-600">{label}</span>
            <div className="mt-1">{children}</div>
            {hint && !error && <span className="text-xs text-gray-400">{hint}</span>}
            {error && <span className="text-xs text-red-600">{error}</span>}
        </label>
    );
}
