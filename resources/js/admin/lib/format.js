// Date formatting shared across admin views.

/** "07 Jul 2026" in id-ID; em dash placeholder when empty. */
export function fmtDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

/**
 * "07 Jul 2026" or "07 Jul 2026, 14.30" when the ISO datetime carries a
 * non-midnight local time — midnight means "date only" upstream (e.g. a
 * cohort start_date saved without a specific time), so the clock is hidden.
 */
export function fmtDateTime(iso) {
    if (!iso) return '—';
    const date = new Date(iso);
    const isMidnight = date.getHours() === 0 && date.getMinutes() === 0;
    if (isMidnight) return fmtDate(iso);
    return `${fmtDate(iso)}, ${date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })}`;
}

/** 'YYYY-MM-DDTHH:mm' in local time for <input type="datetime-local">; '' when empty. */
export function toDatetimeLocal(iso) {
    if (!iso) return '';
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) return '';
    const pad = (n) => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

/** Human date range of a cohort; null when neither date is set (unscheduled). */
export function cohortPeriodLabel(cohort) {
    if (!cohort.start_date && !cohort.end_date) {
        return null;
    }

    return `${fmtDate(cohort.start_date)} – ${fmtDate(cohort.end_date)}`;
}
