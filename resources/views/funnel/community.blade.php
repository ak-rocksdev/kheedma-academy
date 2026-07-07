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
                <h1 class="mt-3 text-3xl font-bold leading-tight text-teal-900 sm:text-4xl">Kheedma Affiliate Community.</h1>
                <p class="mx-auto mt-4 max-w-lg text-base leading-relaxed text-teal-800/80">
                    Assalamu'alaikum! Selamat datang di ekosistem inklusif bagi kreator, affiliator,
                    dan pejuang halal-growth yang ingin berkembang bersama, selaras dengan
                    nilai-nilai Islami.
                </p>
            </div>

            <div class="mt-10 space-y-4">
                <div class="rounded-3xl border border-teal-900/10 bg-white/70 p-6 shadow-sm backdrop-blur sm:p-8">
                    <h2 class="text-lg font-bold text-teal-900">Komunitas belajar, bukan sekadar kelas jualan.</h2>
                    <p class="mt-2 text-sm leading-relaxed text-teal-800/80">
                        Kami mendampingimu membangun habit dan rutinitas harian sebagai affiliator
                        yang solid, konsisten, dan berkelanjutan.
                    </p>
                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <div class="rounded-2xl bg-sand-50 p-4">
                            <p class="text-sm font-semibold text-teal-900">Mentor pribadi, gratis</p>
                            <p class="mt-1 text-sm leading-relaxed text-teal-800/70">
                                Dedicated personal manager yang membimbing, membantu mengurai kendala
                                affiliate, dan menjaga konsistensi konten kreatifmu.
                            </p>
                        </div>
                        <div class="rounded-2xl bg-sand-50 p-4">
                            <p class="text-sm font-semibold text-teal-900">Akses komunitas, gratis</p>
                            <p class="mt-1 text-sm leading-relaxed text-teal-800/70">
                                Grup koordinasi tanpa biaya supaya kamu selalu up to date dengan
                                program strategis yang akan dijalankan ke depannya.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="rounded-3xl border border-teal-900/10 bg-white/70 p-6 shadow-sm backdrop-blur sm:p-8">
                    <h2 class="text-lg font-bold text-teal-900">Belajar daring dan luring.</h2>
                    <ul class="mt-3 space-y-1.5 text-sm leading-relaxed text-teal-800/80">
                        <li>Sesi Pagi Daring (Perempuan): 09.30 WIB</li>
                        <li>Sesi Siang Luring (Laki-laki): 13.30 WIB</li>
                        <li>Lokasi: Kantor Kheedma Indonesia, Pasar Kliwon, Surakarta, atau via Zoom/Google Meet</li>
                    </ul>
                    <p class="mt-4 text-sm font-semibold text-teal-900">Silabus program:</p>
                    <ol class="mt-2 list-decimal space-y-1 pl-5 text-sm leading-relaxed text-teal-800/80">
                        <li>Fondasi Dasar dan Teknis Awal Affiliate TikTok</li>
                        <li>Akselerasi Penjualan dan Strategi Scale Up</li>
                        <li>Optimalisasi Konten dan Iklan TikTok Affiliate</li>
                        <li>Membangun Personal Branding Digital</li>
                    </ol>
                </div>

                <div class="rounded-3xl border border-teal-900/10 bg-white/70 p-6 shadow-sm backdrop-blur sm:p-8">
                    <h2 class="text-lg font-bold text-teal-900">Komitmen dan etika belajar.</h2>
                    <p class="mt-2 text-sm leading-relaxed text-teal-800/80">Kami mencari rekan yang siap berkomitmen untuk:</p>
                    <ol class="mt-2 list-decimal space-y-1 pl-5 text-sm leading-relaxed text-teal-800/80">
                        <li>Alokasi waktu minimal 1 jam per hari untuk mempraktikkan materi dan menyelesaikan task.</li>
                        <li>Menjaga vibrasi positif, saling support antar anggota, dan membangun circle belajar yang sehat.</li>
                        <li>Saling menghargai dan menjaga etika, kepada sesama rekan belajar maupun mentor.</li>
                    </ol>
                    <p class="mt-4 rounded-2xl bg-sand-50 p-4 text-xs leading-relaxed text-teal-800/60">
                        Kami tidak menjanjikan keberhasilan instan atau target angka tertentu. Fokus
                        utama komunitas ini adalah membentuk mindset, kebiasaan produktif, dan
                        framework strategi agar kamu dapat mengelola profesi affiliator secara
                        efektif dan berjangka panjang.
                    </p>
                </div>
            </div>

            <p class="mt-10 text-center text-sm leading-relaxed text-teal-800/70">
                Isi formulir di bawah dengan lengkap dan valid agar kami dapat memprosesmu masuk
                ke ekosistem komunitas. Barakallahu fiikum.
            </p>

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
                    <span class="block text-sm font-medium text-teal-800">Jenis kelamin</span>
                    <div class="mt-1.5 flex gap-3">
                        <label class="cursor-pointer rounded-full border border-teal-900/15 px-5 py-2 text-sm font-medium text-teal-800 transition has-[:checked]:border-teal-600 has-[:checked]:bg-teal-700 has-[:checked]:text-white">
                            <input type="radio" name="gender" value="male" class="sr-only" @checked(old('gender') === 'male')>
                            Laki-laki
                        </label>
                        <label class="cursor-pointer rounded-full border border-teal-900/15 px-5 py-2 text-sm font-medium text-teal-800 transition has-[:checked]:border-teal-600 has-[:checked]:bg-teal-700 has-[:checked]:text-white">
                            <input type="radio" name="gender" value="female" class="sr-only" @checked(old('gender') === 'female')>
                            Perempuan
                        </label>
                    </div>
                    @error('gender') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
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

                <x-social-follow :old="old('followed_socials')" />

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
