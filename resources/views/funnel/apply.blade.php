<x-layouts.public :title="'Daftar ' . $program->name"
    :description="'Formulir pendaftaran ' . $program->name . ' Kheedma Academy.'">

    @php
        $field = 'mt-1.5 w-full rounded-lg bg-white px-3.5 py-2.5 text-sm text-teal-900 outline-none transition placeholder:text-teal-900/30 focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20';
        $gmvLabels = ['0-50' => '0-50 Juta', '50-100' => '50-100 Juta', '100+' => 'Di atas 100 Juta'];
    @endphp

    <section class="relative overflow-hidden">
        <div class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full bg-teal-100 blur-3xl"></div>

        <div class="relative mx-auto max-w-2xl px-6 py-16 sm:py-20">
            <div class="text-center">
                <x-logo variant="stacked" class="mx-auto h-20" />
                <p class="mt-8 font-display text-xs uppercase tracking-[0.3em] text-orange-600">Pendaftaran</p>
                <h1 class="mt-3 text-3xl font-bold leading-tight text-teal-900 sm:text-4xl">{{ $program->name }}</h1>
                <p class="mx-auto mt-4 max-w-lg text-base leading-relaxed text-teal-800/80">
                    {{ $program->tagline ?: 'Isi data di bawah ini. Setelah mendaftar, kamu akan menerima tugas pra-seleksi sebagai langkah menunjukkan kesungguhan.' }}
                </p>
            </div>

            @if ($errors->any())
                <div class="mt-8 rounded-xl border border-orange-600/30 bg-orange-50 px-5 py-4 text-sm text-orange-700">
                    Ada beberapa isian yang perlu diperbaiki. Silakan cek kembali field di bawah.
                </div>
            @endif

            @if ($stateNotice)
                <div class="mt-10 rounded-3xl border border-teal-900/10 bg-white/70 p-6 text-center shadow-sm backdrop-blur sm:p-8">
                    <h2 class="text-lg font-bold text-teal-900">{{ $stateNotice['title'] }}</h2>
                    <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-teal-800/70">
                        {{ $stateNotice['body'] }}
                    </p>
                    <div class="mt-5">
                        <x-cta :href="route('member.area')" label="Lihat Status" />
                    </div>
                </div>
            @elseif ($showGate && ! $errors->any())
                {{-- The account question comes FIRST: returning members log in and
                     come back to a prefilled form; new people open the blank form. --}}
                <div class="mt-10 rounded-3xl border border-teal-900/10 bg-white/70 p-6 text-center shadow-sm backdrop-blur sm:p-8">
                    <h2 class="text-lg font-bold text-teal-900">Sudah punya akun Kheedma?</h2>
                    <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-teal-800/70">
                        Masuk dulu supaya datamu terisi otomatis dan pendaftaranmu tersambung ke akunmu.
                    </p>
                    <div class="mt-6 grid gap-3 sm:grid-cols-2">
                        <a href="{{ route('member.login', ['redirect' => url()->current()]) }}"
                           class="inline-flex items-center justify-center rounded-full bg-teal-700 px-6 py-3 text-sm font-semibold text-white shadow-md transition hover:bg-teal-800">
                            Sudah, masuk ke akunku
                        </a>
                        <a href="{{ request()->fullUrlWithQuery(['baru' => 1]) }}"
                           class="inline-flex items-center justify-center rounded-full border border-teal-900/15 bg-white px-6 py-3 text-sm font-semibold text-teal-800 transition hover:border-teal-600/40 hover:text-orange-600">
                            Belum, daftar baru
                        </a>
                    </div>
                </div>
            @elseif ($confirming && ! $errors->any())
                {{-- Logged-in members confirm their stored data instead of retyping it. --}}
                <form method="POST" data-submit-once action="{{ route('program.apply.store', $program) }}" class="mt-10 space-y-6 rounded-3xl border border-teal-900/10 bg-white/70 p-6 shadow-sm backdrop-blur sm:p-8">
                    @csrf
                    <input type="hidden" name="name" value="{{ $person->name }}">
                    <input type="hidden" name="phone" value="{{ $person->phone }}">
                    <input type="hidden" name="email" value="{{ $person->email }}">
                    <input type="hidden" name="province_code" value="{{ $person->province_code }}">
                    <input type="hidden" name="city_code" value="{{ $person->city_code }}">
                    <input type="hidden" name="tiktok_username" value="{{ $person->tiktok_username }}">
                    <input type="hidden" name="instagram_username" value="{{ $person->instagram_username }}">
                    <input type="hidden" name="birth_date" value="{{ $person->birth_date?->toDateString() }}">
                    <input type="hidden" name="gender" value="{{ $person->gender }}">
                    <input type="hidden" name="tiktok_followers" value="{{ $person->tiktok_followers }}">
                    <input type="hidden" name="has_started_affiliate" value="{{ $person->has_started_affiliate === null ? '' : ($person->has_started_affiliate ? '1' : '0') }}">
                    <input type="hidden" name="affiliate_level" value="{{ $person->affiliate_level }}">
                    <input type="hidden" name="affiliate_gmv_range" value="{{ $person->affiliate_gmv_range }}">
                    <input type="hidden" name="followed_socials" value="{{ $person->followed_socials === null ? '' : ($person->followed_socials ? 1 : 0) }}">

                    <div>
                        <h2 class="text-lg font-bold text-teal-900">Konfirmasi datamu</h2>
                        <p class="mt-1 text-sm leading-relaxed text-teal-800/70">
                            Pastikan data di bawah ini benar sebelum mengirim pendaftaran.
                        </p>
                    </div>

                    <dl class="space-y-3 rounded-2xl border border-teal-900/10 bg-white px-5 py-4 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-teal-800/60">Nama lengkap</dt>
                            <dd class="font-medium text-teal-900">{{ $person->name }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-teal-800/60">Nomor HP</dt>
                            <dd class="font-medium text-teal-900">{{ $person->phone }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-teal-800/60">Email</dt>
                            <dd class="font-medium text-teal-900">{{ $person->email }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-teal-800/60">Domisili</dt>
                            <dd class="font-medium text-teal-900">{{ $person->city?->name ?? '—' }}{{ $person->province ? ', '.$person->province->name : '' }}</dd>
                        </div>
                        @if ($person->tiktok_username)
                            <div class="flex justify-between gap-4">
                                <dt class="text-teal-800/60">TikTok</dt>
                                <dd class="font-medium text-teal-900">{{ $person->tiktok_username }}</dd>
                            </div>
                        @endif
                        @if ($person->instagram_username)
                            <div class="flex justify-between gap-4">
                                <dt class="text-teal-800/60">Instagram</dt>
                                <dd class="font-medium text-teal-900">{{ $person->instagram_username }}</dd>
                            </div>
                        @endif
                        @if ($person->birth_date)
                            <div class="flex justify-between gap-4">
                                <dt class="text-teal-800/60">Tanggal lahir</dt>
                                <dd class="font-medium text-teal-900">{{ $person->birth_date->locale('id')->translatedFormat('j F Y') }} ({{ $person->age }} tahun)</dd>
                            </div>
                        @endif
                        @if ($person->gender)
                            <div class="flex justify-between gap-4">
                                <dt class="text-teal-800/60">Jenis kelamin</dt>
                                <dd class="font-medium text-teal-900">{{ $person->gender === 'male' ? 'Laki-laki' : 'Perempuan' }}</dd>
                            </div>
                        @endif
                        @if ($person->tiktok_followers !== null)
                            <div class="flex justify-between gap-4">
                                <dt class="text-teal-800/60">Followers TikTok</dt>
                                <dd class="font-medium text-teal-900">{{ number_format($person->tiktok_followers) }}</dd>
                            </div>
                        @endif
                        @if ($person->has_started_affiliate !== null)
                            <div class="flex justify-between gap-4">
                                <dt class="text-teal-800/60">Affiliate TikTok</dt>
                                <dd class="font-medium text-teal-900">
                                    @if ($person->has_started_affiliate)
                                        Sudah &middot; Level {{ $person->affiliate_level }} &middot; {{ $gmvLabels[$person->affiliate_gmv_range] ?? $person->affiliate_gmv_range }}
                                    @else
                                        Belum mulai
                                    @endif
                                </dd>
                            </div>
                        @endif
                    </dl>

                    <div>
                        <label for="motivation" class="block text-sm font-medium text-teal-800">Kenapa kamu ingin ikut program ini?</label>
                        <textarea id="motivation" name="motivation" rows="3"
                                  class="{{ $field }} @error('motivation') border border-red-400 @else border border-teal-900/15 @enderror"
                                  placeholder="Ceritakan alasanmu…">{{ old('motivation') }}</textarea>
                        @error('motivation') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="referral_source" class="block text-sm font-medium text-teal-800">Tahu program ini dari mana?</label>
                        <select id="referral_source" name="referral_source"
                                class="{{ $field }} @error('referral_source') border border-red-400 @else border border-teal-900/15 @enderror">
                            <option value="">Pilih salah satu…</option>
                            @foreach ([
                                'instagram' => 'Instagram',
                                'tiktok' => 'TikTok',
                                'whatsapp' => 'WhatsApp',
                                'teman' => 'Teman atau keluarga',
                                'google' => 'Pencarian Google',
                                'lainnya' => 'Lainnya',
                            ] as $value => $label)
                                <option value="{{ $value }}" @selected(old('referral_source') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('referral_source') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex flex-wrap items-center gap-4 pt-2">
                        <button type="submit"
                                class="inline-flex items-center justify-center gap-2 rounded-full bg-orange-500 px-7 py-3.5 text-sm font-semibold text-white shadow-md transition hover:bg-orange-600 hover:shadow-lg">
                            Data Benar, Kirim Pendaftaran
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 10h12M11 5l5 5-5 5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>
                        <a href="{{ request()->fullUrlWithQuery(['ubah' => 1]) }}"
                           class="text-sm font-semibold text-teal-700 transition hover:text-orange-600">
                            Ubah data dulu
                        </a>
                    </div>
                </form>
            @else
            @if ($applicationState === 'rejected')
                <p class="mt-8 rounded-2xl border border-orange-200 bg-orange-50 px-4 py-3 text-center text-sm text-orange-800">
                    Pendaftaranmu sebelumnya belum lolos. Kamu boleh mencoba lagi, semangat!
                </p>
            @endif
            <form method="POST" data-submit-once data-live-validate data-validate-url="{{ url()->current() }}" action="{{ route('program.apply.store', $program) }}" class="mt-10 space-y-6 rounded-3xl border border-teal-900/10 bg-white/70 p-6 shadow-sm backdrop-blur sm:p-8">
                @csrf

                {{-- Honeypot: hidden from humans (inline style so it never depends on CSS build), tempting to bots --}}
                <div aria-hidden="true" style="position:absolute; left:-9999px; top:-9999px; width:1px; height:1px; overflow:hidden;">
                    <label>Website
                        <input type="text" name="website" tabindex="-1" autocomplete="off" value="">
                    </label>
                </div>

                <div>
                    <label for="name" class="block text-sm font-medium text-teal-800">Nama lengkap</label>
                    <input id="name" name="name" type="text" value="{{ old('name', $person?->name) }}" autocomplete="name"
                           class="{{ $field }} @error('name') border border-red-400 @else border border-teal-900/15 @enderror"
                           placeholder="Nama sesuai identitas">
                    @error('name') <p data-server-error-for="name" class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="phone" class="block text-sm font-medium text-teal-800">Nomor HP <span class="text-teal-800/50">(WhatsApp aktif)</span></label>
                    <input id="phone" name="phone" type="tel" inputmode="tel" value="{{ old('phone', $person?->phone) }}" autocomplete="tel"
                           class="{{ $field }} @error('phone') border border-red-400 @else border border-teal-900/15 @enderror"
                           placeholder="0812xxxxxxx">
                    @error('phone') <p data-server-error-for="phone" class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-teal-800">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $person?->email) }}" autocomplete="email"
                           class="{{ $field }} @error('email') border border-red-400 @else border border-teal-900/15 @enderror"
                           placeholder="kamu@email.com">
                    @error('email') <p data-server-error-for="email" class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="birth_date" class="block text-sm font-medium text-teal-800">Tanggal lahir</label>
                    {{-- Duet renders its own input (id = identifier, so the label keeps
                         working) but never submits it; the hidden field below carries the
                         ISO value the server validates. Wiring lives in initBirthDatePicker(). --}}
                    <duet-date-picker
                        identifier="birth_date"
                        value="{{ old('birth_date', $person?->birth_date?->toDateString()) }}"
                        max="{{ now()->subDay()->toDateString() }}"
                        class="mt-1.5 block @error('birth_date') duet-field-invalid @enderror"
                    ></duet-date-picker>
                    <input type="hidden" name="birth_date" id="birth_date_value"
                           value="{{ old('birth_date', $person?->birth_date?->toDateString()) }}">
                    <p class="mt-1.5 text-xs text-teal-800/60">Ketik langsung (contoh: 17-08-1998) atau buka kalender.</p>
                    @error('birth_date') <p data-server-error-for="birth_date" class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                @php $genderOld = old('gender', $person?->gender); @endphp
                <div>
                    <span class="block text-sm font-medium text-teal-800">Jenis kelamin</span>
                    <div class="mt-1.5 flex gap-3">
                        <label class="cursor-pointer rounded-full border border-teal-900/15 px-5 py-2 text-sm font-medium text-teal-800 transition has-[:checked]:border-teal-600 has-[:checked]:bg-teal-700 has-[:checked]:text-white">
                            <input type="radio" name="gender" value="male" class="sr-only" @checked($genderOld === 'male')>
                            Laki-laki
                        </label>
                        <label class="cursor-pointer rounded-full border border-teal-900/15 px-5 py-2 text-sm font-medium text-teal-800 transition has-[:checked]:border-teal-600 has-[:checked]:bg-teal-700 has-[:checked]:text-white">
                            <input type="radio" name="gender" value="female" class="sr-only" @checked($genderOld === 'female')>
                            Perempuan
                        </label>
                    </div>
                    @error('gender') <p data-server-error-for="gender" class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                @guest
                    <div>
                        <label for="password" class="block text-sm font-medium text-teal-800">Buat kata sandi <span class="text-teal-800/50">(minimal 8 karakter)</span></label>
                        <input id="password" name="password" type="password" autocomplete="new-password"
                               class="{{ $field }} @error('password') border border-red-400 @else border border-teal-900/15 @enderror"
                               placeholder="••••••••">
                        <p class="mt-1.5 text-xs text-teal-800/50">Akunmu dibuat otomatis untuk memantau status pendaftaran dan mengubah datamu nanti.</p>
                        @error('password') <p data-server-error-for="password" class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endguest

                <div class="grid gap-6 sm:grid-cols-2">
                    {{-- Region selects are enhanced into searchable comboboxes by app.js
                         (Tom Select); the wrapper inherits these classes, so keep them
                         to the error marker only. --}}
                    <div>
                        <label for="province_code" class="block text-sm font-medium text-teal-800">Provinsi</label>
                        <select id="province_code" name="province_code"
                                class="@error('province_code') has-error @enderror">
                            <option value="">Pilih provinsi…</option>
                            @foreach ($provinces as $province)
                                <option value="{{ $province->code }}" @selected(old('province_code', $person?->province_code) === $province->code)>{{ $province->name }}</option>
                            @endforeach
                        </select>
                        @error('province_code') <p data-server-error-for="province_code" class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="city_code" class="block text-sm font-medium text-teal-800">Kota/Kabupaten</label>
                        <select id="city_code" name="city_code" data-old="{{ old('city_code', $person?->city_code) }}"
                                data-cities-url="{{ url('/daftar/cities') }}" disabled
                                class="@error('city_code') has-error @enderror">
                            <option value="">Pilih provinsi dulu…</option>
                        </select>
                        @error('city_code') <p data-server-error-for="city_code" class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid gap-6 sm:grid-cols-2">
                    <div>
                        <label for="tiktok_username" class="block text-sm font-medium text-teal-800">Akun TikTok <span class="text-teal-800/50">(opsional, tanpa @)</span></label>
                        <input id="tiktok_username" name="tiktok_username" type="text" value="{{ old('tiktok_username', $person?->tiktok_username) }}"
                               class="{{ $field }} border border-teal-900/15" placeholder="username">
                        @error('tiktok_username') <p data-server-error-for="tiktok_username" class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="instagram_username" class="block text-sm font-medium text-teal-800">Akun Instagram <span class="text-teal-800/50">(opsional)</span></label>
                        <input id="instagram_username" name="instagram_username" type="text" value="{{ old('instagram_username', $person?->instagram_username) }}"
                               class="{{ $field }} border border-teal-900/15" placeholder="@username">
                        @error('instagram_username') <p data-server-error-for="instagram_username" class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                @php
                    $hasStartedAffiliateOld = old(
                        'has_started_affiliate',
                        $person?->has_started_affiliate === null ? null : ($person->has_started_affiliate ? '1' : '0')
                    );
                @endphp
                <div data-tiktok-dependents class="hidden space-y-6">
                    <div>
                        <label for="tiktok_followers" class="block text-sm font-medium text-teal-800">Jumlah followers TikTok</label>
                        <input id="tiktok_followers" name="tiktok_followers" type="number" min="0"
                               value="{{ old('tiktok_followers', $person?->tiktok_followers) }}"
                               class="{{ $field }} @error('tiktok_followers') border border-red-400 @else border border-teal-900/15 @enderror">
                        @error('tiktok_followers') <p data-server-error-for="tiktok_followers" class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <span class="block text-sm font-medium text-teal-800">Sudah mulai affiliate TikTok?</span>
                        <div class="mt-1.5 flex gap-3">
                            <label class="cursor-pointer rounded-full border border-teal-900/15 px-5 py-2 text-sm font-medium text-teal-800 transition has-[:checked]:border-teal-600 has-[:checked]:bg-teal-700 has-[:checked]:text-white">
                                <input type="radio" name="has_started_affiliate" value="1" class="sr-only" @checked($hasStartedAffiliateOld === '1')>
                                Sudah
                            </label>
                            <label class="cursor-pointer rounded-full border border-teal-900/15 px-5 py-2 text-sm font-medium text-teal-800 transition has-[:checked]:border-teal-600 has-[:checked]:bg-teal-700 has-[:checked]:text-white">
                                <input type="radio" name="has_started_affiliate" value="0" class="sr-only" @checked($hasStartedAffiliateOld === '0')>
                                Belum
                            </label>
                        </div>
                        @error('has_started_affiliate') <p data-server-error-for="has_started_affiliate" class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div data-affiliate-dependents class="hidden space-y-6">
                        <div>
                            <label for="affiliate_level" class="block text-sm font-medium text-teal-800">Level affiliate</label>
                            <select id="affiliate_level" name="affiliate_level"
                                    class="{{ $field }} @error('affiliate_level') border border-red-400 @else border border-teal-900/15 @enderror">
                                <option value="">Pilih level…</option>
                                @for ($level = 0; $level <= 8; $level++)
                                    <option value="{{ $level }}" @selected((string) old('affiliate_level', $person?->affiliate_level) === (string) $level)>{{ $level }}</option>
                                @endfor
                            </select>
                            @error('affiliate_level') <p data-server-error-for="affiliate_level" class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="affiliate_gmv_range" class="block text-sm font-medium text-teal-800">GMV affiliate TikTok</label>
                            <select id="affiliate_gmv_range" name="affiliate_gmv_range"
                                    class="{{ $field }} @error('affiliate_gmv_range') border border-red-400 @else border border-teal-900/15 @enderror">
                                <option value="">Pilih rentang…</option>
                                @foreach ($gmvLabels as $value => $label)
                                    <option value="{{ $value }}" @selected(old('affiliate_gmv_range', $person?->affiliate_gmv_range) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('affiliate_gmv_range') <p data-server-error-for="affiliate_gmv_range" class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                <x-social-follow :old="old('followed_socials', isset($person) && $person?->followed_socials !== null ? ($person->followed_socials ? '1' : '0') : null)" />

                <div>
                    <label for="motivation" class="block text-sm font-medium text-teal-800">Kenapa kamu ingin ikut program ini?</label>
                    <textarea id="motivation" name="motivation" rows="3"
                              class="{{ $field }} @error('motivation') border border-red-400 @else border border-teal-900/15 @enderror"
                              placeholder="Ceritakan alasanmu…">{{ old('motivation') }}</textarea>
                    @error('motivation') <p data-server-error-for="motivation" class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="referral_source" class="block text-sm font-medium text-teal-800">Tahu program ini dari mana?</label>
                    <select id="referral_source" name="referral_source"
                            class="{{ $field }} @error('referral_source') border border-red-400 @else border border-teal-900/15 @enderror">
                        <option value="">Pilih salah satu…</option>
                        @foreach ([
                            'instagram' => 'Instagram',
                            'tiktok' => 'TikTok',
                            'whatsapp' => 'WhatsApp',
                            'teman' => 'Teman atau keluarga',
                            'google' => 'Pencarian Google',
                            'lainnya' => 'Lainnya',
                        ] as $value => $label)
                            <option value="{{ $value }}" @selected(old('referral_source') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('referral_source') <p data-server-error-for="referral_source" class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                @auth
                    <p class="text-xs text-teal-800/60">Masuk sebagai <span class="font-semibold">{{ auth()->user()->name }}</span>. Perubahan data di atas akan tersimpan di akunmu.</p>
                @endauth

                <div class="pt-2">
                    <button type="submit"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-orange-500 px-7 py-3.5 text-sm font-semibold text-white shadow-md transition hover:bg-orange-600 hover:shadow-lg sm:w-auto">
                        Kirim Pendaftaran
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 10h12M11 5l5 5-5 5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                </div>
            </form>
            @endif

            <p class="mt-6 text-center text-xs text-teal-800/50">
                Dengan mendaftar, kamu setuju mengikuti masa observasi terbimbing yang dipantau.
            </p>
        </div>
    </section>

</x-layouts.public>
