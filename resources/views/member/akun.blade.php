<x-layouts.public title="Akun Saya"
    description="Area member Kheedma Academy.">

    <section class="relative overflow-hidden">
        <div class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full bg-teal-100 blur-3xl"></div>

        <div class="relative mx-auto max-w-2xl px-6 py-16 sm:py-20">
            @if (session('joined'))
                <div class="mb-8 rounded-xl border border-teal-600/30 bg-teal-50 px-5 py-4 text-sm text-teal-800">
                    Selamat datang di komunitas! Akunmu sudah aktif.
                </div>
            @endif

            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="font-display text-xs uppercase tracking-[0.3em] text-orange-600">Akun Saya</p>
                    <h1 class="mt-3 text-3xl font-bold leading-tight text-teal-900">Halo, {{ $user->name }}.</h1>
                </div>
                <form method="POST" action="{{ route('member.logout') }}">
                    @csrf
                    <button type="submit" class="rounded-full border border-teal-900/15 px-5 py-2 text-sm font-medium text-teal-800 transition hover:border-teal-600/40 hover:text-orange-600">
                        Keluar
                    </button>
                </form>
            </div>

            <div class="mt-8 rounded-3xl border border-teal-900/10 bg-white/70 p-6 shadow-sm backdrop-blur sm:p-8">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-teal-800/60">Profil</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-teal-800/60">Nama</dt>
                        <dd class="font-medium text-teal-900">{{ $user->name }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-teal-800/60">Email</dt>
                        <dd class="font-medium text-teal-900">{{ $user->email }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-teal-800/60">Nomor HP</dt>
                        <dd class="font-medium text-teal-900">{{ $person?->phone ?? '—' }}</dd>
                    </div>
                    @if ($membership)
                        <div class="flex justify-between gap-4">
                            <dt class="text-teal-800/60">Anggota sejak</dt>
                            <dd class="font-medium text-teal-900">{{ $membership->created_at->translatedFormat('d F Y') }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            <div class="mt-6 rounded-3xl border border-dashed border-teal-900/15 bg-white/40 p-6 text-center sm:p-8">
                <p class="text-sm leading-relaxed text-teal-800/60">
                    Materi pilihan dan pengumuman komunitas akan tampil di sini. Nantikan ya!
                </p>
            </div>
        </div>
    </section>

</x-layouts.public>
