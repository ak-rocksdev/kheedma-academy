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

            @if ($program->description)
                <div class="mt-10 rounded-3xl border border-teal-900/10 bg-white/70 p-6 text-sm leading-relaxed text-teal-800/90 shadow-sm backdrop-blur sm:p-8">
                    {!! nl2br(e($program->description)) !!}
                </div>
            @endif

            <div class="mt-10 text-center">
                @if ($isOpen)
                    <x-cta :href="route('program.apply', $program)" label="Daftar Sekarang" />
                    <p class="mt-4 text-xs text-teal-800/50">Pendaftaran sedang dibuka. Tempat terbatas.</p>
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
