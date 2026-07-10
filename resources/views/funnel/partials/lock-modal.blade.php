{{-- Lock explainer modal. Filled and toggled by initLockModal() in app.js. --}}
<div id="lock-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="lock-modal-title">
    <div class="absolute inset-0 bg-teal-950/70 backdrop-blur-sm" data-lock-close></div>
    <div class="relative z-10 w-full max-w-md rounded-3xl border border-teal-900/10 bg-white p-7 shadow-xl sm:p-8">
        <span class="inline-block rounded-full bg-teal-100 px-3 py-1 text-[0.65rem] font-semibold uppercase tracking-wide text-teal-800">Terkunci</span>
        <h2 id="lock-modal-title" class="mt-3 text-xl font-bold text-teal-900">Kelas ini belum terbuka untukmu</h2>
        <p id="lock-modal-message" class="mt-2 text-sm leading-relaxed text-teal-800/80"></p>

        {{-- Guest CTAs: route into the funnel. Hidden for logged-in members.
             Stacked full-width so labels never wrap inside the narrow modal. --}}
        <div id="lock-modal-guest-actions" class="mt-6 hidden flex-col gap-2.5">
            <a href="{{ route('daftar') }}" class="inline-flex w-full items-center justify-center rounded-full bg-orange-500 px-5 py-3 text-sm font-semibold text-white transition hover:bg-orange-600">Lihat program yang dibuka</a>
            <a href="{{ url('/komunitas') }}" class="inline-flex w-full items-center justify-center rounded-full border border-teal-900/15 px-5 py-3 text-sm font-semibold text-teal-900 transition hover:bg-teal-50">Gabung Komunitas</a>
        </div>

        <div class="mt-4 text-center">
            <button type="button" data-lock-close class="text-sm font-medium text-teal-700 underline-offset-4 hover:underline">Tutup</button>
        </div>
    </div>
</div>
