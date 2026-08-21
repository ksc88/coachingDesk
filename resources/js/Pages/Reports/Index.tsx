import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import DateInput from '@/Components/DateInput';
import { formatDate, formatDateRange } from '@/lib/formatDate';
import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, ReactNode, useState } from 'react';

const PERIODS = [
    { value: 'this_month', label: 'This month' },
    { value: 'last_30', label: 'Last 30 days' },
    { value: 'this_fy', label: 'This FY' },
    { value: 'all', label: 'All time' },
] as const;

const money = (n: number | string | null | undefined) =>
    `₹${Number(n || 0).toLocaleString('en-IN')}`;

const studentCell = (row: { admission_no?: string | null; student?: string | null }) => {
    const id = row.admission_no ? `${row.admission_no} · ` : '';
    return `${id}${row.student || '—'}`;
};

type CatalogItem = { key: string; title: string; blurb: string };

export default function ReportsIndex({
    report,
    catalog,
    filters,
    exportQuery,
    data,
}: {
    report: string | null;
    catalog: CatalogItem[];
    filters: any;
    exportQuery: Record<string, string | null | undefined>;
    data: any;
}) {
    const meta = catalog.find((c) => c.key === report);

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h2 className="text-[28px] font-semibold tracking-tight text-gray-900">Reports</h2>
                    {!report && (
                        <p className="mt-0.5 text-sm text-gray-500">Choose one report. Run it. Export when you need Excel.</p>
                    )}
                </div>
            }
        >
            <Head title={meta ? `${meta.title} · Reports` : 'Reports'} />

            <div className="mx-auto max-w-3xl px-4 py-10 sm:px-6">
                {!report ? <Hub catalog={catalog} /> : <ReportView report={report} meta={meta} filters={filters} exportQuery={exportQuery} data={data} />}
            </div>
        </AuthenticatedLayout>
    );
}

function Hub({ catalog }: { catalog: CatalogItem[] }) {
    return (
        <div className="overflow-hidden rounded-2xl bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04)] ring-1 ring-black/[0.04]">
            <ul className="divide-y divide-gray-100">
                {catalog.map((item) => (
                    <li key={item.key}>
                        <Link
                            href={route('reports.index', { report: item.key })}
                            className="group flex items-center gap-4 px-5 py-4 transition hover:bg-gray-50/80 sm:px-6 sm:py-5"
                        >
                            <div className="min-w-0 flex-1">
                                <div className="text-[17px] font-medium tracking-tight text-gray-900 group-hover:text-brand-800">
                                    {item.title}
                                </div>
                                <div className="mt-0.5 text-sm text-gray-500">{item.blurb}</div>
                            </div>
                            <svg className="h-5 w-5 shrink-0 text-gray-300 transition group-hover:text-brand-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden>
                                <path fillRule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clipRule="evenodd" />
                            </svg>
                        </Link>
                    </li>
                ))}
            </ul>
        </div>
    );
}

function ReportView({
    report,
    meta,
    filters,
    exportQuery,
    data,
}: {
    report: string;
    meta?: CatalogItem;
    filters: any;
    exportQuery: Record<string, string | null | undefined>;
    data: any;
}) {
    const needsPeriod = ['fees', 'receipts', 'attendance'].includes(report);

    return (
        <div className="space-y-8">
            <div>
                <Link
                    href={route('reports.index')}
                    className="inline-flex items-center gap-1 text-sm font-medium text-brand-700 hover:text-brand-900"
                >
                    <svg className="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden>
                        <path fillRule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clipRule="evenodd" />
                    </svg>
                    All reports
                </Link>
                <h3 className="mt-3 text-2xl font-semibold tracking-tight text-gray-900">{meta?.title}</h3>
                <p className="mt-1 text-sm text-gray-500">{meta?.blurb}</p>
            </div>

            {needsPeriod && <PeriodBar report={report} filters={filters} />}

            {report === 'fees' && <FeesReport data={data} filters={filters} exportQuery={exportQuery} />}
            {report === 'receipts' && <ReceiptsReport data={data} filters={filters} exportQuery={exportQuery} />}
            {report === 'defaulters' && <DefaultersReport data={data} />}
            {report === 'pending_dues' && <PendingDuesReport data={data} />}
            {report === 'enquiries' && <EnquiriesReport data={data} />}
            {report === 'attendance' && <AttendanceReport data={data} filters={filters} />}
            {report === 'students' && <StudentsReport data={data} />}
        </div>
    );
}

function PeriodBar({ report, filters }: { report: string; filters: any }) {
    const [period, setPeriod] = useState(filters?.period || 'this_month');
    const [from, setFrom] = useState(filters?.from || '');
    const [to, setTo] = useState(filters?.to || '');

    const apply = (e?: FormEvent, nextPeriod?: string) => {
        e?.preventDefault();
        const p = nextPeriod ?? period;
        router.get(
            route('reports.index'),
            {
                report,
                period: p,
                ...(nextPeriod ? {} : { from: from || undefined, to: to || undefined }),
            },
            { preserveState: true, replace: true },
        );
    };

    return (
        <form onSubmit={(e) => apply(e)} className="space-y-4 rounded-2xl bg-white p-5 shadow-[0_1px_2px_rgba(0,0,0,0.04)] ring-1 ring-black/[0.04] sm:p-6">
            <div className="flex flex-wrap gap-2">
                {PERIODS.map((p) => (
                    <button
                        key={p.value}
                        type="button"
                        onClick={() => {
                            setPeriod(p.value);
                            apply(undefined, p.value);
                        }}
                        className={`rounded-full px-3.5 py-1.5 text-sm transition ${
                            period === p.value
                                ? 'bg-gray-900 text-white'
                                : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                        }`}
                    >
                        {p.label}
                    </button>
                ))}
            </div>
            <div className="flex flex-wrap items-end gap-3">
                <label className="text-sm text-gray-600">
                    From
                    <DateInput value={from} onChange={setFrom} className="mt-1 block rounded-xl border-gray-200 text-sm shadow-sm" />
                </label>
                <label className="text-sm text-gray-600">
                    To
                    <DateInput value={to} onChange={setTo} className="mt-1 block rounded-xl border-gray-200 text-sm shadow-sm" />
                </label>
                <button type="submit" className="rounded-full bg-brand-700 px-5 py-2 text-sm font-medium text-white hover:bg-brand-800">
                    Apply
                </button>
            </div>
            <p className="text-xs text-gray-400">
                {formatDateRange(filters?.from, filters?.to)}
            </p>
        </form>
    );
}

function Panel({ title, action, children }: { title?: string; action?: ReactNode; children: ReactNode }) {
    return (
        <section className="overflow-hidden rounded-2xl bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04)] ring-1 ring-black/[0.04]">
            {(title || action) && (
                <div className="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-3.5 sm:px-6">
                    {title ? <h4 className="text-sm font-medium text-gray-900">{title}</h4> : <span />}
                    {action}
                </div>
            )}
            <div className="px-5 py-4 sm:px-6 sm:py-5">{children}</div>
        </section>
    );
}

function exportHref(name: string, exportQuery: Record<string, string | null | undefined>) {
    const params = new URLSearchParams();
    Object.entries(exportQuery || {}).forEach(([k, v]) => {
        if (v != null && String(v) !== '') params.set(k, String(v));
    });
    const qs = params.toString();
    return route(name) + (qs ? `?${qs}` : '');
}

function FeesReport({ data, filters, exportQuery }: { data: any; filters: any; exportQuery: any }) {
    const [invoiceQ, setInvoiceQ] = useState(filters?.invoice_q || '');
    const [invoiceStatus, setInvoiceStatus] = useState(filters?.invoice_status || 'all');
    const invoices = data?.invoices;
    const collections = data?.collections || {};

    const search = (e: FormEvent) => {
        e.preventDefault();
        router.get(
            route('reports.index'),
            {
                report: 'fees',
                period: filters.period,
                from: filters.from,
                to: filters.to,
                invoice_q: invoiceQ || undefined,
                invoice_status: invoiceStatus !== 'all' ? invoiceStatus : undefined,
            },
            { preserveState: true, replace: true },
        );
    };

    return (
        <div className="space-y-6">
            <div className="grid gap-3 sm:grid-cols-2">
                <Stat label="Collected" value={money(data?.summary?.collected)} />
                <Stat
                    label="By mode"
                    value={
                        Object.keys(collections).length
                            ? Object.entries(collections)
                                  .map(([mode, total]) => `${mode}: ${money(total as number)}`)
                                  .join(' · ')
                            : '—'
                    }
                />
            </div>

            <Panel
                title="Invoices"
                action={
                    <a className="text-sm font-medium text-brand-700 hover:text-brand-900" href={exportHref('reports.invoices', exportQuery)}>
                        Export CSV
                    </a>
                }
            >
                <form onSubmit={search} className="mb-4 flex flex-wrap gap-2">
                    <input
                        value={invoiceQ}
                        onChange={(e) => setInvoiceQ(e.target.value)}
                        placeholder="Search student or invoice…"
                        className="min-w-[12rem] flex-1 rounded-xl border-gray-200 text-sm"
                    />
                    <select value={invoiceStatus} onChange={(e) => setInvoiceStatus(e.target.value)} className="rounded-xl border-gray-200 text-sm">
                        <option value="all">All statuses</option>
                        <option value="open">Open</option>
                        <option value="partial">Partial</option>
                        <option value="paid">Paid</option>
                    </select>
                    <button type="submit" className="rounded-full bg-gray-900 px-4 py-2 text-sm font-medium text-white">
                        Search
                    </button>
                </form>
                <SimpleTable
                    headers={['Invoice', 'Admission · Student', 'Batch', 'Total', 'Paid', 'Due', 'Due date', 'Status']}
                    rows={(invoices?.data || []).map((inv: any) => [
                        inv.invoice_no,
                        studentCell(inv),
                        inv.batch || '—',
                        money(inv.total),
                        money(inv.paid),
                        money(inv.due ?? Math.max(0, Number(inv.total) - Number(inv.paid_total ?? inv.paid ?? 0))),
                        formatDate(inv.due_date),
                        inv.display_status || inv.status,
                    ])}
                />
                <Pager links={invoices?.links} />
            </Panel>
        </div>
    );
}

function ReceiptsReport({ data, filters, exportQuery }: { data: any; filters: any; exportQuery: any }) {
    const [receiptQ, setReceiptQ] = useState(filters?.receipt_q || '');
    const receipts = data?.receipts;

    const search = (e: FormEvent) => {
        e.preventDefault();
        router.get(
            route('reports.index'),
            {
                report: 'receipts',
                period: filters.period,
                from: filters.from,
                to: filters.to,
                receipt_q: receiptQ || undefined,
            },
            { preserveState: true, replace: true },
        );
    };

    return (
        <div className="space-y-6">
            <div className="grid gap-3 sm:grid-cols-2">
                <Stat label="Receipts" value={String(data?.summary?.receipt_count ?? 0)} />
                <Stat label="Amount" value={money(data?.summary?.amount)} />
            </div>
            <Panel
                title="Receipt list"
                action={
                    <a className="text-sm font-medium text-brand-700 hover:text-brand-900" href={exportHref('reports.receipts', exportQuery)}>
                        Export CSV
                    </a>
                }
            >
                <form onSubmit={search} className="mb-4 flex flex-wrap gap-2">
                    <input
                        value={receiptQ}
                        onChange={(e) => setReceiptQ(e.target.value)}
                        placeholder="Search receipt or student…"
                        className="min-w-[12rem] flex-1 rounded-xl border-gray-200 text-sm"
                    />
                    <button type="submit" className="rounded-full bg-gray-900 px-4 py-2 text-sm font-medium text-white">
                        Search
                    </button>
                </form>
                <SimpleTable
                    headers={['Receipt', 'Student', 'Amount', 'Date']}
                    rows={(receipts?.data || []).map((r: any) => [
                        r.receipt_no,
                        r.student?.admission_no
                            ? `${r.student.admission_no} · ${r.student?.full_name || r.student?.first_name || '—'}`
                            : r.student?.full_name || r.student?.first_name || '—',
                        money(r.amount),
                        formatDate(r.issued_on),
                    ])}
                />
                <Pager links={receipts?.links} />
            </Panel>
        </div>
    );
}

function DefaultersReport({ data }: { data: any }) {
    return (
        <div className="space-y-6">
            <div className="grid gap-3 sm:grid-cols-2">
                <Stat label="Overdue bills" value={String(data?.summary?.count ?? 0)} />
                <Stat label="Total overdue" value={money(data?.summary?.due_total)} />
            </div>
            <Panel
                title="Past due — true defaulters"
                action={
                    <a className="text-sm font-medium text-brand-700 hover:text-brand-900" href={route('reports.defaulters')}>
                        Export CSV
                    </a>
                }
            >
                <p className="mb-4 text-xs text-gray-400">
                    Only unpaid bills whose due date is before today. For all open bills (including due today), see Pending collection.
                </p>
                <InvoiceDueTable rows={data?.rows || []} />
            </Panel>
        </div>
    );
}

function PendingDuesReport({ data }: { data: any }) {
    return (
        <div className="space-y-6">
            <div className="grid gap-3 sm:grid-cols-2">
                <Stat label="Open / partial" value={String(data?.summary?.count ?? 0)} />
                <Stat label="Total due" value={money(data?.summary?.due_total)} />
            </div>
            <Panel
                title="All outstanding bills"
                action={
                    <a className="text-sm font-medium text-brand-700 hover:text-brand-900" href={route('reports.pending')}>
                        Export CSV
                    </a>
                }
            >
                <p className="mb-4 text-xs text-gray-400">
                    Every open or partial invoice — including brand-new bills due today. Overdue-only list is under Overdue defaulters.
                </p>
                <InvoiceDueTable rows={data?.rows || []} />
            </Panel>
        </div>
    );
}

function InvoiceDueTable({ rows }: { rows: any[] }) {
    return (
        <SimpleTable
            headers={['Invoice', 'Admission · Student', 'Batch', 'Total', 'Paid', 'Due', 'Due date', 'Status']}
            rows={rows.map((row: any) => [
                row.invoice_no,
                studentCell(row),
                row.batch || '—',
                money(row.total),
                money(row.paid),
                money(row.due),
                formatDate(row.due_date),
                row.display_status || row.status,
            ])}
        />
    );
}

function EnquiriesReport({ data }: { data: any }) {
    const labels = data?.labels || {};
    const pipeline = data?.pipeline || {};

    return (
        <div className="space-y-6">
            <div className="grid gap-3 sm:grid-cols-2">
                <Stat label="Open pipeline" value={String(data?.open ?? 0)} />
                <Stat label="All time" value={String(data?.total ?? 0)} />
            </div>
            <Panel title="By status">
                <ul className="divide-y divide-gray-100">
                    {Object.entries(labels).map(([key, label]) => (
                        <li key={key} className="flex items-center justify-between py-3 text-sm">
                            <span className="text-gray-600">{label as string}</span>
                            <span className="tabular-nums font-medium text-gray-900">{pipeline[key] ?? 0}</span>
                        </li>
                    ))}
                </ul>
            </Panel>
        </div>
    );
}

function AttendanceReport({ data, filters }: { data: any; filters: any }) {
    const byStatus = data?.by_status || {};

    return (
        <div className="space-y-6">
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <Stat label={`Classes · ${filters?.from} → ${filters?.to}`} value={String(data?.sessions ?? 0)} />
                <Stat label="Present" value={String(data?.present ?? 0)} />
                <Stat label="Absent" value={String(data?.absent ?? 0)} />
                <Stat label="Late" value={String(data?.late ?? 0)} />
                <Stat label="Leave" value={String(data?.leave ?? 0)} />
            </div>

            <Panel title="By batch">
                <SimpleTable
                    headers={['Batch', 'Present', 'Absent', 'Late', 'Leave']}
                    rows={(data?.by_batch || []).map((b: any) => [
                        b.batch,
                        String(b.present),
                        String(b.absent),
                        String(b.late),
                        String(b.leave),
                    ])}
                />
            </Panel>

            <Panel title="Recent absences">
                {(data?.recent_absences || []).length ? (
                    <SimpleTable
                        headers={['Date', 'Student', 'Batch', 'Subject', 'Topic']}
                        rows={(data?.recent_absences || []).map((r: any) => [
                            r.date || '—',
                            `${r.admission_no ? `${r.admission_no} · ` : ''}${r.student || '—'}`,
                            r.batch || '—',
                            r.subject || '—',
                            r.topic || '—',
                        ])}
                    />
                ) : (
                    <p className="text-sm text-gray-500">No absences in this period.</p>
                )}
            </Panel>

            {data?.total_marks ? (
                <Panel title="All marks">
                    <ul className="divide-y divide-gray-100">
                        {Object.entries(byStatus).map(([status, total]) => (
                            <li key={status} className="flex items-center justify-between py-3 text-sm">
                                <span className="capitalize text-gray-600">{status}</span>
                                <span className="tabular-nums font-medium text-gray-900">{total as number}</span>
                            </li>
                        ))}
                    </ul>
                </Panel>
            ) : null}
        </div>
    );
}

function StudentsReport({ data }: { data: any }) {
    return (
        <div className="space-y-6">
            <div className="grid gap-3 sm:grid-cols-3">
                <Stat label="Active" value={String(data?.active ?? 0)} />
                <Stat label="Left" value={String(data?.left ?? 0)} />
                <Stat label="No batch" value={String(data?.unassigned ?? 0)} />
            </div>
            <Panel title="Batch strength">
                <SimpleTable
                    headers={['Batch', 'Students', 'Capacity']}
                    rows={(data?.batches || []).map((b: any) => [
                        b.name,
                        String(b.students),
                        b.capacity != null ? String(b.capacity) : '—',
                    ])}
                />
            </Panel>
        </div>
    );
}

function Stat({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-2xl bg-white px-5 py-4 shadow-[0_1px_2px_rgba(0,0,0,0.04)] ring-1 ring-black/[0.04]">
            <div className="text-xs font-medium uppercase tracking-wide text-gray-400">{label}</div>
            <div className="mt-1 text-xl font-semibold tracking-tight text-gray-900">{value}</div>
        </div>
    );
}

function SimpleTable({ headers, rows }: { headers: string[]; rows: (string | number)[][] }) {
    if (!rows.length) {
        return <p className="py-6 text-center text-sm text-gray-500">Nothing to show.</p>;
    }

    return (
        <div className="overflow-x-auto">
            <table className="min-w-full text-left text-sm">
                <thead>
                    <tr className="border-b border-gray-100 text-xs font-medium uppercase tracking-wide text-gray-400">
                        {headers.map((h) => (
                            <th key={h} className="whitespace-nowrap px-1 py-2 pr-4 font-medium">
                                {h}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-50">
                    {rows.map((row, i) => (
                        <tr key={i} className="text-gray-800">
                            {row.map((cell, j) => (
                                <td key={j} className="whitespace-nowrap px-1 py-3 pr-4">
                                    {cell}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function Pager({ links }: { links?: { url: string | null; label: string; active: boolean }[] }) {
    if (!links || links.length <= 3) return null;

    return (
        <div className="mt-4 flex flex-wrap justify-center gap-1 border-t border-gray-100 pt-4">
            {links.map((link, i) =>
                link.url ? (
                    <Link
                        key={i}
                        href={link.url}
                        preserveState
                        className={`rounded-lg px-3 py-1.5 text-xs ${
                            link.active ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100'
                        }`}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ) : (
                    <span key={i} className="rounded-lg px-3 py-1.5 text-xs text-gray-300" dangerouslySetInnerHTML={{ __html: link.label }} />
                ),
            )}
        </div>
    );
}
