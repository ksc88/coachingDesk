import { DATE_DISPLAY_HINT, displayToIso, isoToDisplay } from '@/lib/formatDate';
import { InputHTMLAttributes, useEffect, useState } from 'react';

type DateInputProps = Omit<InputHTMLAttributes<HTMLInputElement>, 'type' | 'value' | 'onChange'> & {
    /** ISO yyyy-mm-dd */
    value: string;
    onChange: (iso: string) => void;
};

function formatAsTyping(raw: string): string {
    const digits = raw.replace(/\D/g, '').slice(0, 8);
    if (digits.length <= 2) return digits;
    if (digits.length <= 4) return `${digits.slice(0, 2)}-${digits.slice(2)}`;
    return `${digits.slice(0, 2)}-${digits.slice(2, 4)}-${digits.slice(4)}`;
}

export default function DateInput({ value, onChange, className = '', onBlur, ...props }: DateInputProps) {
    const [text, setText] = useState(() => isoToDisplay(value));

    useEffect(() => {
        setText(isoToDisplay(value));
    }, [value]);

    const commit = (formatted: string) => {
        if (formatted === '') {
            onChange('');
            return;
        }
        if (formatted.length === 10) {
            const iso = displayToIso(formatted);
            if (iso) {
                onChange(iso);
                setText(isoToDisplay(iso));
            }
        }
    };

    return (
        <input
            {...props}
            type="text"
            inputMode="numeric"
            autoComplete="off"
            placeholder={DATE_DISPLAY_HINT}
            maxLength={10}
            className={className}
            value={text}
            onChange={(e) => {
                const formatted = formatAsTyping(e.target.value);
                setText(formatted);
                if (formatted.length === 10) {
                    commit(formatted);
                } else if (formatted === '') {
                    onChange('');
                }
            }}
            onBlur={(e) => {
                commit(text);
                if (text !== '' && text.length !== 10) {
                    setText(isoToDisplay(value));
                }
                onBlur?.(e);
            }}
        />
    );
}
