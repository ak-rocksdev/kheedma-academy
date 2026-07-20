{{-- One toast card. Lives inside <x-toast-stack>; behavior (slide in,
     auto-dismiss, X to close) comes from initToasts() in resources/js/app.js,
     entrance animation from .kh-toast in app.css. --}}
@props([
    'type' => 'success',
    'message' => '',
])
@php($disc = match ($type) {
    'error' => 'bg-red-100 text-red-600',
    'warning' => 'bg-orange-100 text-orange-600',
    'info' => 'bg-sand-100 text-teal-700',
    default => 'bg-teal-100 text-teal-700',
})
@php($bar = match ($type) {
    'error' => 'bg-red-500',
    'warning' => 'bg-orange-500',
    'info' => 'bg-teal-600/50',
    default => 'bg-teal-600',
})
<div data-toast role="status"
     class="kh-toast pointer-events-auto w-full max-w-sm overflow-hidden rounded-2xl bg-white shadow-lg ring-1 ring-teal-900/10">
    <div class="flex items-center gap-3 px-4 py-3">
        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ $disc }}">
            @if ($type === 'error')
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M7 7l6 6M13 7l-6 6"/></svg>
            @elseif ($type === 'warning')
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 7v4m0 3h.01M8.6 3.6 2.9 13.8A1.6 1.6 0 0 0 4.3 16h11.4a1.6 1.6 0 0 0 1.4-2.2L11.4 3.6a1.6 1.6 0 0 0-2.8 0Z"/></svg>
            @elseif ($type === 'info')
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="10" cy="10" r="7.25"/><path d="M10 9v4m0-7h.01"/></svg>
            @else
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10.5l4 4 8-9"/></svg>
        @endif
        </span>
        <p class="min-w-0 flex-1 text-sm font-medium leading-snug text-teal-900">{{ $message }}</p>
        <button type="button" data-toast-close aria-label="Tutup"
                class="flex size-7 shrink-0 items-center justify-center rounded-full text-teal-800/40 transition hover:bg-sand-100 hover:text-teal-900">
            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>
        </button>
    </div>
    {{-- Timer bar: drains over the dismiss window, pauses while hovered. --}}
    <div class="h-1 w-full bg-teal-900/5">
        <div data-toast-timer class="kh-toast-timer h-full {{ $bar }}"></div>
    </div>
</div>
