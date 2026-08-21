import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatDate } from '@/lib/formatDate';
import { Head } from '@inertiajs/react';

const money = (n: number | string | null | undefined) =>
    `₹${Number(n || 0).toLocaleString('en-IN')}`;

export default function ParentIndex({ students, attendance, invoices, receipts, announcements, notes, feeSummary }: any) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">Parent portal</h2>}>
            <Head title="Parent portal" />
            <div className="mx-auto max-w-5xl space-y-6 px-4 py-8">
                <Section title="Fee balance">
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <Stat label="Total billed (open)" value={money(feeSummary?.total_due)} />
                        <Stat label="Paid so far" value={money(feeSummary?.lifetime_paid)} />
                        <Stat label="Remaining" value={money(feeSummary?.remaining)} />
                        <Stat
                            label="Next due"
                            value={feeSummary?.next_due_date
                                ? `${formatDate(feeSummary.next_due_date)} · ${money(feeSummary.next_due_amount)}`
                                : 'Nothing pending'}
                        />
                    </div>
                </Section>

                <Section title="My students">
                    {students.map((s: any) => (
                        <div key={s.id} className="border-t py-2 text-sm">{s.first_name} {s.last_name} · {s.enrolments?.[0]?.batch?.name || '—'}</div>
                    ))}
                </Section>
                <Section title="Recent attendance">
                    {attendance.map((a: any) => (
                        <div key={a.id} className="border-t py-2 text-sm">
                            {formatDate(a.class_session?.session_date)} · {a.class_session?.batch?.name} · <span className="font-semibold">{a.status}</span>
                        </div>
                    ))}
                </Section>
                <Section title="Bills">
                    {invoices.length === 0 && <p className="text-sm text-gray-500">No bills yet.</p>}
                    {invoices.map((i: any) => (
                        <div key={i.id} className="flex flex-wrap items-center justify-between gap-2 border-t py-2 text-sm">
                            <span>{i.period_label || i.invoice_no} · {money(i.total)} · paid {money(i.paid_total)}</span>
                            <StatusPill status={i.display_status || i.status} />
                        </div>
                    ))}
                </Section>
                <Section title="Receipts">
                    {receipts.map((r: any) => (
                        <div key={r.id} className="border-t py-2 text-sm">{r.receipt_no} · {money(r.amount)} · {formatDate(r.issued_on)}</div>
                    ))}
                </Section>
                <Section title="Announcements">
                    {announcements.map((a: any) => (
                        <div key={a.id} className="border-t py-2 text-sm"><div className="font-medium">{a.title}</div>{a.body}</div>
                    ))}
                </Section>
                <Section title="Notes">
                    {notes.map((n: any) => (
                        <div key={n.id} className="border-t py-2 text-sm">{n.title}</div>
                    ))}
                </Section>
            </div>
        </AuthenticatedLayout>
    );
}

function Stat({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-lg bg-slate-50 px-3 py-3">
            <div className="text-xs text-gray-500">{label}</div>
            <div className="mt-1 text-base font-semibold text-gray-900">{value}</div>
        </div>
    );
}

function StatusPill({ status }: { status: string }) {
    const styles: Record<string, string> = {
        paid: 'bg-emerald-50 text-emerald-800',
        partial: 'bg-amber-50 text-amber-800',
        due: 'bg-sky-50 text-sky-800',
        overdue: 'bg-rose-50 text-rose-800',
        not_due: 'bg-gray-100 text-gray-600',
        open: 'bg-sky-50 text-sky-800',
    };
    const label = status === 'not_due' ? 'Not due' : status.replace('_', ' ');

    return (
        <span className={`rounded-full px-2 py-0.5 text-xs font-medium capitalize ${styles[status] || 'bg-gray-100 text-gray-700'}`}>
            {label}
        </span>
    );
}

function Section({ title, children }: any) {
    return (
        <div className="rounded-lg bg-white p-4 shadow-sm">
            <h3 className="font-semibold">{title}</h3>
            <div className="mt-2">{children}</div>
        </div>
    );
}
