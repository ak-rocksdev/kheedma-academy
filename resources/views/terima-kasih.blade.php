<x-layouts.public title="Terima Kasih"
    description="Pendaftaran Kheedma Academy berhasil. Langkah berikutnya: tugas pra-seleksi.">

    <section class="mx-auto flex max-w-2xl flex-col items-center px-6 py-24 text-center sm:py-28">
        <div class="flex h-16 w-16 items-center justify-center rounded-full bg-teal-700/10">
            <svg class="h-8 w-8 text-teal-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path d="M5 13l4 4L19 7" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>

        <p class="mt-8 font-display text-xs uppercase tracking-[0.3em] text-orange-600">Pendaftaran diterima</p>
        <h1 class="mt-3 text-3xl font-bold leading-tight text-teal-900 sm:text-4xl">
            @if (session('applicant_name'))
                Terima kasih, {{ session('applicant_name') }}.
            @else
                Terima kasih sudah mendaftar.
            @endif
        </h1>

        <p class="mt-5 max-w-lg text-base leading-relaxed text-teal-800/80">
            Pendaftaranmu sudah kami terima. Langkah berikutnya adalah
            <strong class="font-semibold text-teal-900">tugas pra-seleksi</strong>. Caranya akan kami
            kirimkan melalui WhatsApp/email ke kontak yang kamu isi. Tugas ini menyaring kesungguhan,
            bukan uang.
        </p>

        @if (session('has_account'))
            <div class="mt-8 flex flex-wrap items-center justify-center gap-4">
                <x-cta :href="route('member.area')" label="Kelola Status Pendaftaran" />
                <a href="{{ url('/') }}"
                   class="inline-flex items-center rounded-full border border-teal-700/20 px-7 py-3.5 text-sm font-semibold text-teal-700 transition hover:bg-white/60">
                    ← Kembali ke beranda
                </a>
            </div>
        @else
            <div class="mt-8 flex flex-wrap items-center justify-center gap-4">
                <a href="{{ url('/') }}"
                   class="inline-flex items-center rounded-full border border-teal-700/20 px-7 py-3.5 text-sm font-semibold text-teal-700 transition hover:bg-white/60">
                    ← Kembali ke beranda
                </a>
                <a href="{{ url('/#program') }}"
                   class="inline-flex items-center rounded-full px-5 py-3.5 text-sm font-semibold text-teal-700 transition hover:text-orange-600">
                    Lihat alur program
                </a>
            </div>
        @endif
    </section>

</x-layouts.public>
