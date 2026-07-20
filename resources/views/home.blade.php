<x-layouts.public>

    {{-- ───────────────────────── Hero ───────────────────────── --}}
    <section class="relative overflow-hidden">
        {{-- Desktop: full-bleed brand pattern, right half of the screen, full hero height --}}
        <div aria-hidden="true" class="pointer-events-none absolute inset-y-0 right-0 hidden w-1/2 md:block">
            <div class="supergraphic absolute inset-0 opacity-[0.13]"></div>
            {{-- dissolve the inner edge so the pattern blends into the text side --}}
            <div class="absolute inset-0 bg-gradient-to-r from-sand-50 via-sand-50/30 to-transparent"></div>
        </div>

        {{-- ambient brand wash --}}
        <div class="pointer-events-none absolute -right-40 -top-40 h-[32rem] w-[32rem] rounded-full bg-teal-100 blur-3xl"></div>
        <div class="pointer-events-none absolute -left-24 top-24 h-72 w-72 rounded-full bg-orange-200/50 blur-3xl"></div>

        <div class="relative mx-auto grid max-w-6xl items-stretch gap-4 px-6 pb-16 pt-6 md:grid-cols-12 md:gap-12 md:pb-20 md:pt-24">
            <div class="md:col-span-7">
                <p class="inline-flex items-center gap-2 rounded-full border border-teal-900/10 bg-white/60 px-4 py-1.5 text-xs font-semibold uppercase tracking-[0.2em] text-teal-700">
                    <span class="h-1.5 w-1.5 rounded-full bg-orange-500"></span>
                    Muslim Growth Partner · Affiliate Academy
                </p>

                <h1 class="mt-5 text-3xl font-bold leading-[1.1] text-teal-900 sm:text-5xl md:text-6xl">
                    Kami membimbing<br>
                    kamu untuk <span class="text-orange-600">tumbuh</span>.
                </h1>

                <p class="mt-4 max-w-xl text-base leading-relaxed text-teal-800/80 sm:mt-6 sm:text-lg">
                    Mulai dari nol menjadi affiliate marketer yang amanah dan profesional.
                    Lewati masa observasi terbimbing 1-2 bulan. Yang sungguh-sungguh, kami
                    antar untuk berkembang dengan cara yang <strong class="font-semibold text-teal-900">halal, terukur, dan berkah</strong>.
                </p>

                <div class="mt-6 flex flex-wrap items-center gap-3 sm:mt-9 sm:gap-4">
                    <x-cta :href="url('/daftar')" label="Daftar Sekarang" />
                    <a href="{{ url('/#program') }}"
                       class="inline-flex items-center rounded-full border border-teal-700/20 px-7 py-3.5 text-sm font-semibold text-teal-700 transition hover:border-teal-700/40 hover:bg-white/50">
                        Lihat program
                    </a>
                </div>

                <p class="mt-5 text-sm text-teal-800/70 sm:mt-6">
                    <span class="font-semibold text-teal-700">Kelas perdana gratis</span>, sebagai eksperimen terukur. Tempat terbatas.
                </p>
            </div>

            {{-- Logo lockup, on top (over its own pattern) on mobile, centred over the
                 right-half brand panel on desktop. --}}
            <div class="relative order-first md:order-none md:col-span-5 md:flex md:items-center md:justify-center">
                {{-- Mobile-only pattern, bled to the screen edges and up under the header.
                     Faded to transparent with a mask so it dissolves seamlessly into the
                     page below (no solid-colour band at the edge). --}}
                <div aria-hidden="true" class="pointer-events-none absolute -inset-x-6 -top-16 -bottom-10 overflow-hidden md:hidden">
                    <div class="supergraphic absolute inset-0 opacity-[0.13] [-webkit-mask-image:linear-gradient(to_bottom,black_0%,black_50%,transparent_100%)] [mask-image:linear-gradient(to_bottom,black_0%,black_50%,transparent_100%)]"></div>
                </div>
                <div class="relative py-2 text-center md:py-0">
                    <x-logo variant="stacked" class="mx-auto h-28 sm:h-44 md:h-72 lg:h-80" />
                    <p class="mt-6 hidden font-display text-xs uppercase tracking-[0.3em] text-teal-700/70 sm:mt-8 sm:block">
                        Serving with Purpose,<br>Growing with Barakah
                    </p>
                </div>
            </div>
        </div>
    </section>

    {{-- ───────────────────────── Tentang ───────────────────────── --}}
    <section id="tentang" class="mx-auto max-w-6xl scroll-mt-24 px-6 py-16">
        <div class="grid gap-10 md:grid-cols-12 md:items-end">
            <div class="md:col-span-8">
                <p class="font-display text-xs uppercase tracking-[0.3em] text-orange-600">Tentang</p>
                <h2 class="mt-3 text-3xl font-bold leading-tight text-teal-900 md:text-4xl">
                    Bukan kursus “cepat kaya”.<br>Sebuah proses yang dibimbing.
                </h2>
            </div>
            <p class="text-base leading-relaxed text-teal-800/80 md:col-span-4">
                Kheedma Academy adalah bagian dari Kheedma, agency berbasis nilai Islam. Kami
                memindahkan identitas yang sama: khidmat, amanah, itqan, dan barakah, lalu
                mengarahkannya untuk membimbing individu bertumbuh.
            </p>
        </div>
    </section>

    {{-- ───────────────────────── Program ───────────────────────── --}}
    <section id="program" class="scroll-mt-24 bg-white/60 py-20">
        <div class="mx-auto max-w-6xl px-6">
            <p class="font-display text-xs uppercase tracking-[0.3em] text-orange-600">Bagaimana programnya</p>
            <h2 class="mt-3 max-w-2xl text-3xl font-bold leading-tight text-teal-900 md:text-4xl">
                Empat langkah, satu arah: yang berkomitmen, berkembang.
            </h2>

            <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    ['01', 'Daftar &amp; tugas pra-seleksi', 'Isi formulir singkat dan selesaikan satu tugas kecil. Ini menyaring kesungguhan, bukan uang.'],
                    ['02', 'Masa observasi terbimbing', 'Selama 1–2 bulan kamu mendapat materi dan review berkala. Dipantau, bukan dilepas.'],
                    ['03', 'Bimbingan &amp; praktik', 'Mentoring berjalan langsung. Fokus pada hasil affiliate yang nyata dan dapat ditelusuri.'],
                    ['04', 'Lanjut bagi yang serius', 'Yang sungguh-sungguh diantar melangkah lebih jauh. Program yang progresif &amp; selektif.'],
                ] as [$num, $head, $body])
                    <div class="group relative rounded-2xl border border-teal-900/10 bg-sand-50 p-6 transition hover:border-orange-500/40 hover:shadow-sm">
                        <span class="font-display text-2xl font-bold text-teal-200 transition group-hover:text-orange-500">{{ $num }}</span>
                        <h3 class="mt-3 text-base font-bold leading-snug text-teal-900">{!! $head !!}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-teal-800/70">{!! $body !!}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ───────────────────────── Nilai ───────────────────────── --}}
    <section id="nilai" class="mx-auto max-w-6xl scroll-mt-24 px-6 py-20">
        <p class="font-display text-xs uppercase tracking-[0.3em] text-orange-600">Nilai yang kami pegang</p>
        <h2 class="mt-3 max-w-2xl text-3xl font-bold leading-tight text-teal-900 md:text-4xl">
            Empat nilai yang menjaga arah.
        </h2>

        <div class="mt-12 grid gap-px overflow-hidden rounded-3xl border border-teal-900/10 bg-teal-900/10 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['Khidmat', 'Melayani dengan niat ibadah, bukan sekadar transaksi.'],
                ['Amanah', 'Menjaga kepercayaan yang dititipkan, di setiap langkah.'],
                ['Itqan', 'Bekerja dengan kualitas terbaik, terarah dan terukur.'],
                ['Barakah', 'Mengedepankan yang halal &amp; thayyib demi pertumbuhan yang berkelanjutan.'],
            ] as [$name, $desc])
                <div class="bg-sand-50 p-7">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-teal-700/10 font-display text-sm font-bold text-teal-700">
                        {{ \Illuminate\Support\Str::substr($name, 0, 1) }}
                    </div>
                    <h3 class="mt-4 text-lg font-bold text-teal-900">{{ $name }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-teal-800/70">{!! $desc !!}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- ───────────────────────── CTA band ───────────────────────── --}}
    <section class="mx-auto max-w-6xl px-6">
        <div class="relative overflow-hidden rounded-3xl bg-teal-700 px-8 py-14 text-center md:px-16 md:py-20">
            <div class="supergraphic pointer-events-none absolute inset-x-0 bottom-0 h-32 opacity-15"></div>
            <div class="relative">
                <h2 class="mx-auto max-w-2xl text-3xl font-bold leading-tight text-sand-50 md:text-4xl">
                    Siap memulai dengan niat yang benar?
                </h2>
                <p class="mx-auto mt-4 max-w-xl text-base leading-relaxed text-sand-100/80">
                    Kelas perdana dibuka gratis sebagai eksperimen terukur. Tunjukkan kesungguhanmu
                    lewat tugas pra-seleksi, dan mulai perjalananmu.
                </p>
                <x-cta :href="url('/daftar')" label="Daftar Sekarang" class="mt-8" />
            </div>
        </div>
    </section>

</x-layouts.public>
