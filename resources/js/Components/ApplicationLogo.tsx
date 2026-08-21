import { SVGAttributes } from 'react';

/** Compact brand mark — pair with "CoachingDesk" text in layouts. */
export default function ApplicationLogo(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 32 32"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden
        >
            <rect width="32" height="32" rx="8" fill="currentColor" />
            <path
                d="M22.5 10.5a7.5 7.5 0 1 0 0 11"
                stroke="#fff"
                strokeWidth="2.75"
                strokeLinecap="round"
            />
            <circle cx="22.5" cy="16" r="1.6" fill="#fff" />
        </svg>
    );
}
