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
        </div>
    </section>

</x-layouts.public>
