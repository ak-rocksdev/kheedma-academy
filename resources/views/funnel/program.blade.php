<x-layouts.public :title="$program->name"
    :description="$program->tagline ?: 'Program Kheedma Academy: ' . $program->name">

    <section class="relative overflow-hidden">
        <div class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full bg-teal-100 blur-3xl"></div>

        <div class="relative mx-auto max-w-2xl px-6 py-16 sm:py-20">
            <div class="text-center">
                <x-logo variant="stacked" class="mx-auto h-20" />
                <p class="mt-8 font-display text-xs uppercase tracking-[0.3em] text-orange-600">Program</p>
                <h1 class="mt-3 text-3xl font-bold leading-tight text-teal-900 sm:text-4xl">{{ $program->name }}</h1>
                @if ($program->tagline)
                    <p class="mx-auto mt-4 max-w-lg text-base leading-relaxed text-teal-800/80">{{ $program->tagline }}</p>
                @endif
            </div>

            @if ($sections->isNotEmpty())
                <div class="mt-10 space-y-4">
                    @include('funnel.partials.content-sections', ['sections' => $sections])
                </div>
            @elseif ($program->description)
                <div class="mt-10 rounded-3xl border border-teal-900/10 bg-white/70 p-6 text-sm leading-relaxed text-teal-800/90 shadow-sm backdrop-blur sm:p-8">
                    {!! nl2br(e($program->description)) !!}
                </div>
            @endif

            @if (count($openClasses))
                <div class="mt-10 rounded-3xl border border-teal-900/10 bg-white/70 p-6 shadow-sm backdrop-blur sm:p-8">
                    <p class="font-display text-xs uppercase tracking-[0.3em] text-orange-600">Jadwal Kelas</p>
                    <h2 class="mt-2 text-lg font-bold text-teal-900">Apa saja yang akan kamu ikuti?</h2>
                    <ul class="mt-4 divide-y divide-teal-900/5">
                        @foreach ($openClasses as $i => $kelas)
                            <li class="flex flex-wrap items-center gap-x-3 gap-y-1 py-3">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-sand-100 text-xs font-bold text-teal-800/70">{{ $i + 1 }}</span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-semibold text-teal-900">{{ $kelas['title'] }}</span>
                                    @if ($kelas['schedule'])
                                        <span class="block text-xs text-teal-800/70">{{ $kelas['schedule'] }}</span>
                                    @endif
                                </span>
                                <span @class([
                                    'shrink-0 rounded-full px-2.5 py-1 text-[0.65rem] font-semibold uppercase tracking-wide',
                                    'bg-teal-100 text-teal-700' => ! $kelas['is_online'],
                                    'bg-sand-100 text-teal-800/70' => $kelas['is_online'],
                                ])>{{ $kelas['is_online'] ? 'Online' : 'Tatap muka' }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mt-10 text-center">
                @if (session('application_notice'))
                    <div class="mx-auto mb-6 max-w-md rounded-2xl border border-orange-200 bg-orange-50 px-4 py-3 text-sm text-orange-800">
                        {{ session('application_notice') }}
                    </div>
                @endif

                @if ($statePill)
                    <span class="inline-flex items-center gap-2 rounded-full px-6 py-3 text-sm font-semibold {{ $statePill['class'] }}">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M4 10.5l4 4 8-9" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        {{ $statePill['label'] }}
                    </span>
                    <div class="mt-4">
                        <a href="{{ route('member.area') }}" class="text-sm font-medium text-teal-700 underline-offset-4 hover:underline">Lihat Status di Akunmu</a>
                    </div>
                @elseif ($isOpen && ! $locked)
                    <x-cta :href="route('program.apply', $program)" label="Daftar Sekarang" />
                    @if ($applicationState === 'rejected')
                        <p class="mt-4 text-xs text-orange-700">Pendaftaranmu sebelumnya belum lolos. Kamu boleh mencoba lagi.</p>
                    @endif
                    <p class="mt-4 text-xs text-teal-800/70">
                        Pendaftaran sedang dibuka. Tempat terbatas.
                        @if ($openCohort?->startLabel())
                            Kelas dimulai {{ $openCohort->startLabel() }}.
                        @endif
                    </p>
                @elseif ($locked)
                    <button
                        type="button"
                        data-lock-trigger
                        data-lock-message="{{ $lockedMessage }}"
                        data-lock-reason="{{ $lockReason }}"
                        class="inline-flex items-center gap-2 rounded-full bg-teal-900/10 px-6 py-3 text-sm font-semibold text-teal-900/80"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="9" width="10" height="7" rx="1.5"/><path d="M7 9V6.5a3 3 0 0 1 6 0V9" stroke-linecap="round"/></svg>
                        Terkunci
                    </button>
                @else
                    <div class="rounded-3xl border border-teal-900/10 bg-white/70 p-6 shadow-sm backdrop-blur sm:p-8">
                        <h2 class="text-lg font-bold text-teal-900">Pendaftaran ditutup</h2>
                        <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-teal-800/70">
                            Pendaftaran program ini sedang tidak dibuka. Gabung komunitas dulu supaya
                            kamu jadi yang pertama tahu saat kelas baru dibuka.
                        </p>
                        <div class="mt-5">
                            <x-cta :href="url('/komunitas')" label="Gabung Komunitas" />
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </section>

</x-layouts.public>
