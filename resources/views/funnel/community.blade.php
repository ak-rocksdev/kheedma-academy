<x-layouts.public title="Gabung Komunitas"
    description="Gabung komunitas affiliator Kheedma Academy: materi, kabar terbaru, dan teman seperjalanan.">

    @php
        $field = 'mt-1.5 w-full rounded-lg bg-white px-3.5 py-2.5 text-sm text-teal-900 outline-none transition placeholder:text-teal-900/30 focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20';
        $gmvLabels = ['0-50' => '0-50 Juta', '50-100' => '50-100 Juta', '100+' => 'Di atas 100 Juta'];
    @endphp

    <section class="relative overflow-hidden">
        <div class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full bg-teal-100 blur-3xl"></div>

        <div class="relative mx-auto max-w-2xl px-6 py-16 sm:py-20">
            <div class="text-center">
                <x-logo variant="stacked" class="mx-auto h-20" />
                <p class="mt-8 font-display text-xs uppercase tracking-[0.3em] text-orange-600">Komunitas</p>
                <h1 class="mt-3 text-3xl font-bold leading-tight text-teal-900 sm:text-4xl">Gabung Komunitas Affiliator.</h1>
                <p class="mx-auto mt-4 max-w-lg text-base leading-relaxed text-teal-800/80">
                    Gratis. Buat akunmu, dapatkan kabar terbaru, materi pilihan, dan jadi yang
                    pertama tahu saat kelas baru dibuka.
                </p>
            </div>

            @if ($errors->any())
                <div class="mt-8 rounded-xl border border-orange-600/30 bg-orange-50 px-5 py-4 text-sm text-orange-700">
                    Ada beberapa isian yang perlu diperbaiki. Silakan cek kembali field di bawah.
                </div>
            @endif

            <form method="POST" data-submit-once action="{{ route('komunitas.join') }}" class="mt-10 space-y-6 rounded-3xl border border-teal-900/10 bg-white/70 p-6 shadow-sm backdrop-blur sm:p-8">
                @csrf

                {{-- Honeypot: hidden from humans, tempting to bots --}}
                <div aria-hidden="true" style="position:absolute; left:-9999px; top:-9999px; width:1px; height:1px; overflow:hidden;">
                    <label>Website
                        <input type="text" name="website" tabindex="-1" autocomplete="off" value="">
                    </label>
                </div>

                <div>
                    <label for="name" class="block text-sm font-medium text-teal-800">Nama lengkap</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" autocomplete="name"
                           class="{{ $field }} @error('name') border border-red-400 @else border border-teal-900/15 @enderror"
                           placeholder="Nama sesuai identitas">
                    @error('name') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="phone" class="block text-sm font-medium text-teal-800">Nomor HP <span class="text-teal-800/50">(WhatsApp aktif)</span></label>
                    <input id="phone" name="phone" type="tel" inputmode="tel" value="{{ old('phone') }}" autocomplete="tel"
                           class="{{ $field }} @error('phone') border border-red-400 @else border border-teal-900/15 @enderror"
                           placeholder="0812xxxxxxx">
                    @error('phone') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-teal-800">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email"
                           class="{{ $field }} @error('email') border border-red-400 @else border border-teal-900/15 @enderror"
                           placeholder="kamu@email.com">
                    @error('email') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="birth_date" class="block text-sm font-medium text-teal-800">Tanggal lahir</label>
                    <input id="birth_date" name="birth_date" type="date" max="{{ now()->subDay()->toDateString() }}"
                           value="{{ old('birth_date') }}"
                           class="{{ $field }} @error('birth_date') border border-red-400 @else border border-teal-900/15 @enderror">
                    @error('birth_date') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-teal-800">Kata sandi <span class="text-teal-800/50">(minimal 8 karakter)</span></label>
                    <input id="password" name="password" type="password" autocomplete="new-password"
                           class="{{ $field }} @error('password') border border-red-400 @else border border-teal-900/15 @enderror"
                           placeholder="••••••••">
                    @error('password') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="tiktok_username" class="block text-sm font-medium text-teal-800">Akun TikTok <span class="text-teal-800/50">(opsional, tanpa @)</span></label>
                    <input id="tiktok_username" name="tiktok_username" type="text" value="{{ old('tiktok_username') }}"
                           class="{{ $field }} border border-teal-900/15" placeholder="username">
                    @error('tiktok_username') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div data-tiktok-dependents class="hidden space-y-6">
                    <div>
                        <label for="tiktok_followers" class="block text-sm font-medium text-teal-800">Jumlah followers TikTok</label>
                        <input id="tiktok_followers" name="tiktok_followers" type="number" min="0"
                               value="{{ old('tiktok_followers') }}"
                               class="{{ $field }} @error('tiktok_followers') border border-red-400 @else border border-teal-900/15 @enderror">
                        @error('tiktok_followers') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <span class="block text-sm font-medium text-teal-800">Sudah mulai affiliate TikTok?</span>
                        <div class="mt-1.5 flex gap-3">
                            <label class="cursor-pointer rounded-full border border-teal-900/15 px-5 py-2 text-sm font-medium text-teal-800 transition has-[:checked]:border-teal-600 has-[:checked]:bg-teal-700 has-[:checked]:text-white">
                                <input type="radio" name="has_started_affiliate" value="1" class="sr-only" @checked(old('has_started_affiliate') === '1')>
                                Sudah
                            </label>
                            <label class="cursor-pointer rounded-full border border-teal-900/15 px-5 py-2 text-sm font-medium text-teal-800 transition has-[:checked]:border-teal-600 has-[:checked]:bg-teal-700 has-[:checked]:text-white">
                                <input type="radio" name="has_started_affiliate" value="0" class="sr-only" @checked(old('has_started_affiliate') === '0')>
                                Belum
                            </label>
                        </div>
                        @error('has_started_affiliate') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div data-affiliate-dependents class="hidden space-y-6">
                        <div>
                            <label for="affiliate_level" class="block text-sm font-medium text-teal-800">Level affiliate</label>
                            <select id="affiliate_level" name="affiliate_level"
                                    class="{{ $field }} @error('affiliate_level') border border-red-400 @else border border-teal-900/15 @enderror">
                                <option value="">Pilih level…</option>
                                @for ($level = 0; $level <= 8; $level++)
                                    <option value="{{ $level }}" @selected((string) old('affiliate_level') === (string) $level)>{{ $level }}</option>
                                @endfor
                            </select>
                            @error('affiliate_level') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="affiliate_gmv_range" class="block text-sm font-medium text-teal-800">GMV affiliate TikTok</label>
                            <select id="affiliate_gmv_range" name="affiliate_gmv_range"
                                    class="{{ $field }} @error('affiliate_gmv_range') border border-red-400 @else border border-teal-900/15 @enderror">
                                <option value="">Pilih rentang…</option>
                                @foreach ($gmvLabels as $value => $label)
                                    <option value="{{ $value }}" @selected(old('affiliate_gmv_range') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('affiliate_gmv_range') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                <div>
                    <label for="motivation" class="block text-sm font-medium text-teal-800">Apa alasanmu ingin gabung komunitas ini?</label>
                    <textarea id="motivation" name="motivation" rows="3"
                              class="{{ $field }} @error('motivation') border border-red-400 @else border border-teal-900/15 @enderror"
                              placeholder="Ceritakan alasanmu…">{{ old('motivation') }}</textarea>
                    @error('motivation') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="referral_source" class="block text-sm font-medium text-teal-800">Tahu komunitas ini dari mana?</label>
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

                <div class="pt-2">
                    <button type="submit"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-orange-500 px-7 py-3.5 text-sm font-semibold text-white shadow-md transition hover:bg-orange-600 hover:shadow-lg sm:w-auto">
                        Gabung Sekarang
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 10h12M11 5l5 5-5 5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                </div>
            </form>

            <p class="mt-6 text-center text-sm text-teal-800/60">
                Sudah punya akun? <a href="{{ route('member.login') }}" class="font-semibold text-teal-700 hover:text-orange-600">Masuk di sini</a>
            </p>
        </div>
    </section>

</x-layouts.public>
