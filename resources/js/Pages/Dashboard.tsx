/*  */import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';

type Stats = {
    students: number;
    open_invoices: number;
    dues_amount: number;
    due_today_count: number;
    due_today_amount: number;
    overdue_count: number;
    overdue_amount: number;
    partial_count: number;
    collected_today: number;
    enquiries_open: number;
    absent_today: number;
};

const money = (n: number) => `₹${Number(n || 0).toLocaleString('en-IN')}`;

export default function Dashboard({ stats }: { stats: Stats }) {
    const flash = usePage().props.flash as { success?: string };

    const fees = [
        { label: 'Due today', value: `${stats.due_today_count}`, sub: money(stats.due_today_amount) },
        { label: 'Overdue', value: `${stats.overdue_count}`, sub: money(stats.overdue_amount) },
        { label: 'Collected today', value: money(stats.collected_today) },
        { label: 'Partial payments', value: `${stats.partial_count}` },
        { label: 'Outstanding', value: money(stats.dues_amount), sub: `${stats.open_invoices} open bills` },
    ];

    const other = [
        { label: 'Active students', value: stats.students },
        { label: 'Open enquiries', value: stats.enquiries_open },
        { label: 'Absent today', value: stats.absent_today },
    ];

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-ink">Dashboard</h2>}>
            <Head title="Dashboard" />
            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {flash?.success && (
                        <div className="rounded border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-800">
                            {flash.success}
                        </div>
                    )}

                    <div>
                        <div className="mb-3 flex items-center justify-between">
                            <h3 className="text-sm font-semibold text-gray-800">Fees today</h3>
                            <Link href={route('fees.index')} className="text-xs text-teal-700 underline">Open Fees</Link>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                            {fees.map((card) => (
                                <div key={card.label} className="rounded-lg bg-white p-5 shadow-sm">
                                    <div className="text-sm text-gray-500">{card.label}</div>
                                    <div className="mt-2 text-2xl font-semibold text-gray-900">{card.value}</div>
                                    {'sub' in card && card.sub && (
                                        <div className="mt-1 text-xs text-gray-500">{card.sub}</div>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-3">
                        {other.map((card) => (
                            <div key={card.label} className="rounded-lg bg-white p-5 shadow-sm">
                                <div className="text-sm text-gray-500">{card.label}</div>
                                <div className="mt-2 text-2xl font-semibold text-gray-900">{card.value}</div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
