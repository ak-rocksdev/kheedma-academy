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
                    Mulai dari program yang sedang dibuka. Komunitas affiliator menanti setelah kamu menyelesaikannya.
                </p>
            </div>

            <div class="mt-10 space-y-4">
                @if ($programs->isNotEmpty())
                    <p class="font-display text-xs uppercase tracking-[0.3em] text-teal-700">Program</p>
                @endif
                @foreach ($programs as $entry)
                    <a href="{{ route('program.show', $entry['program']) }}"
                       class="block overflow-hidden rounded-3xl border border-teal-900/10 bg-white/70 shadow-sm backdrop-blur transition hover:border-teal-600/40 hover:shadow-md">
                        <div class="grid sm:grid-cols-[13rem_1fr]">
                            @include('funnel.partials.program-cover', ['program' => $entry['program'], 'class' => 'h-36 sm:h-full'])
                            <div class="flex items-center justify-between gap-4 p-6 sm:p-8">
                                <div>
                                    @if ($entry['chip'])
                                        <span class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-[0.65rem] font-semibold uppercase tracking-wide {{ $entry['chip']['class'] }}">
                                            <svg class="h-3 w-3" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M4 10.5l4 4 8-9" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                            {{ $entry['chip']['label'] }}
                                        </span>
                                    @else
                                        <span class="inline-block rounded-full bg-orange-100 px-3 py-1 text-[0.65rem] font-semibold uppercase tracking-wide text-orange-800">Dibuka</span>
                                    @endif
                                    <h2 class="mt-3 text-xl font-bold text-teal-900">{{ $entry['program']->name }}</h2>
                                    @if ($entry['program']->tagline)
                                        <p class="mt-1.5 text-sm leading-relaxed text-teal-800/70">{{ $entry['program']->tagline }}</p>
                                    @endif
                                    @if ($entry['class_count'] > 0)
                                        <p class="mt-2 inline-flex items-center gap-1.5 text-xs font-semibold text-teal-700">
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3.5" y="5" width="17" height="15.5" rx="2.5"/><path d="M3.5 9.5h17M8 3v4M16 3v4" stroke-linecap="round"/></svg>
                                            {{ $entry['class_count'] }} kelas dalam satu pendaftaran
                                        </p>
                                    @endif
                                </div>
                                <svg class="h-5 w-5 shrink-0 text-teal-700" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M4 10h12M11 5l5 5-5 5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>
                        </div>
                    </a>
                @endforeach

                @if ($canJoinCommunity)
                    <a href="{{ url('/komunitas') }}"
                       class="block rounded-3xl border border-teal-900/10 bg-teal-900 p-6 shadow-sm transition hover:bg-teal-800 hover:shadow-md sm:p-8">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <span class="inline-block rounded-full bg-white/10 px-3 py-1 text-[0.65rem] font-semibold uppercase tracking-wide text-orange-300">Khusus lulusan</span>
                                <h2 class="mt-3 text-xl font-bold text-white">Gabung Komunitas Affiliator</h2>
                                <p class="mt-1.5 text-sm leading-relaxed text-white/70">
                                    Kamu sudah menyelesaikan program. Lanjutkan seriusmu di komunitas: kelas berjenjang, pendampingan, dan jalur karier affiliator.
                                </p>
                            </div>
                            <svg class="h-5 w-5 shrink-0 text-white" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 10h12M11 5l5 5-5 5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </div>
                    </a>
                @else
                    <div class="block rounded-3xl border border-teal-900/10 bg-teal-900/90 p-6 shadow-sm sm:p-8">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1 text-[0.65rem] font-semibold uppercase tracking-wide text-orange-300">
                                    <svg class="h-3 w-3" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="9" width="10" height="7" rx="1.5"/><path d="M7 9V6.5a3 3 0 0 1 6 0V9" stroke-linecap="round"/></svg>
                                    Khusus lulusan
                                </span>
                                <h2 class="mt-3 text-xl font-bold text-white">Komunitas Affiliator</h2>
                                <p class="mt-1.5 text-sm leading-relaxed text-white/70">
                                    Terbuka setelah kamu menyelesaikan semua kelas di satu angkatan program. Di sinilah kelas berjenjang dan jalur karier affiliator berlanjut.
                                </p>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Jenjang kelas komunitas: sengaja DI BAWAH kartu gabung, karena
                 kelas-kelas ini adalah perjalanan setelah bergabung. Level berjajar
                 horizontal (Level 1 paling kiri); lebih dari muat layar tinggal
                 digulir ke kanan. --}}
            @if ($affiliate->isNotEmpty())
                <div class="mt-12">
                    <p class="font-display text-xs uppercase tracking-[0.3em] text-teal-700">Kheedma Affiliate Community</p>
                    <p class="mt-2 text-sm leading-relaxed text-teal-800/70">
                        Jenjang kelas komunitas. Terbuka setelah kamu gabung komunitas.
                    </p>

                    <div class="-mx-6 mt-5 flex snap-x snap-mandatory gap-4 overflow-x-auto px-6 pb-3 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                        @foreach ($affiliate as $entry)
                            @if ($entry['locked'])
                                <button
                                    type="button"
                                    data-lock-trigger
                                    data-lock-message="{{ $entry['message'] }}"
                                    data-lock-reason="{{ $entry['reason'] }}"
                                    class="flex w-64 shrink-0 snap-start flex-col overflow-hidden rounded-3xl border border-teal-900/10 bg-white/40 text-left opacity-80 shadow-sm transition hover:opacity-100"
                                >
                                    <div class="relative">
                                        @include('funnel.partials.program-cover', ['program' => $entry['program'], 'locked' => true, 'class' => 'aspect-video w-full'])
                                        <span class="absolute left-3 top-3 rounded-full bg-white/90 px-3 py-1 text-[0.65rem] font-semibold uppercase tracking-wide text-teal-900">Level {{ $entry['program']->level }}</span>
                                    </div>
                                    <div class="flex flex-1 flex-col p-5">
                                        <h2 class="text-lg font-bold leading-snug text-teal-900/70">{{ $entry['program']->name }}</h2>
                                        @if ($entry['program']->tagline)
                                            <p class="mt-1.5 text-sm leading-relaxed text-teal-800/70">{{ $entry['program']->tagline }}</p>
                                        @endif
                                        <span class="mt-auto flex items-center gap-1.5 pt-4 text-xs font-semibold uppercase tracking-wide text-teal-700">
                                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
                                                <rect x="5" y="9" width="10" height="7" rx="1.5"/><path d="M7 9V6.5a3 3 0 0 1 6 0V9" stroke-linecap="round"/>
                                            </svg>
                                            Terkunci
                                        </span>
                                    </div>
                                </button>
                            @else
                                <a href="{{ route('program.show', $entry['program']) }}"
                                   class="flex w-64 shrink-0 snap-start flex-col overflow-hidden rounded-3xl border border-teal-900/10 bg-white/70 shadow-sm backdrop-blur transition hover:border-teal-600/40 hover:shadow-md">
                                    <div class="relative">
                                        @include('funnel.partials.program-cover', ['program' => $entry['program'], 'class' => 'aspect-video w-full'])
                                        <span class="absolute left-3 top-3 rounded-full bg-white/90 px-3 py-1 text-[0.65rem] font-semibold uppercase tracking-wide text-orange-700">Level {{ $entry['program']->level }}{{ $entry['program']->isOpen() ? '' : ' · Ditutup' }}</span>
                                        @if ($entry['chip'])
                                            <span class="absolute bottom-3 left-3 rounded-full px-3 py-1 text-[0.65rem] font-semibold uppercase tracking-wide {{ $entry['chip']['class'] }}">{{ $entry['chip']['label'] }}</span>
                                        @endif
                                    </div>
                                    <div class="flex flex-1 flex-col p-5">
                                        <h2 class="text-lg font-bold leading-snug text-teal-900">{{ $entry['program']->name }}</h2>
                                        @if ($entry['program']->tagline)
                                            <p class="mt-1.5 text-sm leading-relaxed text-teal-800/70">{{ $entry['program']->tagline }}</p>
                                        @endif
                                        <span class="mt-auto flex items-center gap-1.5 pt-4 text-xs font-semibold uppercase tracking-wide text-orange-600">
                                            Terbuka untukmu
                                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M4 10h12M11 5l5 5-5 5" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </span>
                                    </div>
                                </a>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </section>

</x-layouts.public>
