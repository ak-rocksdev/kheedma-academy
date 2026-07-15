// Application intake status — labels (ID) + badge variants, shared across views.

export const APPLICATION_STATUSES = [
    { value: 'pending', label: 'Menunggu' },
    { value: 'accepted', label: 'Diterima' },
    { value: 'rejected', label: 'Ditolak' },
];

export function statusVariant(status) {
    return { pending: 'warning', accepted: 'success', rejected: 'destructive' }[status] ?? 'secondary';
}

export function statusLabel(status) {
    return APPLICATION_STATUSES.find((s) => s.value === status)?.label ?? status;
}

// Program catalog status — shared by the catalog list and the detail header.

const PROGRAM_STATUSES = {
    draft: { label: 'Draf', variant: 'secondary' },
    active: { label: 'Aktif', variant: 'success' },
    inactive: { label: 'Nonaktif', variant: 'destructive' },
};

export function programStatusVariant(status) {
    return PROGRAM_STATUSES[status]?.variant ?? 'secondary';
}

export function programStatusLabel(status) {
    return PROGRAM_STATUSES[status]?.label ?? status;
}

// Cohort lifecycle (derived upcoming/active/ended) — shared by cohort tables.

const COHORT_STATUSES = {
    upcoming: { label: 'Akan datang', variant: 'warning' },
    active: { label: 'Berjalan', variant: 'success' },
    ended: { label: 'Selesai', variant: 'secondary' },
};

export function cohortStatusVariant(status) {
    return COHORT_STATUSES[status]?.variant ?? 'secondary';
}

export function cohortStatusLabel(status) {
    return COHORT_STATUSES[status]?.label ?? status;
}
