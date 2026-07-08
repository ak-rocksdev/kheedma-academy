<x-layouts.public title="Pendaftaran"
    description="Pilih jalurmu di Kheedma Academy: daftar program yang sedang dibuka atau gabung komunitas affiliator.">

    <section class="relative overflow-hidden">
        <div class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full bg-teal-100 blur-3xl"></div>

        <div class="relative mx-auto max-w-3xl px-6 py-16 sm:py-20">
            <div class="text-center">
                <x-logo variant="stacked" class="mx-auto h-20" />
                <p class="mt-8 font-display text-xs uppercase tracking-[0.3em] text-orange-600">Pendaftaran</p>
                <h1 class="mt-3 text-3xl font-bold leading-tight text-teal-900 sm:text-4xl">Pilih jalurmu.</h1>
                <p class="mx-auto mt-4 max-w-lg text-base leading-relaxed text-teal-800/80">
                    Ikuti program yang sedang dibuka, atau mulai lebih dulu dari komunitas.
                </p>
            </div>

            <div class="mt-10 space-y-4">
                @if ($programs->isNotEmpty())
                    <p class="font-display text-xs uppercase tracking-[0.3em] text-teal-700">Program</p>
                @endif
                @foreach ($programs as $program)
                    <a href="{{ route('program.show', $program) }}"
                       class="block rounded-3xl border border-teal-900/10 bg-white/70 p-6 shadow-sm backdrop-blur transition hover:border-teal-600/40 hover:shadow-md sm:p-8">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <span class="inline-block rounded-full bg-orange-100 px-3 py-1 text-[0.65rem] font-semibold uppercase tracking-wide text-orange-700">Dibuka</span>
                                <h2 class="mt-3 text-xl font-bold text-teal-900">{{ $program->name }}</h2>
                                @if ($program->tagline)
                                    <p class="mt-1.5 text-sm leading-relaxed text-teal-800/70">{{ $program->tagline }}</p>
                                @endif
                            </div>
                            <svg class="h-5 w-5 shrink-0 text-teal-700" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 10h12M11 5l5 5-5 5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                    </a>
                @endforeach

                <a href="{{ url('/komunitas') }}"
                   class="block rounded-3xl border border-teal-900/10 bg-teal-900 p-6 shadow-sm transition hover:bg-teal-800 hover:shadow-md sm:p-8">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <span class="inline-block rounded-full bg-white/10 px-3 py-1 text-[0.65rem] font-semibold uppercase tracking-wide text-orange-300">Gratis</span>
                            <h2 class="mt-3 text-xl font-bold text-white">Gabung Komunitas Affiliator</h2>
                            <p class="mt-1.5 text-sm leading-relaxed text-white/70">
                                Belum siap ikut program? Mulai dari komunitas: materi, kabar terbaru, dan teman seperjalanan.
                            </p>
                        </div>
                        <svg class="h-5 w-5 shrink-0 text-white" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 10h12M11 5l5 5-5 5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                </a>
            </div>

            {{-- Jenjang kelas komunitas: sengaja DI BAWAH kartu gabung, karena
                 kelas-kelas ini adalah perjalanan setelah bergabung. Level berjajar
                 horizontal (Level 1 paling kiri); lebih dari muat layar tinggal
                 digulir ke kanan. --}}
            @if ($affiliate->isNotEmpty())
                <div class="mt-12">
                    <p class="font-display text-xs uppercase tracking-[0.3em] text-teal-700">Kheedma Affiliate Community</p>
                    <p class="mt-2 text-sm leading-relaxed text-teal-800/70">
                        Jenjang kelas komunitas. Terbuka bertahap setelah kamu menyelesaikan program.
                    </p>

                    <div class="-mx-6 mt-5 flex snap-x snap-mandatory gap-4 overflow-x-auto px-6 pb-3 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                        @foreach ($affiliate as $entry)
                            @if ($entry['locked'])
                                <button
                                    type="button"
                                    data-lock-trigger
                                    data-lock-message="{{ $entry['message'] }}"
                                    data-lock-reason="{{ $entry['reason'] }}"
                                    class="flex w-64 shrink-0 snap-start flex-col rounded-3xl border border-teal-900/10 bg-white/40 p-6 text-left opacity-75 shadow-sm transition hover:opacity-100"
                                >
                                    <span class="self-start rounded-full bg-teal-100 px-3 py-1 text-[0.65rem] font-semibold uppercase tracking-wide text-teal-800">Level {{ $entry['program']->level }}</span>
                                    <h2 class="mt-3 text-lg font-bold leading-snug text-teal-900/70">{{ $entry['program']->name }}</h2>
                                    @if ($entry['program']->tagline)
                                        <p class="mt-1.5 text-sm leading-relaxed text-teal-800/50">{{ $entry['program']->tagline }}</p>
                                    @endif
                                    <span class="mt-auto flex items-center gap-1.5 pt-4 text-xs font-semibold uppercase tracking-wide text-teal-700/60">
                                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="5" y="9" width="10" height="7" rx="1.5"/><path d="M7 9V6.5a3 3 0 0 1 6 0V9" stroke-linecap="round"/>
                                        </svg>
                                        Terkunci
                                    </span>
                                </button>
                            @else
                                <a href="{{ route('program.show', $entry['program']) }}"
                                   class="flex w-64 shrink-0 snap-start flex-col rounded-3xl border border-teal-900/10 bg-white/70 p-6 shadow-sm backdrop-blur transition hover:border-teal-600/40 hover:shadow-md">
                                    <span class="self-start rounded-full bg-orange-100 px-3 py-1 text-[0.65rem] font-semibold uppercase tracking-wide text-orange-700">Level {{ $entry['program']->level }}{{ $entry['program']->isOpen() ? '' : ' · Pendaftaran ditutup' }}</span>
                                    <h2 class="mt-3 text-lg font-bold leading-snug text-teal-900">{{ $entry['program']->name }}</h2>
                                    @if ($entry['program']->tagline)
                                        <p class="mt-1.5 text-sm leading-relaxed text-teal-800/70">{{ $entry['program']->tagline }}</p>
                                    @endif
                                    <span class="mt-auto flex items-center gap-1.5 pt-4 text-xs font-semibold uppercase tracking-wide text-orange-600">
                                        Terbuka untukmu
                                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M4 10h12M11 5l5 5-5 5" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </span>
                                </a>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </section>

</x-layouts.public>
