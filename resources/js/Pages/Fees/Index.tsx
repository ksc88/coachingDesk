import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import DateInput from '@/Components/DateInput';
import { DATE_DISPLAY_HINT, formatDate } from '@/lib/formatDate';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { FormEvent, useEffect, useMemo, useRef, useState } from 'react';

type BatchOption = {
    id: number;
    name: string;
    default_fee?: number | null;
    fee_style?: string | null;
    fee_amount?: number | null;
    fee_installments?: number | null;
    enrolled_on?: string | null;
    fee_due_day?: number | null;
    fee_first_due_date?: string | null;
};

const billingMonth = (value: string | null | undefined) => (value ? value.slice(0, 7) : '');

const isBeforeEnrollment = (period: string, enrolledOn?: string | null) =>
    Boolean(enrolledOn && period < billingMonth(enrolledOn));

type Student = {
    id: number;
    admission_no: string;
    first_name: string;
    last_name?: string | null;
    phone?: string | null;
    batch_id?: number | null;
    batch_name?: string | null;
    batch_fee?: number | null;
    fee_style?: string | null;
    fee_installments?: number | null;
    batches?: BatchOption[];
};

type Invoice = {
    id: number;
    invoice_no: string;
    student_id: number;
    total: number | string;
    paid_total: number | string;
    discount_total?: number | string;
    status: string;
    display_status?: string;
    notes?: string | null;
    due_date?: string | null;
    student?: { first_name?: string; last_name?: string; admission_no?: string };
    batch?: { id?: number; name?: string } | null;
};

const money = (n: number | string | null | undefined) =>
    `₹${Number(n || 0).toLocaleString('en-IN')}`;

/** Fee arrangement label — avoid "/mo" for term / instalments / custom. */
const feeArrangementLabel = (
    style: string | null | undefined,
    amount: number | string | null | undefined,
    installments?: number | null,
) => {
    if (amount == null || amount === '') return null;
    const m = money(amount);
    switch (style) {
        case 'term':
            return `${m} term`;
        case 'installments':
            return installments != null ? `${m} · ${installments} instalments` : `${m} instalments`;
        case 'custom':
            return `${m} one-time`;
        default:
            return `${m}/mo`;
    }
};

const statusLabel = (status: string) => {
    if (status === 'not_due') return 'Not due';
    if (status === 'not_billed') return 'Not billed';
    return status.replace(/_/g, ' ');
};

function StatusPill({ status }: { status: string }) {
    const styles: Record<string, string> = {
        paid: 'bg-emerald-50 text-emerald-800',
        partial: 'bg-amber-50 text-amber-800',
        due: 'bg-sky-50 text-sky-800',
        overdue: 'bg-rose-50 text-rose-800',
        not_due: 'bg-gray-100 text-gray-600',
        not_billed: 'bg-gray-100 text-gray-600',
        open: 'bg-sky-50 text-sky-800',
    };

    return (
        <span className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium capitalize ${styles[status] || 'bg-gray-100 text-gray-700'}`}>
            {statusLabel(status)}
        </span>
    );
}

const PLAN_TYPES = [
    { value: 'monthly', label: 'Monthly', hint: 'One bill for a chosen month — usually auto-created when you record payment or bulk-generate batch dues.' },
    { value: 'term', label: 'Term / lump sum', hint: 'Creates 1 invoice for a full term or year package.' },
    { value: 'installments', label: 'Instalments', hint: 'Splits one package total into 2–12 equal invoices with monthly due dates.' },
    { value: 'custom', label: 'Custom', hint: 'One-off bill — admission, books, exam fee. Put the reason in the note.' },
] as const;

const modeLabel = (mode: string) => {
    const labels: Record<string, string> = {
        cash: 'Cash',
        upi: 'UPI',
        bank: 'Bank',
        razorpay: 'Online (Razorpay)',
    };
    return labels[mode] ?? mode;
};

const receiptPrintUrl = (receiptId: number, autoPrint = false) =>
    `${route('fees.receipts.show', receiptId)}${autoPrint ? '?auto_print=1' : ''}`;

type FeeTab = 'collect' | 'batch' | 'more';

export default function FeesIndex({
    openInvoices,
    recentInvoices,
    recentReceipts,
    students,
    gatewayStatus,
    ledger,
    ledgerStudentId,
    batchOutstanding,
    batchLedger,
    ledgerBatchId,
    currentBillingPeriod,
}: any) {
    const { flash } = usePage().props as any;
    const defaultBillingMonth = currentBillingPeriod || new Date().toISOString().slice(0, 7);
    const [tab, setTab] = useState<FeeTab>('collect');
    const [feePurpose, setFeePurpose] = useState<'current' | 'arrears'>('current');
    const invoiceForm = useForm({
        student_id: '',
        batch_id: '',
        plan_type: 'monthly',
        installments: '3',
        total: '',
        discount_total: '0',
        due_date: '',
        notes: '',
    });
    const paymentForm = useForm({
        student_id: '',
        batch_id: '',
        billing_period: defaultBillingMonth,
        invoice_id: '',
        amount: '',
        mode: 'cash',
        reference: '',
        paid_on: new Date().toISOString().slice(0, 10),
        allow_back_due: false,
        back_due_note: '',
    });
    const [showSpecialBill, setShowSpecialBill] = useState(false);

    useEffect(() => {
        const receiptId = flash?.print_receipt_id;
        if (receiptId) {
            window.open(receiptPrintUrl(Number(receiptId), true), '_blank', 'noopener');
        }
    }, [flash?.print_receipt_id]);

    const selectedStudent = useMemo(
        () => students.find((s: Student) => String(s.id) === String(invoiceForm.data.student_id)) as Student | undefined,
        [students, invoiceForm.data.student_id],
    );

    const paymentStudent = useMemo(
        () => students.find((s: Student) => String(s.id) === String(paymentForm.data.student_id)) as Student | undefined,
        [students, paymentForm.data.student_id],
    );

    const paymentBatches: BatchOption[] = paymentStudent?.batches?.length
        ? paymentStudent.batches
        : paymentStudent?.batch_id
            ? [{
                id: paymentStudent.batch_id,
                name: paymentStudent.batch_name || 'Batch',
                default_fee: paymentStudent.batch_fee,
                fee_style: paymentStudent.fee_style,
                fee_amount: paymentStudent.batch_fee,
            }]
            : [];

    const paymentBatch = paymentBatches.find((b) => String(b.id) === String(paymentForm.data.batch_id));
    const paymentEnrolledOn = paymentBatch?.enrolled_on ?? paymentBatches[0]?.enrolled_on ?? null;
    const earliestBillMonth = billingMonth(paymentEnrolledOn);
    const billingTooEarly = Boolean(
        paymentStudent && paymentEnrolledOn && isBeforeEnrollment(paymentForm.data.billing_period, paymentEnrolledOn),
    );

    const studentBatches: BatchOption[] = selectedStudent?.batches?.length
        ? selectedStudent.batches
        : selectedStudent?.batch_id
            ? [{
                id: selectedStudent.batch_id,
                name: selectedStudent.batch_name || 'Batch',
                default_fee: selectedStudent.batch_fee,
            }]
            : [];

    const selectedBatch = studentBatches.find((b) => String(b.id) === String(invoiceForm.data.batch_id));

    const arrangement = selectedBatch ?? (selectedStudent && studentBatches[0] ? studentBatches[0] : null);
    const arrangementStyle = arrangement?.fee_style || selectedStudent?.fee_style || 'monthly';
    const arrangementAmount = arrangement?.fee_amount ?? arrangement?.default_fee ?? selectedStudent?.batch_fee ?? null;
    const enteredAmount = Number(invoiceForm.data.total) || 0;
    const enteredDiscount = Number(invoiceForm.data.discount_total) || 0;
    const billAmount = Math.max(0, enteredAmount - enteredDiscount);
    const planDiffers = arrangement && invoiceForm.data.plan_type !== arrangementStyle;
    const amountDiffers = arrangementAmount != null && enteredAmount > 0 && Math.abs(enteredAmount - Number(arrangementAmount)) > 0.009;
    const showArrangementWarn = selectedStudent && (planDiffers || amountDiffers);

    const studentInvoices = useMemo(() => {
        const list: Invoice[] = openInvoices || [];
        if (!paymentForm.data.student_id) return list;
        return list.filter((inv) => String(inv.student_id) === String(paymentForm.data.student_id));
    }, [openInvoices, paymentForm.data.student_id]);

    const studentOutstanding = useMemo(() => {
        if (!paymentForm.data.student_id) return 0;
        return studentInvoices.reduce(
            (sum, inv) => sum + Math.max(0, Number(inv.total) - Number(inv.paid_total)),
            0,
        );
    }, [paymentForm.data.student_id, studentInvoices]);

    const canSubmitPayment = !billingTooEarly || (paymentForm.data.allow_back_due && paymentForm.data.back_due_note.trim().length > 0);

    const applyBatchFee = (batch?: BatchOption | null) => {
        const b = batch ?? selectedBatch;
        if (!b) return;
        const amount = b.fee_amount ?? b.default_fee;
        if (amount != null) {
            invoiceForm.setData('total', String(amount));
        }
        invoiceForm.setData('batch_id', String(b.id));
        if (b.fee_style) {
            invoiceForm.setData('plan_type', b.fee_style);
        }
        if (b.fee_style === 'installments' && b.fee_installments) {
            invoiceForm.setData('installments', String(b.fee_installments));
        }
    };

    const onPickBatch = (batchId: string) => {
        invoiceForm.setData('batch_id', batchId);
        const batch = studentBatches.find((b) => String(b.id) === String(batchId));
        if (batch) {
            applyBatchFee(batch);
        }
    };

    const onPickInvoiceStudent = (id: string) => {
        invoiceForm.setData('student_id', id);
        const student = students.find((s: Student) => String(s.id) === String(id)) as Student | undefined;
        const batches = student?.batches?.length
            ? student.batches
            : student?.batch_id
                ? [{
                    id: student.batch_id,
                    name: student.batch_name || 'Batch',
                    default_fee: student.batch_fee,
                    fee_style: student.fee_style,
                    fee_amount: student.batch_fee,
                    fee_installments: student.fee_installments,
                }]
                : [];
        const primary = batches[0];
        invoiceForm.setData('batch_id', primary ? String(primary.id) : '');
        if (primary) {
            if (primary.fee_style) {
                invoiceForm.setData('plan_type', primary.fee_style);
            }
            if (primary.fee_style === 'installments' && primary.fee_installments) {
                invoiceForm.setData('installments', String(primary.fee_installments));
            }
            const amount = primary.fee_amount ?? primary.default_fee;
            if (amount != null) {
                invoiceForm.setData('total', String(amount));
            }
        }
    };

    const onPickPaymentStudent = (id: string) => {
        paymentForm.setData('student_id', id);
        const student = students.find((s: Student) => String(s.id) === String(id)) as Student | undefined;
        const batches = student?.batches?.length
            ? student.batches
            : student?.batch_id
                ? [{
                    id: student.batch_id,
                    name: student.batch_name || 'Batch',
                    default_fee: student.batch_fee,
                    fee_style: student.fee_style,
                    fee_amount: student.batch_fee,
                }]
                : [];
        const primary = batches[0];
        paymentForm.setData('batch_id', primary ? String(primary.id) : '');
        paymentForm.setData('billing_period', defaultBillingMonth);
        setFeePurpose('current');
        paymentForm.setData('allow_back_due', false);
        paymentForm.setData('back_due_note', '');
        const openForStudent = (openInvoices || []).filter(
            (inv: Invoice) => String(inv.student_id) === String(id) && ['open', 'partial'].includes(inv.status),
        );
        paymentForm.setData('invoice_id', openForStudent.length === 1 ? String(openForStudent[0].id) : '');
        const feeAmount = primary?.fee_amount ?? primary?.default_fee ?? student?.batch_fee;
        if (openForStudent.length === 1) {
            const due = Number(openForStudent[0].total) - Number(openForStudent[0].paid_total);
            paymentForm.setData('amount', String(due));
        } else if (feeAmount != null && (primary?.fee_style || student?.fee_style || 'monthly') === 'monthly') {
            paymentForm.setData('amount', String(feeAmount));
        } else {
            paymentForm.setData('amount', '');
        }
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">Fees & receipts</h2>}>
            <Head title="Fees" />
            <div className="mx-auto max-w-3xl px-4 py-8">
                {flash?.success && (
                    <div className="mb-6 rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-800 ring-1 ring-emerald-200">
                        {flash.success}
                    </div>
                )}

                <div className="mb-6 flex gap-1 rounded-lg bg-gray-100 p-1">
                    {([
                        ['collect', 'Collect fee'],
                        ['batch', 'Batch month'],
                        ['more', 'More'],
                    ] as const).map(([key, label]) => (
                        <button
                            key={key}
                            type="button"
                            onClick={() => setTab(key)}
                            className={`flex-1 rounded-md px-3 py-2 text-sm font-medium transition ${
                                tab === key ? 'bg-white text-brand-800 shadow-sm' : 'text-gray-600 hover:text-gray-900'
                            }`}
                        >
                            {label}
                        </button>
                    ))}
                </div>

                {tab === 'collect' && (
                <>
                {gatewayStatus && (
                    <div className={`mb-4 rounded-lg px-4 py-3 text-sm ${
                        gatewayStatus.is_active && gatewayStatus.connected
                            ? 'bg-sky-50 text-sky-900 ring-1 ring-sky-200'
                            : 'bg-amber-50 text-amber-900 ring-1 ring-amber-200'
                    }`}>
                        {gatewayStatus.is_active && gatewayStatus.connected ? (
                            <span>Online payments (Razorpay) are <strong>enabled</strong>.</span>
                        ) : (
                            <span>Manual payments only — Razorpay is <strong>off</strong> or not configured.</span>
                        )}
                        {' '}
                        <Link href={route('settings.index')} className="font-medium underline">Payment settings</Link>
                    </div>
                )}
                <form
                    onSubmit={(e: FormEvent) => {
                        e.preventDefault();
                        paymentForm.transform((data) => ({
                            ...data,
                            billing_period: feePurpose === 'current' ? defaultBillingMonth : data.billing_period,
                            allow_back_due: billingTooEarly ? data.allow_back_due : false,
                        }));
                        paymentForm.post(route('fees.payments.store'), {
                            onSuccess: () => paymentForm.reset('amount', 'reference', 'invoice_id', 'back_due_note'),
                        });
                    }}
                    className="space-y-4 rounded-xl border border-brand-200 bg-white p-5 shadow-sm"
                >
                    <div>
                        <h3 className="text-lg font-semibold text-gray-900">Collect fee</h3>
                        <p className="mt-1 text-sm text-gray-500">Search student → amount → save. Bill is created automatically.</p>
                    </div>

                    <StudentSearchSelect students={students} value={paymentForm.data.student_id} onChange={onPickPaymentStudent} />
                    {paymentForm.errors.student_id && <p className="text-xs text-red-600">{paymentForm.errors.student_id}</p>}

                    {paymentStudent && (
                        <div className="rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-700">
                            <div className="font-medium">{paymentStudent.admission_no} · {paymentStudent.first_name}</div>
                            {paymentBatches.length === 1 && (() => {
                                const b = paymentBatches[0];
                                const feeLabel = feeArrangementLabel(
                                    b.fee_style,
                                    b.fee_amount ?? b.default_fee,
                                    b.fee_installments,
                                );
                                return (
                                    <div className="mt-1 text-xs text-slate-500">
                                        {b.name}
                                        {feeLabel && <> · {feeLabel}</>}
                                        {b.fee_style === 'monthly' && b.fee_due_day != null && (
                                            <> · due on {b.fee_due_day}th</>
                                        )}
                                        {b.enrolled_on && <> · joined {formatDate(b.enrolled_on)}</>}
                                    </div>
                                );
                            })()}
                            {studentOutstanding > 0 && (
                                <div className="mt-1 text-xs font-medium text-amber-800">Already owes {money(studentOutstanding)}</div>
                            )}
                        </div>
                    )}

                    {paymentStudent && paymentBatches.length > 1 && (
                        <label className="block text-sm font-medium text-gray-700">
                            Batch
                            <select
                                className="mt-1 w-full rounded-lg border-gray-300"
                                value={paymentForm.data.batch_id}
                                onChange={(e) => {
                                    paymentForm.setData('batch_id', e.target.value);
                                    const batch = paymentBatches.find((b) => String(b.id) === String(e.target.value));
                                    const amt = batch?.fee_amount ?? batch?.default_fee;
                                    if (amt != null && !paymentForm.data.invoice_id) paymentForm.setData('amount', String(amt));
                                }}
                            >
                                {paymentBatches.map((b) => (
                                    <option key={b.id} value={b.id}>{b.name}</option>
                                ))}
                            </select>
                        </label>
                    )}

                    <div>
                        <span className="text-sm font-medium text-gray-700">Payment for</span>
                        <div className="mt-2 flex gap-2">
                            <button
                                type="button"
                                onClick={() => {
                                    setFeePurpose('current');
                                    paymentForm.setData('billing_period', defaultBillingMonth);
                                }}
                                className={`rounded-full px-4 py-1.5 text-sm ${feePurpose === 'current' ? 'bg-brand-700 text-white' : 'bg-gray-100 text-gray-700'}`}
                            >
                                This month
                            </button>
                            <button
                                type="button"
                                onClick={() => setFeePurpose('arrears')}
                                className={`rounded-full px-4 py-1.5 text-sm ${feePurpose === 'arrears' ? 'bg-brand-700 text-white' : 'bg-gray-100 text-gray-700'}`}
                            >
                                Older month (arrears)
                            </button>
                        </div>
                    </div>

                    {feePurpose === 'arrears' && (
                        <label className="block text-sm font-medium text-gray-700">
                            Which month?
                            <input
                                type="month"
                                className="mt-1 w-full rounded-lg border-gray-300"
                                value={paymentForm.data.billing_period}
                                onChange={(e) => paymentForm.setData('billing_period', e.target.value)}
                            />
                        </label>
                    )}

                    {billingTooEarly && (
                        <div className="space-y-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                            <p>Before join date ({formatDate(paymentEnrolledOn)}). Only use for real back dues.</p>
                            <label className="flex items-start gap-2">
                                <input
                                    type="checkbox"
                                    checked={paymentForm.data.allow_back_due}
                                    onChange={(e) => paymentForm.setData('allow_back_due', e.target.checked)}
                                />
                                <span>Back dues — explain below</span>
                            </label>
                            {paymentForm.data.allow_back_due && (
                                <input
                                    className="w-full rounded border-gray-300 text-sm"
                                    placeholder="e.g. Transferred from old branch, arrears from 2025"
                                    value={paymentForm.data.back_due_note}
                                    onChange={(e) => paymentForm.setData('back_due_note', e.target.value)}
                                />
                            )}
                        </div>
                    )}
                    {paymentForm.errors.billing_period && <p className="text-xs text-red-600">{paymentForm.errors.billing_period}</p>}

                    <div className="grid gap-3 sm:grid-cols-3">
                        <label className="block text-sm font-medium text-gray-700">
                            Amount ₹
                            <input
                                className="mt-1 w-full rounded-lg border-gray-300 text-lg"
                                value={paymentForm.data.amount}
                                onChange={(e) => paymentForm.setData('amount', e.target.value)}
                            />
                        </label>
                        <label className="block text-sm font-medium text-gray-700">
                            Mode
                            <select className="mt-1 w-full rounded-lg border-gray-300" value={paymentForm.data.mode} onChange={(e) => paymentForm.setData('mode', e.target.value)}>
                                <option value="cash">Cash</option>
                                <option value="upi">UPI</option>
                                <option value="bank">Bank</option>
                            </select>
                        </label>
                        <label className="block text-sm font-medium text-gray-700">
                            Paid on
                            <DateInput
                                className="mt-1 w-full rounded-lg border-gray-300"
                                value={paymentForm.data.paid_on}
                                onChange={(v) => paymentForm.setData('paid_on', v)}
                            />
                        </label>
                    </div>
                    {paymentForm.errors.amount && <p className="text-xs text-red-600">{paymentForm.errors.amount}</p>}
                    {paymentForm.errors.paid_on && <p className="text-xs text-red-600">{paymentForm.errors.paid_on}</p>}
                    <input className="w-full rounded-lg border-gray-300 text-sm" placeholder="Reference (optional)" value={paymentForm.data.reference} onChange={(e) => paymentForm.setData('reference', e.target.value)} />

                    <details className="text-sm text-gray-600">
                        <summary className="cursor-pointer text-brand-700">Pin to a specific bill (optional)</summary>
                        <select className="mt-2 w-full rounded-lg border-gray-300" value={paymentForm.data.invoice_id} onChange={(e) => paymentForm.setData('invoice_id', e.target.value)}>
                            <option value="">Auto — oldest due first</option>
                            {studentInvoices.map((inv) => (
                                <option key={inv.id} value={inv.id}>
                                    {inv.invoice_no} · ₹{(Number(inv.total) - Number(inv.paid_total)).toFixed(0)} · {inv.notes || inv.display_status}
                                </option>
                            ))}
                        </select>
                    </details>

                    <button
                        type="submit"
                        disabled={!canSubmitPayment}
                        className="w-full rounded-lg bg-brand-700 py-3 text-base font-semibold text-white hover:bg-brand-800 disabled:opacity-50"
                    >
                        Save & print receipt
                    </button>
                </form>
                </>
                )}

                {tab === 'batch' && (
                    <BatchFeeBoard
                        batchOutstanding={batchOutstanding || []}
                        batchLedger={batchLedger}
                        ledgerBatchId={ledgerBatchId}
                        defaultBillingMonth={defaultBillingMonth}
                    />
                )}

                {tab === 'more' && (
                    <div className="space-y-6">
                        <div className="flex flex-wrap gap-3 text-sm">
                            <a href={route('reports.index', { report: 'pending_dues' })} className="text-brand-700 underline">Pending dues</a>
                            <a href={route('reports.index', { report: 'defaulters' })} className="text-brand-700 underline">Overdue</a>
                            <a href={route('reports.index', { report: 'receipts' })} className="text-brand-700 underline">All receipts</a>
                        </div>

                        <FeeLedger students={students} ledger={ledger} ledgerStudentId={ledgerStudentId} />

                        <details className="rounded-lg bg-white p-4 shadow-sm" open={showSpecialBill} onToggle={(e) => setShowSpecialBill((e.target as HTMLDetailsElement).open)}>
                            <summary className="cursor-pointer font-semibold">Special bill (term / admission / books)</summary>
                            <form
                                onSubmit={(e: FormEvent) => {
                                    e.preventDefault();
                                    invoiceForm.post(route('fees.invoices.store'), {
                                        onSuccess: () => invoiceForm.reset('total', 'notes', 'discount_total', 'due_date'),
                                    });
                                }}
                                className="mt-4 space-y-2"
                            >
                    <StudentSearchSelect
                        students={students}
                        value={invoiceForm.data.student_id}
                        onChange={onPickInvoiceStudent}
                    />
                    {invoiceForm.errors.student_id && <p className="text-xs text-red-600">{invoiceForm.errors.student_id}</p>}

                    {selectedStudent && (
                        <div className="space-y-2 rounded bg-slate-50 px-3 py-2 text-xs text-slate-700">
                            {studentBatches.length === 0 && (
                                <p>No batch assigned — enter amount manually or assign a batch on Students first.</p>
                            )}
                            {studentBatches.length === 1 && (
                                <p>
                                    Batch: <strong>{studentBatches[0].name}</strong>
                                    {studentBatches[0].fee_style && <> · {studentBatches[0].fee_style}</>}
                                    {(studentBatches[0].fee_amount ?? studentBatches[0].default_fee) != null && (
                                        <> · ₹{Number(studentBatches[0].fee_amount ?? studentBatches[0].default_fee).toLocaleString('en-IN')}</>
                                    )}
                                    <button type="button" onClick={() => applyBatchFee(studentBatches[0])} className="ml-2 text-brand-700 underline">
                                        Use arrangement
                                    </button>
                                </p>
                            )}
                            {studentBatches.length > 1 && (
                                <>
                                    <label className="block font-medium text-slate-800">
                                        Which batch is this bill for?
                                        <select
                                            className="mt-1 w-full rounded border-gray-300 text-sm"
                                            value={invoiceForm.data.batch_id}
                                            onChange={(e) => onPickBatch(e.target.value)}
                                        >
                                            {studentBatches.map((b) => (
                                                <option key={b.id} value={b.id}>
                                                    {b.name}
                                                    {b.default_fee != null ? ` · ₹${Number(b.default_fee).toLocaleString('en-IN')}` : ''}
                                                </option>
                                            ))}
                                        </select>
                                    </label>
                                    <p className="text-slate-500">
                                        Create a separate invoice for each batch if fees differ.
                                        {selectedBatch?.default_fee != null && (
                                            <button type="button" onClick={() => applyBatchFee(selectedBatch)} className="ml-2 text-brand-700 underline">
                                                Use this batch fee
                                            </button>
                                        )}
                                    </p>
                                </>
                            )}
                        </div>
                    )}

                    {showArrangementWarn && (
                        <div className="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                            <p className="font-medium">Different from admission arrangement</p>
                            <p className="mt-0.5">
                                Admission: {arrangementStyle}
                                {arrangementAmount != null && <> · {money(arrangementAmount)}</>}
                                {planDiffers && <> · you chose {invoiceForm.data.plan_type}</>}
                                {amountDiffers && <> · you entered {money(enteredAmount)}</>}
                                . Add a note if this is intentional (exam fee, correction, one-off term bill).
                            </p>
                        </div>
                    )}

                    <label className="block text-xs font-medium text-gray-600">How is this fee charged?</label>
                    <select
                        className="w-full rounded border-gray-300"
                        value={invoiceForm.data.plan_type}
                        onChange={(e) => invoiceForm.setData('plan_type', e.target.value)}
                    >
                        {PLAN_TYPES.map((p) => (
                            <option key={p.value} value={p.value}>{p.label}</option>
                        ))}
                    </select>
                    <p className="text-[11px] text-gray-500">
                        {PLAN_TYPES.find((p) => p.value === invoiceForm.data.plan_type)?.hint}
                    </p>

                    {invoiceForm.data.plan_type === 'installments' && (
                        <label className="block text-xs font-medium text-gray-600">
                            Number of instalments
                            <select
                                className="mt-1 w-full rounded border-gray-300"
                                value={invoiceForm.data.installments}
                                onChange={(e) => invoiceForm.setData('installments', e.target.value)}
                            >
                                {[2, 3, 4, 5, 6, 8, 10, 12].map((n) => (
                                    <option key={n} value={n}>{n} parts</option>
                                ))}
                            </select>
                        </label>
                    )}

                    <label className="block text-xs font-medium text-gray-600">
                        Amount (gross fee before discount)
                        <input
                            className="mt-1 w-full rounded border-gray-300"
                            placeholder={invoiceForm.data.plan_type === 'installments' ? 'Total package amount' : 'e.g. 1200'}
                            value={invoiceForm.data.total}
                            onChange={(e) => invoiceForm.setData('total', e.target.value)}
                        />
                    </label>
                    {invoiceForm.errors.total && <p className="text-xs text-red-600">{invoiceForm.errors.total}</p>}
                    <label className="block text-xs font-medium text-gray-600">
                        Discount (₹ off — optional)
                        <input
                            className="mt-1 w-full rounded border-gray-300"
                            placeholder="0"
                            value={invoiceForm.data.discount_total}
                            onChange={(e) => invoiceForm.setData('discount_total', e.target.value)}
                        />
                    </label>
                    <p className="text-[11px] text-gray-500">
                        Bill amount = Amount − Discount. For a final ₹1000 fee, either enter Amount ₹1000 with Discount ₹0, or Amount ₹1200 with Discount ₹200.
                        Put the reason in the note (e.g. sibling discount).
                    </p>
                    {(enteredAmount > 0 || enteredDiscount > 0) && (
                        <p className="rounded bg-slate-100 px-3 py-2 text-sm font-medium text-slate-800">
                            Bill amount: {money(billAmount)}
                            {enteredDiscount > 0 && (
                                <span className="ml-2 text-xs font-normal text-slate-500">
                                    ({money(enteredAmount)} − {money(enteredDiscount)} discount)
                                </span>
                            )}
                        </p>
                    )}
                    <label className="block text-xs font-medium text-gray-600">
                        Due date
                        <DateInput
                            className="mt-1 w-full rounded border-gray-300"
                            value={invoiceForm.data.due_date}
                            onChange={(v) => invoiceForm.setData('due_date', v)}
                        />
                    </label>
                    <p className="text-[11px] text-gray-500">When this fee is expected. Shown as {DATE_DISPLAY_HINT} in lists.</p>
                    <input
                        className="w-full rounded border-gray-300"
                        placeholder={invoiceForm.data.plan_type === 'custom' ? 'Note (Admission, Books, Exam…)' : 'Note (sibling discount, mid-session…)'}
                        value={invoiceForm.data.notes}
                        onChange={(e) => invoiceForm.setData('notes', e.target.value)}
                    />
                    <button className="rounded bg-brand-700 px-3 py-2 text-white hover:bg-brand-800">
                        {invoiceForm.data.plan_type === 'installments' ? 'Create instalment invoices' : 'Create special bill'}
                    </button>
                </form>
                        </details>

                        <Table
                            title="Recent receipts"
                            linkLabel="All receipts →"
                            linkHref={route('reports.index', { report: 'receipts' })}
                            headers={['Receipt', 'Student', 'Amount', 'Date', '']}
                            rows={(recentReceipts || []).map((r: any) => [
                                <a
                                    key={`r-${r.id}`}
                                    href={receiptPrintUrl(r.id)}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="font-medium text-brand-700 underline"
                                >
                                    {r.receipt_no}
                                </a>,
                                r.student?.admission_no
                                    ? `${r.student.admission_no} · ${r.student.first_name}`
                                    : r.student?.first_name,
                                money(r.amount),
                                formatDate(r.issued_on),
                                <a
                                    key={`p-${r.id}`}
                                    href={receiptPrintUrl(r.id, true)}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-xs text-brand-700 underline"
                                >
                                    Print
                                </a>,
                            ])}
                        />
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}

function FeeLedger({
    students,
    ledger,
    ledgerStudentId,
}: {
    students: Student[];
    ledger: any;
    ledgerStudentId?: number | null;
}) {
    const multiBatch = (ledger?.batches?.length ?? 0) > 1;
    const showBatchColumn = multiBatch && !ledger?.selected_batch_id;

    const loadLedger = (studentId: string, batchId?: number | null) => {
        const params: Record<string, string | number> = {};
        if (studentId) params.ledger_student_id = studentId;
        if (batchId) params.ledger_student_batch_id = batchId;
        router.get(route('fees.index'), params, { preserveState: true, replace: true });
    };

    return (
        <div className="rounded-lg bg-white p-4 shadow-sm">
            <h3 className="font-semibold text-gray-900">Student fee ledger</h3>
            <p className="mt-1 text-xs text-gray-500">
                Month-wise due / paid / pending, plus payment history. Pick a student below.
            </p>
            <div className="mt-3 max-w-md">
                <StudentSearchSelect
                    students={students}
                    value={ledgerStudentId ? String(ledgerStudentId) : ''}
                    onChange={(id) => loadLedger(id)}
                />
            </div>

            {ledger && (
                <div className="mt-4 space-y-6">
                    <div className="flex flex-wrap items-baseline justify-between gap-2 text-sm">
                        <span className="font-medium text-gray-800">{ledger.student_name}</span>
                        <span className="text-gray-500">Outstanding {money(ledger.outstanding)}</span>
                    </div>

                    {multiBatch && (
                        <div className="flex flex-wrap gap-2">
                            <button
                                type="button"
                                onClick={() => loadLedger(String(ledger.student_id))}
                                className={`rounded-full px-3 py-1 text-xs font-medium ${
                                    !ledger.selected_batch_id ? 'bg-brand-700 text-white' : 'bg-gray-100 text-gray-700'
                                }`}
                            >
                                All batches
                            </button>
                            {(ledger.batches || []).map((b: { id: number; name: string }) => (
                                <button
                                    key={b.id}
                                    type="button"
                                    onClick={() => loadLedger(String(ledger.student_id), b.id)}
                                    className={`rounded-full px-3 py-1 text-xs font-medium ${
                                        Number(ledger.selected_batch_id) === b.id
                                            ? 'bg-brand-700 text-white'
                                            : 'bg-gray-100 text-gray-700'
                                    }`}
                                >
                                    {b.name}
                                </button>
                            ))}
                        </div>
                    )}

                    {(ledger.months || []).length === 0 ? (
                        <p className="py-4 text-sm text-gray-500">No invoices yet for this student.</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-left text-sm">
                                <thead>
                                    <tr className="border-b border-gray-100 text-xs font-medium uppercase tracking-wide text-gray-400">
                                        <th className="px-2 py-2">Month</th>
                                        {showBatchColumn && <th className="px-2 py-2">Batch</th>}
                                        <th className="px-2 py-2">Due</th>
                                        <th className="px-2 py-2">Paid</th>
                                        <th className="px-2 py-2">Pending</th>
                                        <th className="px-2 py-2">Status</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-50">
                                    {ledger.months.map((row: any) => (
                                        <tr key={`${row.period}-${row.batch_id ?? 'all'}`}>
                                            <td className="whitespace-nowrap px-2 py-2.5 font-medium text-gray-800">{row.period_label}</td>
                                            {showBatchColumn && (
                                                <td className="whitespace-nowrap px-2 py-2.5 text-gray-600">{row.batch_name ?? '—'}</td>
                                            )}
                                            <td className="whitespace-nowrap px-2 py-2.5">{money(row.due)}</td>
                                            <td className="whitespace-nowrap px-2 py-2.5">{money(row.paid)}</td>
                                            <td className="whitespace-nowrap px-2 py-2.5">{money(row.pending)}</td>
                                            <td className="whitespace-nowrap px-2 py-2.5">
                                                <StatusPill status={row.status} />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    <div>
                        <h4 className="text-sm font-semibold text-gray-900">Payment history</h4>
                        {(ledger.payments || []).length === 0 ? (
                            <p className="mt-2 text-sm text-gray-500">No payments recorded yet.</p>
                        ) : (
                            <div className="mt-2 overflow-x-auto">
                                <table className="min-w-full text-left text-sm">
                                    <thead>
                                        <tr className="border-b border-gray-100 text-xs font-medium uppercase tracking-wide text-gray-400">
                                            <th className="px-2 py-2">Date</th>
                                            <th className="px-2 py-2">Receipt</th>
                                            <th className="px-2 py-2">Mode</th>
                                            <th className="px-2 py-2">Amount</th>
                                            <th className="px-2 py-2">Applied to</th>
                                            <th className="px-2 py-2"></th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-50">
                                        {(ledger.payments || []).map((pay: any) => (
                                            <tr key={pay.id}>
                                                <td className="whitespace-nowrap px-2 py-2.5">{formatDate(pay.paid_on)}</td>
                                                <td className="whitespace-nowrap px-2 py-2.5 font-medium text-gray-800">
                                                    {pay.receipt_no ?? '—'}
                                                </td>
                                                <td className="whitespace-nowrap px-2 py-2.5 capitalize">{modeLabel(pay.mode)}</td>
                                                <td className="whitespace-nowrap px-2 py-2.5">{money(pay.amount)}</td>
                                                <td className="max-w-xs px-2 py-2.5 text-xs text-gray-600">
                                                    {(pay.allocations || []).map((a: any, i: number) => (
                                                        <span key={i}>
                                                            {i > 0 && ', '}
                                                            {a.batch_name ?? 'Fee'} {money(a.amount)}
                                                        </span>
                                                    ))}
                                                    {pay.reference && (
                                                        <span className="block text-gray-400">Ref: {pay.reference}</span>
                                                    )}
                                                </td>
                                                <td className="whitespace-nowrap px-2 py-2.5">
                                                    {pay.receipt_id && (
                                                        <a
                                                            href={receiptPrintUrl(pay.receipt_id, true)}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            className="text-xs text-brand-700 underline"
                                                        >
                                                            Print
                                                        </a>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}

function BatchFeeBoard({
    batchOutstanding,
    batchLedger,
    ledgerBatchId,
    defaultBillingMonth,
}: {
    batchOutstanding: { id: number; name: string; students: number; pending: number }[];
    batchLedger: any;
    ledgerBatchId?: number | null;
    defaultBillingMonth: string;
}) {
    const [billingPeriod, setBillingPeriod] = useState(defaultBillingMonth);
    const [generatingId, setGeneratingId] = useState<number | null>(null);

    const generateDues = (batchId: number) => {
        setGeneratingId(batchId);
        router.post(
            route('fees.batch-dues.generate'),
            { batch_id: batchId, billing_period: billingPeriod },
            {
                preserveScroll: true,
                onFinish: () => setGeneratingId(null),
            },
        );
    };

    return (
        <div className="rounded-lg bg-white p-4 shadow-sm">
            <div className="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h3 className="font-semibold text-gray-900">Batch-wise pending</h3>
                    <p className="mt-1 text-xs text-gray-500">
                        Generate one month’s bills for the whole batch, then collect payments at the desk.
                    </p>
                </div>
                <label className="text-xs text-gray-600">
                    Bill for month
                    <input
                        type="month"
                        className="mt-1 block rounded border-gray-300 text-sm"
                        value={billingPeriod}
                        onChange={(e) => setBillingPeriod(e.target.value)}
                    />
                </label>
            </div>
            <div className="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                {batchOutstanding.map((b) => (
                    <div
                        key={b.id}
                        className={`rounded-lg border px-3 py-3 text-left text-sm ${
                            String(ledgerBatchId) === String(b.id) ? 'border-brand-600 bg-brand-50' : 'border-gray-100'
                        }`}
                    >
                        <button
                            type="button"
                            onClick={() => router.get(route('fees.index'), { ledger_batch_id: b.id }, { preserveState: true, replace: true })}
                            className="w-full text-left"
                        >
                            <div className="font-medium text-gray-900">{b.name}</div>
                            <div className="mt-1 text-xs text-gray-500">{b.students} students</div>
                            <div className="mt-1 font-semibold text-gray-800">{money(b.pending)} pending</div>
                        </button>
                        <button
                            type="button"
                            disabled={generatingId === b.id}
                            onClick={() => generateDues(b.id)}
                            className="mt-2 w-full rounded bg-brand-700 px-2 py-1.5 text-xs font-medium text-white hover:bg-brand-800 disabled:opacity-50"
                        >
                            {generatingId === b.id ? 'Generating…' : 'Generate monthly dues'}
                        </button>
                    </div>
                ))}
            </div>

            {batchLedger && (
                <div className="mt-4 overflow-x-auto">
                    <div className="mb-2 text-sm font-medium text-gray-800">{batchLedger.batch_name}</div>
                    <table className="min-w-full text-left text-sm">
                        <thead>
                            <tr className="border-b border-gray-100 text-xs font-medium uppercase tracking-wide text-gray-400">
                                <th className="px-2 py-2">Student</th>
                                <th className="px-2 py-2">Fee style</th>
                                <th className="px-2 py-2">Plan amt</th>
                                <th className="px-2 py-2">Billed</th>
                                <th className="px-2 py-2">Paid</th>
                                <th className="px-2 py-2">Pending</th>
                                <th className="px-2 py-2">Status</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {(batchLedger.rows || []).map((row: any) => (
                                <tr key={row.id}>
                                    <td className="whitespace-nowrap px-2 py-2.5">
                                        <button
                                            type="button"
                                            className="text-brand-700 underline"
                                            onClick={() => router.get(route('fees.index'), { ledger_student_id: row.id }, { preserveState: true, replace: true })}
                                        >
                                            {row.name}
                                        </button>
                                        <div className="text-xs text-gray-400">{row.admission_no}</div>
                                    </td>
                                    <td className="whitespace-nowrap px-2 py-2.5 capitalize">{row.fee_style}</td>
                                    <td className="whitespace-nowrap px-2 py-2.5">{row.fee_amount != null ? money(row.fee_amount) : '—'}</td>
                                    <td className="whitespace-nowrap px-2 py-2.5">{money(row.billed)}</td>
                                    <td className="whitespace-nowrap px-2 py-2.5">{money(row.paid)}</td>
                                    <td className="whitespace-nowrap px-2 py-2.5">{money(row.pending)}</td>
                                    <td className="whitespace-nowrap px-2 py-2.5">
                                        <StatusPill status={row.status} />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}

function StudentSearchSelect({
    students,
    value,
    onChange,
}: {
    students: Student[];
    value: string;
    onChange: (value: string) => void;
}) {
    const [query, setQuery] = useState('');
    const [open, setOpen] = useState(false);
    const wrapRef = useRef<HTMLDivElement>(null);

    const selected = students.find((s) => String(s.id) === String(value));

    useEffect(() => {
        if (selected) {
            setQuery(`${selected.admission_no} · ${selected.first_name}${selected.last_name ? ` ${selected.last_name}` : ''}`);
        } else if (!value) {
            setQuery('');
        }
    }, [value, selected]);

    useEffect(() => {
        const onDoc = (e: MouseEvent) => {
            if (!wrapRef.current?.contains(e.target as Node)) setOpen(false);
        };
        document.addEventListener('mousedown', onDoc);
        return () => document.removeEventListener('mousedown', onDoc);
    }, []);

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();
        const list = !q
            ? students
            : students.filter((s) => {
                const batchNames = (s.batches || []).map((b) => b.name).join(' ');
                const hay = `${s.admission_no} ${s.first_name} ${s.last_name || ''} ${s.phone || ''} ${s.batch_name || ''} ${batchNames}`.toLowerCase();
                return hay.includes(q);
            });
        return list.slice(0, 20);
    }, [students, query]);

    const batchSummary = (s: Student) => {
        if (s.batches && s.batches.length > 1) {
            return `${s.batches.length} batches`;
        }
        if (s.batch_name) {
            const fee = feeArrangementLabel(
                s.batches?.[0]?.fee_style ?? s.fee_style,
                s.batches?.[0]?.fee_amount ?? s.batch_fee,
                s.batches?.[0]?.fee_installments ?? s.fee_installments,
            );
            return fee ? `${s.batch_name} · ${fee}` : s.batch_name;
        }
        return null;
    };

    return (
        <div ref={wrapRef} className="relative">
            <input
                className="w-full rounded border-gray-300"
                placeholder="Search student by name, admission no, or phone"
                value={query}
                onChange={(e) => {
                    setQuery(e.target.value);
                    setOpen(true);
                    if (value) onChange('');
                }}
                onFocus={() => setOpen(true)}
                autoComplete="off"
            />
            <p className="mt-1 text-[11px] text-gray-500">Type to search — top 20 matches.</p>
            {open && (
                <ul className="absolute z-20 mt-1 max-h-56 w-full overflow-auto rounded border border-gray-200 bg-white shadow-lg">
                    {filtered.length === 0 && (
                        <li className="px-3 py-2 text-sm text-gray-500">No match</li>
                    )}
                    {filtered.map((s) => (
                        <li key={s.id}>
                            <button
                                type="button"
                                className="w-full px-3 py-2 text-left text-sm hover:bg-brand-50"
                                onClick={() => {
                                    onChange(String(s.id));
                                    setQuery(`${s.admission_no} · ${s.first_name}${s.last_name ? ` ${s.last_name}` : ''}`);
                                    setOpen(false);
                                }}
                            >
                                <span className="font-medium">{s.first_name}{s.last_name ? ` ${s.last_name}` : ''}</span>
                                <span className="block text-xs text-gray-500">
                                    {s.admission_no}
                                    {batchSummary(s) ? ` · ${batchSummary(s)}` : ''}
                                </span>
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

function Table({ title, headers, rows, linkLabel, linkHref }: any) {
    return (
        <div className="rounded-lg bg-white shadow-sm">
            <div className="flex items-center justify-between border-b px-4 py-3">
                <div className="font-semibold">{title}</div>
                {linkHref && (
                    <a href={linkHref} className="text-xs text-brand-700 underline">{linkLabel || 'View all'}</a>
                )}
            </div>
            <div className="overflow-x-auto">
                <table className="min-w-full text-sm">
                    <thead className="bg-gray-50 text-left">
                        <tr>{headers.map((h: string) => <th key={h} className="whitespace-nowrap px-4 py-2">{h}</th>)}</tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr><td colSpan={headers.length} className="px-4 py-6 text-center text-gray-500">None yet</td></tr>
                        )}
                        {rows.map((row: any[], idx: number) => (
                            <tr key={idx} className="border-t">
                                {row.map((cell, i) => (
                                    <td key={i} className="whitespace-nowrap px-4 py-2">{cell}</td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
