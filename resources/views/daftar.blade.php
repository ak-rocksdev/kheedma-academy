<x-layouts.public title="Pendaftaran"
    description="Formulir pendaftaran Kheedma Academy Cohort 1. Mulai perjalananmu menjadi affiliate marketer yang amanah dan profesional.">

    @php
        $field = 'mt-1.5 w-full rounded-lg bg-white px-3.5 py-2.5 text-sm text-teal-900 outline-none transition placeholder:text-teal-900/30 focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20';
    @endphp

    <section class="relative overflow-hidden">
        <div class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full bg-teal-100 blur-3xl"></div>

        <div class="relative mx-auto max-w-2xl px-6 py-16 sm:py-20">
            <div class="text-center">
                <x-logo variant="stacked" class="mx-auto h-20" />
                <p class="mt-8 font-display text-xs uppercase tracking-[0.3em] text-orange-600">Pendaftaran</p>
                <h1 class="mt-3 text-3xl font-bold leading-tight text-teal-900 sm:text-4xl">Mulai perjalananmu.</h1>
                <p class="mx-auto mt-4 max-w-lg text-base leading-relaxed text-teal-800/80">
                    Isi data di bawah ini. Setelah mendaftar, kamu akan menerima tugas pra-seleksi
                    sebagai langkah menunjukkan kesungguhan. Cohort 1 gratis, tempat terbatas.
                </p>
            </div>

            @if ($errors->any())
                <div class="mt-8 rounded-xl border border-orange-600/30 bg-orange-50 px-5 py-4 text-sm text-orange-700">
                    Ada beberapa isian yang perlu diperbaiki. Silakan cek kembali field di bawah.
                </div>
            @endif

            <form method="POST" action="{{ route('daftar.store') }}" class="mt-10 space-y-6 rounded-3xl border border-teal-900/10 bg-white/70 p-6 shadow-sm backdrop-blur sm:p-8">
                @csrf

                {{-- Honeypot: hidden from humans (inline style so it never depends on CSS build), tempting to bots --}}
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

                <div class="grid gap-6 sm:grid-cols-2">
                    <div>
                        <label for="province_code" class="block text-sm font-medium text-teal-800">Provinsi</label>
                        <select id="province_code" name="province_code"
                                class="{{ $field }} @error('province_code') border border-red-400 @else border border-teal-900/15 @enderror">
                            <option value="">Pilih provinsi…</option>
                            @foreach ($provinces as $province)
                                <option value="{{ $province->code }}" @selected(old('province_code') === $province->code)>{{ $province->name }}</option>
                            @endforeach
                        </select>
                        @error('province_code') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="city_code" class="block text-sm font-medium text-teal-800">Kota/Kabupaten</label>
                        <select id="city_code" name="city_code" data-old="{{ old('city_code') }}" disabled
                                class="{{ $field }} disabled:cursor-not-allowed disabled:bg-sand-100 disabled:text-teal-900/40 @error('city_code') border border-red-400 @else border border-teal-900/15 @enderror">
                            <option value="">Pilih provinsi dulu…</option>
                        </select>
                        @error('city_code') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid gap-6 sm:grid-cols-2">
                    <div>
                        <label for="tiktok_username" class="block text-sm font-medium text-teal-800">Akun TikTok <span class="text-teal-800/50">(opsional)</span></label>
                        <input id="tiktok_username" name="tiktok_username" type="text" value="{{ old('tiktok_username') }}"
                               class="{{ $field }} border border-teal-900/15" placeholder="@username">
                        @error('tiktok_username') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="instagram_username" class="block text-sm font-medium text-teal-800">Akun Instagram <span class="text-teal-800/50">(opsional)</span></label>
                        <input id="instagram_username" name="instagram_username" type="text" value="{{ old('instagram_username') }}"
                               class="{{ $field }} border border-teal-900/15" placeholder="@username">
                        @error('instagram_username') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

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

            <p class="mt-6 text-center text-xs text-teal-800/50">
                Dengan mendaftar, kamu setuju mengikuti masa observasi terbimbing yang dipantau.
            </p>
        </div>
    </section>

    <script>
        (function () {
            const province = document.getElementById('province_code');
            const city = document.getElementById('city_code');
            const citiesBase = @json(url('/daftar/cities'));

            async function loadCities(code, selected = '') {
                city.innerHTML = '<option value="">Memuat…</option>';
                city.disabled = true;
                if (!code) {
                    city.innerHTML = '<option value="">Pilih provinsi dulu…</option>';
                    return;
                }
                try {
                    const res = await fetch(`${citiesBase}/${code}`, { headers: { 'Accept': 'application/json' } });
                    const list = await res.json();
                    city.innerHTML = '<option value="">Pilih kota/kabupaten…</option>'
                        + list.map(c => `<option value="${c.code}"${c.code === selected ? ' selected' : ''}>${c.name}</option>`).join('');
                    city.disabled = false;
                } catch (e) {
                    city.innerHTML = '<option value="">Gagal memuat, pilih ulang provinsi</option>';
                }
            }

            province.addEventListener('change', () => loadCities(province.value));

            // Repopulate after a validation redirect (old input).
            if (province.value) {
                loadCities(province.value, city.dataset.old || '');
            }
        })();
    </script>

</x-layouts.public>
