{{-- Reusable confirm/dialog modal for member-facing pages. Open it from any
     element carrying data-modal-open="{id}"; closes via the X, any
     [data-modal-close], the backdrop, or Escape. `autoopen` reopens it on
     page load (e.g. a validation redirect). Behavior: initModals() in
     resources/js/app.js; entrance animation: .kh-modal-card in app.css. --}}
@props([
    'id',
    'title',
    'autoopen' => false,
])
<div id="{{ $id }}" data-modal @if ($autoopen) data-modal-autoopen @endif
     class="fixed inset-0 z-50 hidden items-center justify-center p-4 sm:p-6">
    <div class="absolute inset-0 bg-teal-950/60 backdrop-blur-sm" data-modal-close aria-hidden="true"></div>
    <div class="kh-modal-card relative w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl sm:p-7"
         role="dialog" aria-modal="true" aria-label="{{ $title }}">
        <div class="flex items-start justify-between gap-4">
            <h3 class="text-lg font-bold leading-snug text-teal-900">{{ $title }}</h3>
            <button type="button" data-modal-close aria-label="Tutup"
                    class="flex size-8 shrink-0 items-center justify-center rounded-full text-teal-800/50 transition hover:bg-sand-100 hover:text-teal-900">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </div>
        <div class="mt-3">
            {{ $slot }}
        </div>
    </div>
</div>
