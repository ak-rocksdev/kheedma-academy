// Date formatting shared across admin views.

/** "07 Jul 2026" in id-ID; em dash placeholder when empty. */
export function fmtDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

/** Human date range of a cohort; null when neither date is set (unscheduled). */
export function cohortPeriodLabel(cohort) {
    if (!cohort.start_date && !cohort.end_date) {
        return null;
    }

    return `${fmtDate(cohort.start_date)} – ${fmtDate(cohort.end_date)}`;
}
