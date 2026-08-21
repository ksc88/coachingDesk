/** Project display format: dd-mm-yyyy */
export const DATE_DISPLAY_HINT = 'dd-mm-yyyy';

/** ISO yyyy-mm-dd → dd-mm-yyyy for empty-safe form display. */
export function isoToDisplay(iso: string | null | undefined): string {
    if (!iso) return '';
    const raw = iso.slice(0, 10);
    const [y, m, d] = raw.split('-');
    if (!y || !m || !d || y.length !== 4) return '';
    return `${d}-${m}-${y}`;
}

/** dd-mm-yyyy → ISO yyyy-mm-dd for API/storage. */
export function displayToIso(display: string | null | undefined): string {
    if (!display) return '';
    const trimmed = display.trim();
    if (/^\d{4}-\d{2}-\d{2}$/.test(trimmed)) {
        return trimmed;
    }
    const match = trimmed.match(/^(\d{1,2})-(\d{1,2})-(\d{4})$/);
    if (!match) return '';
    const [, d, m, y] = match;
    const day = d.padStart(2, '0');
    const month = m.padStart(2, '0');
    const iso = `${y}-${month}-${day}`;
    const date = new Date(`${iso}T00:00:00`);
    if (Number.isNaN(date.getTime())) return '';
    if (
        date.getFullYear() !== Number(y)
        || date.getMonth() + 1 !== Number(month)
        || date.getDate() !== Number(day)
    ) {
        return '';
    }
    return iso;
}

/** Display date as dd-mm-yyyy. Accepts ISO yyyy-mm-dd, datetime, or Date. */
export function formatDate(value: string | Date | null | undefined): string {
    if (!value) return '—';
    if (value instanceof Date) {
        return isoToDisplay(value.toISOString()) || '—';
    }
    return isoToDisplay(value) || String(value);
}

/** @deprecated Use formatDate */
export const formatDateIndia = formatDate;

export function formatDateRange(
    from: string | null | undefined,
    to: string | null | undefined,
    separator = ' → ',
): string {
    if (!from && !to) return '—';
    if (from && to) return `${formatDate(from)}${separator}${formatDate(to)}`;
    return formatDate(from || to);
}
