{{-- 6-digit PIN entry, e-wallet style: two groups of three boxes (the way
     Indonesians read "123 456"), masked dots with one eye toggle for all
     boxes. The boxes are nameless — the hidden input carries the composed
     value under `name`, so the backend keeps seeing a plain `password`
     field. Behavior lives in initPinInputs() (resources/js/app.js). --}}
@props([
    'name' => 'password',
    'autocomplete' => 'new-password',
    'invalid' => false,
])
<div data-pin-input class="mt-1.5 flex items-center gap-2" role="group" aria-label="PIN 6 digit">
    @foreach (range(1, 6) as $digit)
        <input
            type="password"
            inputmode="numeric"
            maxlength="6"
            autocomplete="off"
            autocorrect="off"
            spellcheck="false"
            data-pin-box
            data-lpignore="true"
            data-1p-ignore="true"
            aria-label="Digit {{ $digit }}"
            @class([
                'h-14 w-full min-w-0 max-w-12 rounded-xl bg-white text-center text-xl font-semibold text-teal-900 caret-orange-500 outline-none transition focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20',
                'ml-2 sm:ml-4' => $digit === 4,
                'border border-red-400' => $invalid,
                'border border-teal-900/15' => ! $invalid,
            ])
        />
    @endforeach
    <button type="button" data-pin-toggle aria-label="Tampilkan PIN"
            class="ml-1 flex h-14 w-10 shrink-0 items-center justify-center rounded-xl text-teal-700/60 transition hover:bg-sand-100 hover:text-teal-700">
        <svg data-pin-eye viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
        <svg data-pin-eye-off viewBox="0 0 24 24" class="hidden h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.4 10.4 0 0 1 12 5c6.5 0 10 7 10 7a13.2 13.2 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.5 13.5 0 0 0 2 12s3.5 7 10 7a9.7 9.7 0 0 0 5.39-1.61"/><line x1="2" y1="2" x2="22" y2="22"/></svg>
    </button>
    <input type="hidden" name="{{ $name }}" autocomplete="{{ $autocomplete }}">
</div>
