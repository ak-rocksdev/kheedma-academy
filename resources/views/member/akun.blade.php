<x-layouts.public title="Akun Saya"
    description="Area member Kheedma Academy."
    :bottom-nav="false">

    @php($tabs = [
        'profil' => 'Profil',
        'pendaftaran' => 'Pendaftaran',
        'kelas' => 'Kelas & Program',
        'tugas' => 'Tugas',
    ])

    <section class="relative overflow-hidden">
        <div class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full bg-teal-100 blur-3xl"></div>

        {{-- max-w-4xl (was 2xl): the page outgrew a narrow column once the
             class timeline and the tugas ledger arrived; 4xl fits the data
             without stretching reading lines to the site shell's width. --}}
        <div class="relative mx-auto max-w-4xl px-6 py-16 pb-32 md:py-20 md:pb-20">
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
            <nav class="mt-8 hidden gap-2 md:flex" aria-label="Bagian akun">
                @foreach ($tabs as $key => $label)
                    <a href="{{ route('member.area', ['bagian' => $key]) }}"
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
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-teal-800/70">Status Pendaftaran</h2>
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
                                                {{ $application['program'] }}@if ($application['cohort'])<span class="text-teal-800/70"> · {{ $application['cohort'] }}</span>@endif
                                            </p>
                                            <p class="text-xs text-teal-800/70">Daftar {{ $application['created_at']->locale('id')->translatedFormat('j F Y') }}</p>
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
                @if ($kelasNotice)
                    {{-- Waiting states must never read as an empty, broken page. --}}
                    <div class="mt-8 rounded-3xl border border-teal-900/10 bg-white/70 p-6 shadow-sm backdrop-blur sm:p-8">
                        @if ($kelasNotice === 'accepted')
                            <p class="font-semibold text-teal-900">Pendaftaranmu diterima! 🎉</p>
                            <p class="mt-1 text-sm leading-relaxed text-teal-800/70">
                                Tim sedang menyiapkan penempatan kelasmu. Jadwal dan lokasinya akan tampil di sini — pantau ya.
                            </p>
                        @else
                            <p class="font-semibold text-teal-900">Pendaftaranmu sedang ditinjau.</p>
                            <p class="mt-1 text-sm leading-relaxed text-teal-800/70">
                                Kami kabari begitu ada keputusan. Cek statusnya kapan saja di tab Pendaftaran.
                            </p>
                        @endif
                    </div>
                @endif

                @if ($enrolledClasses->isNotEmpty())
                    <div class="mt-8">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-teal-800/70">Kelas Saya</h2>
                        <div class="mt-4 space-y-4">
                            @foreach ($enrolledClasses as $enrollment)
                                @php($cohort = $enrollment->cohort)
                                <div class="rounded-3xl border border-teal-900/10 bg-white/70 p-6 shadow-sm backdrop-blur sm:p-8">
                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                        <p class="font-semibold text-teal-900">
                                            {{ $cohort->program?->name }}<span class="text-teal-800/70"> · {{ $cohort->name }}</span>
                                        </p>
                                        @if ($cohort->status === 'ended')
                                            <span class="shrink-0 rounded-full bg-sand-100 px-3 py-1 text-xs font-semibold text-teal-800/70">Selesai</span>
                                        @endif
                                    </div>
                                    @if ($cohort->mentor)
                                        <p class="mt-1 inline-flex items-center gap-1.5 text-sm text-teal-800/80">
                                            <svg class="h-4 w-4 shrink-0 text-teal-700/70" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="3.5"/><path d="M5 19.5c1.2-3 3.8-4.5 7-4.5s5.8 1.5 7 4.5" stroke-linecap="round"/></svg>
                                            Mentormu: <span class="font-semibold text-teal-900">{{ $cohort->mentor->name }}</span>
                                        </p>
                                    @endif
                                    @if ($cohort->status === 'ended')
                                        {{-- Closure: the card turns into a personal record, never a stale invite. --}}
                                        <div class="mt-4 space-y-2 text-sm text-teal-800/80">
                                            @if ($enrollment->attendances_count > 0)
                                                <p class="inline-flex items-center gap-1.5 font-semibold text-teal-700">
                                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M4 10.5l4 4 8-9" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                    Kamu hadir di kelas ini
                                                </p>
                                            @else
                                                <p class="text-teal-800/70">Kelas telah selesai.</p>
                                            @endif
                                        </div>
                                    @else
                                        @if ($cohort->sessions->isEmpty())
                                            <p class="mt-3 text-sm text-teal-800/70">Jadwal kelas akan diumumkan.</p>
                                        @endif

                                        <div class="mt-4 space-y-3">
                                            @foreach ($cohort->sessions as $session)
                                                <div class="rounded-2xl border border-teal-900/10 bg-white p-4">
                                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                                        <p class="font-semibold text-teal-900">{{ $session->title }}</p>
                                                        <span @class([
                                                            'shrink-0 rounded-full px-3 py-1 text-xs font-semibold',
                                                            'bg-teal-100 text-teal-700' => ! $session->isOnline(),
                                                            'bg-sand-100 text-teal-800/70' => $session->isOnline(),
                                                        ])>{{ $session->isOnline() ? 'Online' : 'Tatap muka' }}</span>
                                                    </div>

                                                    @if ($session->scheduledLabel())
                                                        <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-teal-800/80">
                                                            <span>{{ $session->scheduledLabel() }}</span>
                                                            @if ($session->countdownLabel())
                                                                <span class="rounded-full bg-orange-500/15 px-2.5 py-0.5 text-xs font-bold text-orange-700">{{ $session->countdownLabel() }}</span>
                                                            @endif
                                                            @if ($session->googleCalendarUrl())
                                                                <a href="{{ $session->googleCalendarUrl() }}" target="_blank" rel="noopener"
                                                                   class="text-xs font-semibold text-teal-700 underline-offset-4 transition hover:text-orange-600 hover:underline">
                                                                    + Tambahkan ke Google Calendar
                                                                </a>
                                                            @endif
                                                        </div>
                                                    @endif

                                                    @if ($session->isOnline())
                                                        <p class="mt-2 text-sm font-medium text-teal-900">Kelas online</p>
                                                        @if ($session->meeting_url)
                                                            <a href="{{ $session->meeting_url }}" target="_blank" rel="noopener"
                                                               class="mt-3 inline-flex items-center gap-2 rounded-full bg-teal-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-teal-900">
                                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2.5" y="6" width="13" height="12" rx="2.5"/><path d="m15.5 10 5-3v10l-5-3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                                Gabung meeting
                                                            </a>
                                                        @else
                                                            <p class="mt-2 text-sm text-teal-800/70">Link meeting akan dibagikan sebelum kelas dimulai.</p>
                                                        @endif
                                                    @else
                                                        @if ($session->mapsEmbedUrl())
                                                            {{-- The collapsible IS the location object: venue name up top,
                                                                 the live map one tap below. --}}
                                                            {{-- H-2: the map opens itself — the venue must not hide behind a
                                                                 tap when the (one-shot) class day is this close. --}}
                                                            {{-- PO decision 2026-07-20: the map always starts collapsed —
                                                                 the venue name/address line already answers "di mana",
                                                                 the map is one tap away when needed. --}}
                                                            <details class="kh-collapsible group mt-3 overflow-hidden rounded-2xl border border-teal-900/10 bg-white">
                                                                <summary class="flex cursor-pointer list-none items-center gap-3 px-4 py-3 transition hover:bg-sand-50 [&::-webkit-details-marker]:hidden">
                                                                    {{-- Mini "map tile": pin on soft brand ground, the visual cue on the left. --}}
                                                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-teal-100 via-sand-50 to-orange-200/70 ring-1 ring-inset ring-teal-900/10">
                                                                        <svg class="h-5 w-5 text-orange-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s-6-5.1-6-9.9a6 6 0 1 1 12 0C18 15.9 12 21 12 21Z"/><circle cx="12" cy="11" r="2.3"/></svg>
                                                                    </span>
                                                                    <span class="min-w-0 flex-1">
                                                                        <span class="block text-sm font-semibold text-teal-900">{{ $session->location_name ?: 'Lokasi kelas' }}</span>
                                                                        <span class="block text-xs text-teal-800/70">{{ $session->location_address ?: 'Lihat lokasi di peta' }}</span>
                                                                    </span>
                                                                    {{-- Labeled affordance: the verb tells what you get, the
                                                                         chevron tells how it behaves; the label flips when open. --}}
                                                                    <span class="flex shrink-0 items-center gap-1.5 rounded-full border border-teal-900/15 px-3 py-1.5 text-xs font-semibold text-teal-700 transition group-hover:border-teal-600/40">
                                                                        <span class="group-open:hidden">Lihat peta</span>
                                                                        <span class="hidden group-open:inline">Tutup peta</span>
                                                                        <svg class="h-3.5 w-3.5 transition-transform group-open:rotate-180" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 8 4 4 4-4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                                    </span>
                                                                </summary>
                                                                <div class="border-t border-teal-900/10">
                                                                    <iframe
                                                                        src="{{ $session->mapsEmbedUrl() }}"
                                                                        title="Peta lokasi kelas"
                                                                        class="h-56 w-full"
                                                                        loading="lazy"
                                                                        referrerpolicy="no-referrer-when-downgrade"
                                                                        allowfullscreen
                                                                    ></iframe>
                                                                    <div class="flex flex-wrap items-center gap-3 px-4 py-3">
                                                                        <a href="{{ $session->mapsDirectionsUrl() }}" target="_blank" rel="noopener"
                                                                           class="inline-flex items-center gap-2 rounded-full bg-orange-500 px-4 py-2 text-xs font-semibold text-white transition hover:bg-orange-600">
                                                                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M21.7 11.3 12.7 2.3a1 1 0 0 0-1.4 0l-9 9a1 1 0 0 0 0 1.4l9 9a1 1 0 0 0 1.4 0l9-9a1 1 0 0 0 0-1.4ZM13 14.5V12h-3v3H8v-4a1 1 0 0 1 1-1h4V7.5l3.5 3.5-3.5 3.5Z"/></svg>
                                                                            Petunjuk arah
                                                                        </a>
                                                                        <a href="{{ $session->mapsUrl() }}" target="_blank" rel="noopener"
                                                                           class="text-xs font-semibold text-teal-700 underline-offset-4 transition hover:text-orange-600 hover:underline">
                                                                            Buka di Google Maps
                                                                        </a>
                                                                    </div>
                                                                </div>
                                                            </details>
                                                        @elseif ($session->location_name || $session->location_address)
                                                            {{-- Coordinates absent (legacy/manual entry): plain text location. --}}
                                                            @if ($session->location_name)
                                                                <p class="mt-2 font-semibold text-teal-900">{{ $session->location_name }}</p>
                                                            @endif
                                                            @if ($session->location_address)
                                                                <p class="text-sm text-teal-800/70">{{ $session->location_address }}</p>
                                                            @endif
                                                        @else
                                                            <p class="mt-2 text-sm text-teal-800/70">Lokasi kelas akan diumumkan.</p>
                                                        @endif
                                                    @endif

                                                    @php($konfirmasi = $confirmationCards[$session->id] ?? null)
                                                    @if ($konfirmasi && $konfirmasi['editable'])
                                                        <div class="mt-3 rounded-2xl border border-teal-900/10 bg-sand-50/60 px-4 py-3.5">
                                                            @if (session('konfirmasi_tersimpan') === $session->id)
                                                                <p class="mb-2 text-xs font-semibold text-teal-700">Konfirmasimu tersimpan. Syukron!</p>
                                                            @endif
                                                            @if ($konfirmasi['status'] === null)
                                                                <p class="text-sm font-semibold text-teal-900">Bisa hadir di kelas ini?</p>
                                                                <p class="mt-0.5 text-xs text-teal-800/60">Konfirmasimu membantu mentor menyiapkan kelas.</p>
                                                                <div class="mt-3 flex flex-wrap gap-2">
                                                                    <form method="POST" action="{{ route('member.session.confirm', $session) }}" data-submit-once>
                                                                        @csrf
                                                                        <input type="hidden" name="status" value="attending">
                                                                        <button type="submit" class="rounded-full bg-teal-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-teal-900">Insya Allah hadir</button>
                                                                    </form>
                                                                    <button type="button" data-modal-open="modal-berhalangan-{{ $session->id }}"
                                                                            class="rounded-full border border-teal-900/15 px-4 py-2 text-sm font-semibold text-teal-800 transition hover:border-orange-400 hover:text-orange-600">Berhalangan</button>
                                                                </div>
                                                            @else
                                                                <div class="flex flex-wrap items-center justify-between gap-2">
                                                                    @if ($konfirmasi['status'] === 'attending')
                                                                        <p class="inline-flex items-center gap-1.5 text-sm font-semibold text-teal-700">
                                                                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M4 10.5l4 4 8-9" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                                            Kamu konfirmasi hadir
                                                                        </p>
                                                                    @else
                                                                        <p class="text-sm font-semibold text-orange-700">Kamu berhalangan hadir</p>
                                                                    @endif
                                                                    <div class="flex flex-wrap gap-2">
                                                                        @if ($konfirmasi['status'] === 'cannot_attend')
                                                                            <form method="POST" action="{{ route('member.session.confirm', $session) }}" data-submit-once>
                                                                                @csrf
                                                                                <input type="hidden" name="status" value="attending">
                                                                                <button type="submit" class="rounded-full border border-teal-900/15 px-3.5 py-1.5 text-xs font-semibold text-teal-700 transition hover:border-teal-600/40">Ubah konfirmasi: jadi hadir</button>
                                                                            </form>
                                                                        @else
                                                                            <button type="button" data-modal-open="modal-berhalangan-{{ $session->id }}"
                                                                                    class="rounded-full border border-teal-900/15 px-3.5 py-1.5 text-xs font-semibold text-teal-700 transition hover:border-orange-400 hover:text-orange-600">Ubah konfirmasi</button>
                                                                        @endif
                                                                    </div>
                                                                </div>
                                                                @if ($konfirmasi['status'] === 'cannot_attend' && $konfirmasi['note'])
                                                                    <p class="mt-2 border-l-2 border-orange-300 pl-3 text-xs italic text-teal-800/70">{{ $konfirmasi['note'] }}</p>
                                                                @endif
                                                            @endif
                                                        </div>

                                                        @php($confirmFailedHere = $errors->hasAny(['status', 'note']) && (int) old('_session_id') === $session->id)
                                                        <x-modal id="modal-berhalangan-{{ $session->id }}" title="Berhalangan hadir?" :autoopen="$confirmFailedHere">
                                                            <p class="rounded-xl bg-sand-50 px-4 py-3 text-sm text-teal-800/80">
                                                                Kabari kendalamu, biar mentor carikan solusi. Kamu masih bisa mengubah konfirmasi ini sampai kelas dimulai.
                                                            </p>
                                                            <form method="POST" action="{{ route('member.session.confirm', $session) }}" data-submit-once class="mt-3 space-y-2.5">
                                                                @csrf
                                                                <input type="hidden" name="status" value="cannot_attend">
                                                                <input type="hidden" name="_session_id" value="{{ $session->id }}">
                                                                <label class="block text-xs font-semibold uppercase tracking-wide text-teal-800/60">Kendalamu (opsional)</label>
                                                                <textarea name="note" rows="3" placeholder="Contoh: bentrok jam kerja, ada acara keluarga."
                                                                          class="w-full rounded-lg border border-teal-900/15 bg-white px-3.5 py-2.5 text-sm text-teal-900 outline-none transition placeholder:text-teal-900/30 focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20">{{ $confirmFailedHere ? old('note') : $konfirmasi['note'] }}</textarea>
                                                                @if ($confirmFailedHere) <p class="text-xs text-red-600">{{ $errors->first('status') ?: $errors->first('note') }}</p> @endif
                                                                <div class="flex justify-end gap-2 pt-2">
                                                                    <button type="button" data-modal-close class="rounded-full border border-teal-900/15 px-5 py-2 text-sm font-semibold text-teal-800 transition hover:border-teal-600/40">Batal</button>
                                                                    <button type="submit" class="rounded-full bg-orange-500 px-5 py-2 text-sm font-semibold text-white transition hover:bg-orange-600">Kirim konfirmasi</button>
                                                                </div>
                                                            </form>
                                                        </x-modal>
                                                    @endif
                                                    @if (isset($assignmentCards[$session->id]))
                                                        @php($tugas = $assignmentCards[$session->id])
                                                        {{-- Tugas lives in its own tab; this row is the signpost. --}}
                                                        <div class="mt-3 flex items-center justify-between gap-3 rounded-2xl border border-teal-900/10 bg-white px-4 py-3">
                                                            <span class="flex min-w-0 items-center gap-2 text-sm">
                                                                <svg class="h-4 w-4 shrink-0 text-teal-700/70" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 5h6m-7 3h8a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1Zm1-3h4a1 1 0 0 1 1 1v2H9V3a1 1 0 0 1 1-1Z" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                                <span class="truncate font-medium text-teal-900">Tugas: {{ $tugas['assignment']->title }}</span>
                                                                <span @class([
                                                                    'hidden shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold sm:inline',
                                                                    'bg-sand-100 text-teal-800/60' => $tugas['state'] === 'belum_dikerjakan',
                                                                    'bg-orange-100 text-orange-700' => $tugas['state'] === 'menunggu_dinilai',
                                                                    'bg-teal-100 text-teal-700' => $tugas['state'] === 'dinilai',
                                                                ])>{{ $tugas['state'] === 'dinilai' ? 'Nilai '.$tugas['score'] : ($tugas['state'] === 'menunggu_dinilai' ? 'Menunggu dinilai' : 'Belum dikerjakan') }}</span>
                                                            </span>
                                                            <a href="{{ route('member.area', ['bagian' => 'tugas']) }}" class="flex shrink-0 items-center gap-1 text-xs font-semibold text-teal-700 transition hover:text-orange-600">
                                                                Lihat tugas
                                                                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="m8 6 4 4-4 4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                            </a>
                                                        </div>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif

                                    <div class="mt-4 space-y-2 text-sm text-teal-800/80">
                                        @if ($cohort->materials_url)
                                            <div class="pt-1">
                                                <a href="{{ $cohort->materials_url }}" target="_blank" rel="noopener"
                                                   class="inline-flex items-center gap-2 rounded-full bg-teal-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-teal-900">
                                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3.5 7A2.5 2.5 0 0 1 6 4.5h3.2c.6 0 1.2.25 1.7.7l1.1 1.1c.2.2.5.2.7.2H18A2.5 2.5 0 0 1 20.5 9v8A2.5 2.5 0 0 1 18 19.5H6A2.5 2.5 0 0 1 3.5 17V7Z" stroke-linejoin="round"/></svg>
                                                    Buka materi kelas
                                                </a>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($openClasses->isNotEmpty())
                    <div class="mt-8">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-teal-800/70">Kelas Dibuka</h2>
                        <div class="mt-4 space-y-3">
                            @foreach ($openClasses as $entry)
                                <div class="flex items-center justify-between gap-4 rounded-2xl border border-teal-900/10 bg-white px-5 py-4">
                                    <div>
                                        <p class="font-semibold text-teal-900">{{ $entry['program']->name }}</p>
                                        <p class="text-xs text-teal-800/70">
                                            @if ($entry['openCohort']?->startLabel())
                                                Kelas dimulai {{ $entry['openCohort']->startLabel() }}
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
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-teal-800/70">Program untuk Anda</h2>
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
                                            <p class="text-xs text-teal-800/70">Level {{ $entry['program']->level }} · Terbuka untukmu</p>
                                        </div>
                                        <svg class="h-4 w-4 shrink-0 text-teal-700" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 10h12M11 5l5 5-5 5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="mt-6 rounded-3xl border border-dashed border-teal-900/15 bg-white/40 p-6 text-center sm:p-8">
                    <p class="text-sm leading-relaxed text-teal-800/70">
                        Materi pilihan dan pengumuman komunitas akan tampil di sini. Nantikan ya!
                    </p>
                </div>
            @elseif ($activeTab === 'tugas')
                <div class="mt-8">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-teal-800/70">Tugas Saya</h2>

                @foreach ($programProgress as $progress)
                    <div class="mt-4 rounded-3xl border border-teal-900/10 bg-white/70 p-6 shadow-sm backdrop-blur sm:p-8">
                        <p class="font-display text-xs uppercase tracking-[0.3em] text-orange-600">Progres Nilai</p>
                        <p class="mt-2 text-lg font-bold text-teal-900">
                            @if ($progress['average'] !== null)
                                Rata-ratamu {{ rtrim(rtrim(number_format($progress['average'], 1, ',', '.'), '0'), ',') }} dari minimum {{ $progress['threshold'] }}
                            @else
                                Belum ada nilai. Minimum kelulusan: {{ $progress['threshold'] }}
                            @endif
                        </p>
                        <div class="mt-3 h-2 overflow-hidden rounded-full bg-sand-100">
                            <div class="h-full rounded-full {{ $progress['qualifies'] ? 'bg-teal-600' : 'bg-orange-400' }}"
                                 style="width: {{ $progress['average'] !== null ? min(100, round($progress['average'] / max(1, $progress['threshold']) * 100)) : 0 }}%"></div>
                        </div>
                        <ul class="mt-4 space-y-1.5 text-sm">
                            @foreach ($progress['rows'] as $row)
                                <li class="flex items-center justify-between gap-3">
                                    <span class="min-w-0 truncate text-teal-800/80">{{ $row['title'] }}</span>
                                    <span @class([
                                        'shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                        'bg-sand-100 text-teal-800/60' => $row['state'] === 'belum_dikerjakan',
                                        'bg-orange-100 text-orange-700' => $row['state'] === 'menunggu_dinilai',
                                        'bg-teal-100 text-teal-700' => $row['state'] === 'dinilai',
                                    ])>{{ $row['state'] === 'dinilai' ? $row['score'] : ($row['state'] === 'menunggu_dinilai' ? 'Menunggu' : 'Belum') }}</span>
                                </li>
                            @endforeach
                        </ul>
                        @if ($progress['qualifies'])
                            <a href="{{ route('daftar') }}" class="mt-4 inline-flex items-center gap-2 rounded-full bg-orange-500 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-orange-600">
                                Kamu memenuhi syarat! Lanjut ke kelas komunitas
                            </a>
                        @else
                            <p class="mt-3 text-sm text-teal-800/70">Capai rata-rata {{ $progress['threshold'] }} untuk membuka kelas komunitas.</p>
                        @endif
                    </div>
                @endforeach

                    @if (collect($assignmentCards)->isEmpty())
                        <div class="mt-4 rounded-3xl border border-dashed border-teal-900/15 bg-white/40 p-8 text-center">
                            <p class="text-sm leading-relaxed text-teal-800/70">
                                Belum ada tugas dari mentormu. Begitu soal dirilis, tugasmu tampil di sini.
                            </p>
                        </div>
                    @endif

                    @foreach ($enrolledClasses as $enrollment)
                        @foreach ($enrollment->cohort->sessions as $session)
                            @if (isset($assignmentCards[$session->id]))
                                @php($tugas = $assignmentCards[$session->id])
                                @php($failedHere = $errors->hasAny(['url', 'note']) && (int) old('_assignment_id') === $tugas['assignment']->id)
                                @php($editFailedHere = $errors->hasAny(['url', 'note']) && (int) old('_submission_id') === ($tugas['latest_id'] ?? 0))
                                @php($operativeIndex = collect($tugas['history'])->search(fn ($h) => $h['score'] !== null))
                                @php($actionLabel = match ($tugas['state']) {
                                    'belum_dikerjakan' => 'Kirim jawaban',
                                    'menunggu_dinilai' => 'Perbaiki kiriman',
                                    default => 'Kirim ulang untuk perbaiki nilai',
                                })

                                <div class="mt-4 rounded-3xl border border-teal-900/10 bg-white/70 p-6 shadow-sm backdrop-blur sm:p-8">
                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <p class="text-xs font-semibold uppercase tracking-wide text-teal-800/60">{{ $session->title }} · {{ $enrollment->cohort->program?->name }}</p>
                                            <p class="mt-1 font-semibold text-teal-900">{{ $tugas['assignment']->title }}</p>
                                        </div>
                                        <span @class([
                                            'shrink-0 rounded-full px-3 py-1 text-xs font-semibold',
                                            'bg-sand-100 text-teal-800/60' => $tugas['state'] === 'belum_dikerjakan',
                                            'bg-orange-100 text-orange-700' => $tugas['state'] === 'menunggu_dinilai',
                                            'bg-teal-100 text-teal-700' => $tugas['state'] === 'dinilai',
                                        ])>{{ $tugas['state'] === 'dinilai' ? 'Dinilai · '.$tugas['score'] : ($tugas['state'] === 'menunggu_dinilai' ? 'Menunggu dinilai' : 'Belum dikerjakan') }}</span>
                                    </div>

                                    @if (session('tugas_terkirim') === $tugas['assignment']->id)
                                        <p class="mt-3 rounded-xl border border-teal-600/30 bg-teal-50 px-4 py-3 text-sm text-teal-800">Jawabanmu terkirim. Mentor akan menilainya segera.</p>
                                    @elseif (session('tugas_diperbarui') === $tugas['assignment']->id)
                                        <p class="mt-3 rounded-xl border border-teal-600/30 bg-teal-50 px-4 py-3 text-sm text-teal-800">Kirimanmu diperbarui.</p>
                                    @endif
                                    @if ($tugas['state'] === 'menunggu_dinilai')
                                        <p class="mt-3 text-sm font-medium text-orange-700">Jawabanmu sudah terkirim, menunggu dinilai mentor.</p>
                                        @if ($tugas['can_edit'])
                                            <p class="mt-1 text-xs text-teal-800/60">Salah tempel link? Kamu masih bisa edit kiriman ini sampai pukul {{ $tugas['edit_until']->format('H.i') }}.</p>
                                        @else
                                            <p class="mt-1 text-xs text-teal-800/60">Kirimanmu terkunci dan masuk antrean penilaian mentor.</p>
                                        @endif
                                    @endif

                                    @unless ($tugas['attended'])
                                        {{-- Attendance gate: soal and sending stay locked until
                                             the mentor marks this member hadir. --}}
                                        <div class="mt-4 flex items-start gap-3 rounded-2xl border border-dashed border-teal-900/15 bg-sand-50/60 px-4 py-3.5">
                                            <svg class="mt-0.5 size-4 shrink-0 text-teal-800/40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                                            <p class="text-sm leading-relaxed text-teal-800/70">Soal dan pengiriman tugas terbuka setelah kehadiranmu ditandai mentor di kelas ini.</p>
                                        </div>
                                    @endunless

                                    @if ($tugas['attended'])
                                    {{-- Soal collapsible: same pill pattern as the map. --}}
                                    <details class="kh-collapsible group mt-4 overflow-hidden rounded-2xl border border-teal-900/10 bg-white">
                                        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 transition hover:bg-sand-50 [&::-webkit-details-marker]:hidden">
                                            <span class="text-sm font-semibold text-teal-900">Soal tugas</span>
                                            <span class="flex shrink-0 items-center gap-1.5 rounded-full border border-teal-900/15 px-3 py-1.5 text-xs font-semibold text-teal-700 transition group-hover:border-teal-600/40">
                                                <span class="group-open:hidden">Lihat soal</span>
                                                <span class="hidden group-open:inline">Tutup soal</span>
                                                <svg class="h-3.5 w-3.5 transition-transform group-open:rotate-180" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 8 4 4 4-4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                            </span>
                                        </summary>
                                        <div class="kh-prose border-t border-teal-900/10 px-4 py-4 text-sm text-teal-800/80">{!! $tugas['assignment']->bodyHtml() !!}</div>
                                    </details>
                                    @endif

                                    @if ($tugas['versions'] > 0)
                                        {{-- Riwayat kiriman: satu baris per kiriman; baris "berlaku"
                                             disorot — inilah nilai yang dihitung. --}}
                                        <div class="mt-4 overflow-hidden rounded-2xl border border-teal-900/10 bg-white">
                                            <div class="overflow-x-auto">
                                            <table class="w-full min-w-[26rem] text-sm">
                                                <thead>
                                                    <tr class="bg-sand-50 text-left text-[0.65rem] font-semibold uppercase tracking-[0.14em] text-teal-800/60">
                                                        <th class="px-4 py-2.5">Kiriman</th>
                                                        <th class="px-4 py-2.5">Link</th>
                                                        <th class="hidden px-4 py-2.5 sm:table-cell">Waktu</th>
                                                        <th class="px-4 py-2.5 text-right">Nilai</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-teal-900/5">
                                                    @foreach ($tugas['history'] as $i => $item)
                                                        <tr @class(['bg-teal-50/60' => $i === $operativeIndex])>
                                                            <td class="px-4 py-2.5 font-medium text-teal-900">
                                                                #{{ $tugas['versions'] - $i }}
                                                                @if ($i === $operativeIndex)
                                                                    <span class="ml-1.5 rounded-full bg-teal-700 px-2 py-0.5 text-[0.65rem] font-bold uppercase tracking-wide text-white">Berlaku</span>
                                                                @endif
                                                            </td>
                                                            <td class="w-full max-w-0 px-4 py-2.5">
                                                                <a href="{{ $item['url'] }}" target="_blank" rel="noopener" class="block truncate text-teal-700 hover:underline">{{ preg_replace('#^https?://#i', '', $item['url']) }}</a>
                                                            </td>
                                                            <td class="hidden whitespace-nowrap px-4 py-2.5 text-teal-800/60 sm:table-cell">{{ $item['at']->locale('id')->translatedFormat('j M Y H.i') }}</td>
                                                            <td class="px-4 py-2.5 text-right font-semibold tabular-nums {{ $item['score'] !== null ? 'text-teal-900' : 'text-orange-600' }}">
                                                                <span class="inline-flex items-center gap-2">
                                                                    {{ $item['score'] ?? 'Menunggu' }}
                                                                    @if ($i === 0 && $tugas['can_edit'])
                                                                        <button type="button" data-modal-open="modal-edit-tugas-{{ $tugas['assignment']->id }}"
                                                                                class="whitespace-nowrap rounded-full border border-teal-900/15 px-2.5 py-0.5 text-[0.7rem] font-semibold text-teal-700 transition hover:border-teal-600/40 hover:text-orange-600">
                                                                            Edit kiriman
                                                                        </button>
                                                                    @endif
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                            </div>
                                        </div>
                                    @endif

                                    @if ($tugas['state'] === 'dinilai' && $tugas['feedback'])
                                        <blockquote class="mt-3 border-l-2 border-orange-400 pl-3 text-sm italic text-teal-800/80">{{ $tugas['feedback'] }}</blockquote>
                                    @endif

                                    @if ($tugas['attended'] && $tugas['state'] !== 'menunggu_dinilai')
                                    <div class="mt-4">
                                        <button type="button" data-modal-open="modal-tugas-{{ $tugas['assignment']->id }}"
                                                @class([
                                                    'inline-flex items-center gap-2 rounded-full px-5 py-2.5 text-sm font-semibold transition',
                                                    'bg-teal-700 text-white hover:bg-teal-900' => $tugas['state'] === 'belum_dikerjakan',
                                                    'border border-teal-900/15 text-teal-700 hover:border-teal-600/40 hover:text-orange-600' => $tugas['state'] !== 'belum_dikerjakan',
                                                ])>
                                            {{ $actionLabel }}
                                        </button>
                                    </div>
                                    @endif
                                </div>

                                @if ($tugas['attended'] && $tugas['state'] !== 'menunggu_dinilai')
                                <x-modal id="modal-tugas-{{ $tugas['assignment']->id }}"
                                         :title="$tugas['state'] === 'dinilai' ? 'Kirim ulang tugas?' : ($tugas['state'] === 'menunggu_dinilai' ? 'Perbaiki kirimanmu' : 'Kirim jawabanmu')"
                                         :autoopen="$failedHere">
                                    @if ($tugas['state'] === 'dinilai')
                                        <p class="rounded-xl border border-orange-300/60 bg-orange-50 px-4 py-3 text-sm text-orange-800">
                                            Nilai {{ $tugas['score'] }} tetap berlaku sampai kiriman barumu dinilai mentor.
                                        </p>
                                    @elseif ($tugas['state'] === 'menunggu_dinilai')
                                        <p class="rounded-xl bg-sand-50 px-4 py-3 text-sm text-teal-800/80">
                                            Kiriman lamamu tetap tercatat; versi baru menggantikannya dalam antrean penilaian.
                                        </p>
                                    @endif
                                    <form method="POST" action="{{ route('member.assignment.submit', $tugas['assignment']) }}" data-submit-once class="mt-3 space-y-2.5">
                                        @csrf
                                        <input type="hidden" name="_assignment_id" value="{{ $tugas['assignment']->id }}">
                                        <label class="block text-xs font-semibold uppercase tracking-wide text-teal-800/60">Link jawaban</label>
                                        <div class="flex w-full">
                                            <span class="inline-flex items-center rounded-l-lg border border-r-0 {{ $failedHere ? 'border-red-400' : 'border-teal-900/15' }} bg-sand-50 px-3 text-sm text-teal-800/60">https://</span>
                                            <input type="text" name="url" value="{{ $failedHere ? preg_replace('#^https?://#i', '', old('url', '')) : '' }}" placeholder="drive.google.com/…" inputmode="url"
                                                   autocapitalize="off" autocorrect="off" spellcheck="false"
                                                   class="w-full min-w-0 rounded-r-lg border {{ $failedHere ? 'border-red-400' : 'border-teal-900/15' }} bg-white px-3.5 py-2.5 text-sm text-teal-900 outline-none transition placeholder:text-teal-900/30 focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20">
                                        </div>
                                        @if ($failedHere) <p class="text-xs text-red-600">{{ $errors->first('url') }}</p> @endif
                                        <input type="text" name="note" value="{{ $failedHere ? old('note') : '' }}" placeholder="Catatan untuk mentor (opsional)"
                                               class="w-full rounded-lg border border-teal-900/15 bg-white px-3.5 py-2.5 text-sm text-teal-900 outline-none transition placeholder:text-teal-900/30 focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20">
                                        @if ($failedHere && $errors->has('note')) <p class="text-xs text-red-600">{{ $errors->first('note') }}</p> @endif
                                        <div class="flex justify-end gap-2 pt-2">
                                            <button type="button" data-modal-close class="rounded-full border border-teal-900/15 px-5 py-2 text-sm font-semibold text-teal-800 transition hover:border-teal-600/40">Batal</button>
                                            <button type="submit" class="rounded-full bg-teal-700 px-5 py-2 text-sm font-semibold text-white transition hover:bg-teal-900">{{ $actionLabel }}</button>
                                        </div>
                                    </form>
                                </x-modal>
                                @endif

                                {{-- Rendered only while editable (or to resurface an edit
                                     error) so a locked card carries no edit affordance at all. --}}
                                @if ($tugas['state'] === 'menunggu_dinilai' && $tugas['latest_id'] && ($tugas['can_edit'] || $editFailedHere))
                                    <x-modal id="modal-edit-tugas-{{ $tugas['assignment']->id }}" title="Edit kirimanmu" :autoopen="$editFailedHere">
                                        <p class="rounded-xl bg-sand-50 px-4 py-3 text-sm text-teal-800/80">
                                            Perubahan menggantikan kiriman yang sama, tanpa menambah baris baru. Bisa diedit sampai pukul {{ $tugas['edit_until']?->format('H.i') ?? '-' }}.
                                        </p>
                                        <form method="POST" action="{{ route('member.submission.update', $tugas['latest_id']) }}" data-submit-once class="mt-3 space-y-2.5">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="_submission_id" value="{{ $tugas['latest_id'] }}">
                                            <label class="block text-xs font-semibold uppercase tracking-wide text-teal-800/60">Link jawaban</label>
                                            <div class="flex w-full">
                                                <span class="inline-flex items-center rounded-l-lg border border-r-0 {{ $editFailedHere ? 'border-red-400' : 'border-teal-900/15' }} bg-sand-50 px-3 text-sm text-teal-800/60">https://</span>
                                                <input type="text" name="url" value="{{ $editFailedHere ? preg_replace('#^https?://#i', '', old('url', '')) : preg_replace('#^https?://#i', '', $tugas['latest_url'] ?? '') }}" placeholder="drive.google.com/…" inputmode="url"
                                                       autocapitalize="off" autocorrect="off" spellcheck="false"
                                                       class="w-full min-w-0 rounded-r-lg border {{ $editFailedHere ? 'border-red-400' : 'border-teal-900/15' }} bg-white px-3.5 py-2.5 text-sm text-teal-900 outline-none transition placeholder:text-teal-900/30 focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20">
                                            </div>
                                            @if ($editFailedHere) <p class="text-xs text-red-600">{{ $errors->first('url') }}</p> @endif
                                            <input type="text" name="note" value="{{ $editFailedHere ? old('note') : $tugas['latest_note'] }}" placeholder="Catatan untuk mentor (opsional)"
                                                   class="w-full rounded-lg border border-teal-900/15 bg-white px-3.5 py-2.5 text-sm text-teal-900 outline-none transition placeholder:text-teal-900/30 focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20">
                                            @if ($editFailedHere && $errors->has('note')) <p class="text-xs text-red-600">{{ $errors->first('note') }}</p> @endif
                                            <div class="flex justify-end gap-2 pt-2">
                                                <button type="button" data-modal-close class="rounded-full border border-teal-900/15 px-5 py-2 text-sm font-semibold text-teal-800 transition hover:border-teal-600/40">Batal</button>
                                                <button type="submit" class="rounded-full bg-teal-700 px-5 py-2 text-sm font-semibold text-white transition hover:bg-teal-900">Simpan perubahan</button>
                                            </div>
                                        </form>
                                    </x-modal>
                                @endif
                            @endif
                        @endforeach
                    @endforeach
                </div>
            @else
                <div class="mt-8 rounded-3xl border border-teal-900/10 bg-white/70 p-6 shadow-sm backdrop-blur sm:p-8">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-teal-800/70">Profil</h2>
                    {{-- Two columns at the wider page width: keeps each
                         label-value pair tight instead of stretching across. --}}
                    <dl class="mt-4 grid gap-x-10 gap-y-4 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-teal-800/50">Nama</dt>
                            <dd class="mt-0.5 font-medium text-teal-900">{{ $user->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-teal-800/50">Email</dt>
                            <dd class="mt-0.5 font-medium text-teal-900">{{ $user->email }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-teal-800/50">Nomor HP</dt>
                            <dd class="mt-0.5 font-medium text-teal-900">{{ $person?->phone ?? '—' }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="mt-6 rounded-3xl border border-teal-900/10 bg-white/70 p-6 shadow-sm backdrop-blur sm:p-8">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-teal-800/70">Komunitas</h2>
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

    {{-- Navigasi bawah khusus halaman akun: Beranda + tab akun --}}
    <x-nav.bottom-nav label="Bagian akun">
        <x-nav.bottom-nav-item :href="url('/')" label="Beranda">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m4 10.5 8-6.75 8 6.75"/><path d="M6 8.75V19a1 1 0 0 0 1 1h3.25v-4.5a1.75 1.75 0 0 1 3.5 0V20H17a1 1 0 0 0 1-1V8.75"/></svg>
        </x-nav.bottom-nav-item>
        <x-nav.bottom-nav-item :href="route('member.area', ['bagian' => 'profil'])" label="Profil" :active="$activeTab === 'profil'">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="3.25"/><path d="M5.5 19.5a6.5 6.5 0 0 1 13 0"/></svg>
        </x-nav.bottom-nav-item>
        <x-nav.bottom-nav-item :href="route('member.area', ['bagian' => 'pendaftaran'])" label="Pendaftaran" :active="$activeTab === 'pendaftaran'">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5h6M9 5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2"/><path d="m9.5 13 1.75 1.75L14.75 11"/></svg>
        </x-nav.bottom-nav-item>
        <x-nav.bottom-nav-item :href="route('member.area', ['bagian' => 'kelas'])" label="Kelas" :active="$activeTab === 'kelas'">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 6.25C10 4.75 7.5 4.5 5 4.5v13c2.5 0 5 .25 7 1.75 2-1.5 4.5-1.75 7-1.75v-13c-2.5 0-5 .25-7 1.75Z"/><path d="M12 6.25v13"/></svg>
        </x-nav.bottom-nav-item>
        <x-nav.bottom-nav-item :href="route('member.area', ['bagian' => 'tugas'])" label="Tugas" :active="$activeTab === 'tugas'">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5h6M9 5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2"/><path d="m9.5 12.5 1.75 1.75 3.25-3.5"/></svg>
        </x-nav.bottom-nav-item>
    </x-nav.bottom-nav>

</x-layouts.public>
