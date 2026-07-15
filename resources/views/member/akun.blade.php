<x-layouts.public title="Akun Saya"
    description="Area member Kheedma Academy.">

    @php($tabs = [
        'profil' => 'Profil',
        'pendaftaran' => 'Pendaftaran',
        'kelas' => 'Kelas & Program',
    ])

    <section class="relative overflow-hidden">
        <div class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full bg-teal-100 blur-3xl"></div>

        <div class="relative mx-auto max-w-2xl px-6 py-16 pb-32 sm:py-20 sm:pb-20">
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

            <!-- Tab bar (tablet/desktop); di mobile navigasinya pindah ke bawah layar -->
            <nav class="mt-8 hidden gap-2 sm:flex" aria-label="Bagian akun">
                @foreach ($tabs as $key => $label)
                    <a href="{{ route('member.area', $key === 'profil' ? [] : ['bagian' => $key]) }}"
                       @class([
                           'rounded-full px-5 py-2.5 text-sm font-semibold transition',
                           'bg-teal-800 text-white shadow-sm' => $activeTab === $key,
                           'border border-teal-900/15 text-teal-800 hover:border-teal-600/40 hover:text-orange-600' => $activeTab !== $key,
                       ])>
                        {{ $label }}
                    </a>
                @endforeach
            </nav>

            @if ($activeTab === 'pendaftaran')
                <div class="mt-8 rounded-3xl border border-teal-900/10 bg-white/70 p-6 shadow-sm backdrop-blur sm:p-8">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-teal-800/60">Status Pendaftaran</h2>
                    @if ($applications->isEmpty())
                        <p class="mt-4 text-sm leading-relaxed text-teal-800/70">
                            Kamu belum punya pendaftaran. Lihat kelas yang sedang dibuka dan mulai perjalananmu.
                        </p>
                        <a href="{{ route('member.area', ['bagian' => 'kelas']) }}"
                           class="mt-4 inline-flex items-center rounded-full bg-orange-500 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-orange-600">
                            Lihat Kelas Dibuka
                        </a>
                    @else
                        <ul class="mt-4 space-y-4 text-sm">
                            @foreach ($applications as $application)
                                <li>
                                    <div class="flex items-center justify-between gap-4">
                                        <div>
                                            <p class="font-medium text-teal-900">
                                                {{ $application['program'] }}@if ($application['cohort'])<span class="text-teal-800/60"> · {{ $application['cohort'] }}</span>@endif
                                            </p>
                                            <p class="text-xs text-teal-800/60">Daftar {{ $application['created_at']->locale('id')->translatedFormat('j F Y') }}</p>
                                        </div>
                                        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $application['statusClass'] }}">{{ $application['statusLabel'] }}</span>
                                    </div>
                                    @if ($application['status'] === 'rejected')
                                        <div class="mt-2 rounded-xl bg-sand-100/70 px-4 py-3 text-xs leading-relaxed text-teal-800/80">
                                            @if ($application['reviewNote'])
                                                <p><span class="font-semibold">Catatan dari tim:</span> {{ $application['reviewNote'] }}</p>
                                            @endif
                                            <p @class(['mt-1' => $application['reviewNote']])>Jangan berkecil hati, kamu boleh mendaftar lagi kapan saja.</p>
                                        </div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @elseif ($activeTab === 'kelas')
                @if ($openClasses->isNotEmpty())
                    <div class="mt-8">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-teal-800/60">Kelas Dibuka</h2>
                        <div class="mt-4 space-y-3">
                            @foreach ($openClasses as $entry)
                                <div class="flex items-center justify-between gap-4 rounded-2xl border border-teal-900/10 bg-white px-5 py-4">
                                    <div>
                                        <p class="font-semibold text-teal-900">{{ $entry['program']->name }}</p>
                                        <p class="text-xs text-teal-800/60">
                                            @if ($entry['openCohort']?->start_date)
                                                Kelas dimulai {{ $entry['openCohort']->start_date->locale('id')->translatedFormat('j F Y') }}
                                            @else
                                                Pendaftaran sedang dibuka
                                            @endif
                                        </p>
                                    </div>
                                    @if ($entry['chip'])
                                        <span class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold {{ $entry['state'] === 'pending' ? 'bg-orange-100 text-orange-700' : 'bg-teal-100 text-teal-700' }}">
                                            {{ $entry['chip'] }}
                                        </span>
                                    @else
                                        <a href="{{ route('program.apply', $entry['program']) }}"
                                           class="shrink-0 rounded-full bg-orange-500 px-5 py-2 text-sm font-semibold text-white transition hover:bg-orange-600">
                                            Daftar
                                        </a>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @else
                    <div class="mt-8 rounded-3xl border border-teal-900/10 bg-white/70 p-6 text-sm leading-relaxed text-teal-800/70 shadow-sm backdrop-blur sm:p-8">
                        Belum ada kelas yang membuka pendaftaran saat ini. Cek lagi secara berkala ya.
                    </div>
                @endif

                @if ($affiliate->isNotEmpty())
                    <div class="mt-10">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-teal-800/60">Program untuk Anda</h2>
                        <div class="mt-4 space-y-3">
                            @foreach ($affiliate as $entry)
                                @if ($entry['locked'])
                                    <button
                                        type="button"
                                        data-lock-trigger
                                        data-lock-message="{{ $entry['message'] }}"
                                        data-lock-reason="{{ $entry['reason'] }}"
                                        class="flex w-full items-center justify-between gap-4 rounded-2xl border border-teal-900/10 bg-white/50 px-5 py-4 text-left opacity-75 transition hover:opacity-100"
                                    >
                                        <div>
                                            <p class="font-semibold text-teal-900/70">{{ $entry['program']->name }}</p>
                                            <p class="text-xs text-teal-800/50">Level {{ $entry['program']->level }} · Terkunci</p>
                                        </div>
                                        <svg class="h-4 w-4 shrink-0 text-teal-700/50" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="9" width="10" height="7" rx="1.5"/><path d="M7 9V6.5a3 3 0 0 1 6 0V9" stroke-linecap="round"/></svg>
                                    </button>
                                @else
                                    <a href="{{ route('program.show', $entry['program']) }}"
                                       class="flex items-center justify-between gap-4 rounded-2xl border border-teal-900/10 bg-white px-5 py-4 transition hover:border-teal-600/40">
                                        <div>
                                            <p class="font-semibold text-teal-900">{{ $entry['program']->name }}</p>
                                            <p class="text-xs text-teal-800/60">Level {{ $entry['program']->level }} · Terbuka untukmu</p>
                                        </div>
                                        <svg class="h-4 w-4 shrink-0 text-teal-700" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 10h12M11 5l5 5-5 5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="mt-6 rounded-3xl border border-dashed border-teal-900/15 bg-white/40 p-6 text-center sm:p-8">
                    <p class="text-sm leading-relaxed text-teal-800/60">
                        Materi pilihan dan pengumuman komunitas akan tampil di sini. Nantikan ya!
                    </p>
                </div>
            @else
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
                    </dl>
                </div>

                <div class="mt-6 rounded-3xl border border-teal-900/10 bg-white/70 p-6 shadow-sm backdrop-blur sm:p-8">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-teal-800/60">Komunitas</h2>
                    @if ($membership)
                        <p class="mt-4 text-sm leading-relaxed text-teal-800/80">
                            Kamu anggota komunitas sejak
                            <span class="font-semibold text-teal-900">{{ $membership->created_at->locale('id')->translatedFormat('d F Y') }}</span>.
                        </p>
                    @else
                        <p class="mt-4 text-sm leading-relaxed text-teal-800/70">
                            Kamu belum bergabung dengan komunitas. Gabung untuk mendapatkan materi dan kabar terbaru lebih dulu.
                        </p>
                        <a href="{{ route('komunitas') }}"
                           class="mt-4 inline-flex items-center rounded-full bg-orange-500 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-orange-600">
                            Gabung Komunitas
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </section>

    <!-- Navigasi bawah (mobile): selalu terlihat, ramah jangkauan jempol -->
    <nav class="fixed inset-x-0 bottom-0 z-40 border-t border-teal-900/10 bg-white/95 backdrop-blur sm:hidden"
         style="padding-bottom: env(safe-area-inset-bottom)"
         aria-label="Bagian akun">
        <div class="mx-auto flex max-w-2xl">
            <a href="{{ url('/') }}"
               class="flex flex-1 flex-col items-center gap-1 py-2.5 text-[0.65rem] font-semibold text-teal-800/60">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m4 10.5 8-6.75 8 6.75"/><path d="M6 8.75V19a1 1 0 0 0 1 1h3.25v-4.5a1.75 1.75 0 0 1 3.5 0V20H17a1 1 0 0 0 1-1V8.75"/></svg>
                Beranda
            </a>
            <a href="{{ route('member.area') }}"
               @class(['flex flex-1 flex-col items-center gap-1 py-2.5 text-[0.65rem] font-semibold', 'text-orange-600' => $activeTab === 'profil', 'text-teal-800/60' => $activeTab !== 'profil'])>
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="3.25"/><path d="M5.5 19.5a6.5 6.5 0 0 1 13 0"/></svg>
                Profil
            </a>
            <a href="{{ route('member.area', ['bagian' => 'pendaftaran']) }}"
               @class(['flex flex-1 flex-col items-center gap-1 py-2.5 text-[0.65rem] font-semibold', 'text-orange-600' => $activeTab === 'pendaftaran', 'text-teal-800/60' => $activeTab !== 'pendaftaran'])>
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5h6M9 5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2"/><path d="m9.5 13 1.75 1.75L14.75 11"/></svg>
                Pendaftaran
            </a>
            <a href="{{ route('member.area', ['bagian' => 'kelas']) }}"
               @class(['flex flex-1 flex-col items-center gap-1 py-2.5 text-[0.65rem] font-semibold', 'text-orange-600' => $activeTab === 'kelas', 'text-teal-800/60' => $activeTab !== 'kelas'])>
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 6.25C10 4.75 7.5 4.5 5 4.5v13c2.5 0 5 .25 7 1.75 2-1.5 4.5-1.75 7-1.75v-13c-2.5 0-5 .25-7 1.75Z"/><path d="M12 6.25v13"/></svg>
                Kelas
            </a>
        </div>
    </nav>

</x-layouts.public>
