import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

export default function Guest({ children }: PropsWithChildren) {
    return (
        <div className="flex min-h-screen flex-col items-center bg-canvas px-4 pt-8 sm:justify-center sm:pt-0">
            <div className="flex flex-col items-center gap-3">
                <Link href="/" className="flex items-center gap-2.5 text-brand-700">
                    <ApplicationLogo className="h-10 w-10" />
                    <span className="text-xl font-semibold tracking-tight text-ink">CoachingDesk</span>
                </Link>
            </div>

            <div className="mt-8 w-full overflow-hidden rounded-xl border border-black/[0.06] bg-white px-6 py-5 shadow-sm sm:max-w-md">
                {children}
            </div>
        </div>
    );
}
