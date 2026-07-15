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
                            <dd class="font-medium text-teal-900">{{ $membership->created_at->locale('id')->translatedFormat('d F Y') }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            @if ($applications->isNotEmpty())
                <div class="mt-6 rounded-3xl border border-teal-900/10 bg-white/70 p-6 shadow-sm backdrop-blur sm:p-8">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-teal-800/60">Status Pendaftaran</h2>
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
                </div>
            @endif

            @if ($openClasses->isNotEmpty())
                <div class="mt-10">
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
        </div>
    </section>

</x-layouts.public>
