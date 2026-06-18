// Application intake status — labels (ID) + badge variants, shared across views.

export const APPLICATION_STATUSES = [
    { value: 'pending', label: 'Menunggu' },
    { value: 'accepted', label: 'Diterima' },
    { value: 'rejected', label: 'Ditolak' },
];

export const PREFILTER_VERDICTS = [
    { value: '', label: '—' },
    { value: 'pending', label: 'Menunggu' },
    { value: 'approved', label: 'Lolos' },
    { value: 'rejected', label: 'Gagal' },
];

export function statusVariant(status) {
    return { pending: 'warning', accepted: 'success', rejected: 'destructive' }[status] ?? 'secondary';
}

export function statusLabel(status) {
    return APPLICATION_STATUSES.find((s) => s.value === status)?.label ?? status;
}
