import Dropdown from '@/Components/Dropdown';
import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren, ReactNode } from 'react';

export default function PlatformLayout({
    header,
    children,
}: PropsWithChildren<{ header?: ReactNode }>) {
    const { auth, flash } = usePage().props as any;

    return (
        <div className="min-h-screen bg-slate-100">
            <nav className="bg-slate-900 text-white">
                <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                    <div className="flex items-center gap-8">
                        <Link href={route('platform.coachings.index')} className="text-sm font-semibold uppercase tracking-widest text-brand-300">
                            Provider console
                        </Link>
                        <Link
                            href={route('platform.coachings.index')}
                            className="border-b-2 border-brand-400 pb-1 text-sm font-medium"
                        >
                            Coachings
                        </Link>
                    </div>

                    <Dropdown>
                        <Dropdown.Trigger>
                            <button type="button" className="text-sm font-medium text-slate-200 hover:text-white">
                                {auth.user.name}
                            </button>
                        </Dropdown.Trigger>
                        <Dropdown.Content>
                            <Dropdown.Link href={route('profile.edit')}>Profile</Dropdown.Link>
                            <Dropdown.Link href={route('logout')} method="post" as="button">
                                Log Out
                            </Dropdown.Link>
                        </Dropdown.Content>
                    </Dropdown>
                </div>
            </nav>

            {header && (
                <header className="bg-white shadow-sm">
                    <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">{header}</div>
                </header>
            )}

            {(flash?.success || flash?.error) && (
                <div className="mx-auto max-w-7xl px-4 pt-4 sm:px-6 lg:px-8">
                    <div
                        className={
                            'rounded-md px-4 py-3 text-sm ' +
                            (flash.error
                                ? 'bg-red-50 text-red-800 ring-1 ring-red-200'
                                : 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200')
                        }
                    >
                        {flash.error || flash.success}
                    </div>
                </div>
            )}

            <main>{children}</main>
        </div>
    );
}
