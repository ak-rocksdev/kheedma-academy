<x-layouts.public title="Masuk"
    description="Masuk ke akun Kheedma Academy kamu.">

    @php
        $field = 'mt-1.5 w-full rounded-lg bg-white px-3.5 py-2.5 text-sm text-teal-900 outline-none transition placeholder:text-teal-900/30 focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20';
    @endphp

    <section class="relative overflow-hidden">
        <div class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full bg-teal-100 blur-3xl"></div>

        <div class="relative mx-auto max-w-md px-6 py-16 sm:py-20">
            <div class="text-center">
                <x-logo variant="stacked" class="mx-auto h-20" />
                <p class="mt-8 font-display text-xs uppercase tracking-[0.3em] text-orange-600">Akun</p>
                <h1 class="mt-3 text-3xl font-bold leading-tight text-teal-900">Masuk.</h1>
            </div>

            @if (session('reset'))
                <div class="mt-8 rounded-xl border border-teal-600/30 bg-teal-50 px-5 py-4 text-sm text-teal-800">
                    {{ session('reset') }}
                </div>
            @endif

            <form method="POST" action="{{ route('member.login.store') }}" class="mt-10 space-y-6 rounded-3xl border border-teal-900/10 bg-white/70 p-6 shadow-sm backdrop-blur sm:p-8">
                @csrf

                <div>
                    <label for="email" class="block text-sm font-medium text-teal-800">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required
                           class="{{ $field }} @error('email') border border-red-400 @else border border-teal-900/15 @enderror"
                           placeholder="kamu@email.com">
                    @error('email') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <span class="block text-sm font-medium text-teal-800">PIN <span class="text-teal-800/50">(6 digit)</span></span>
                    <x-pin-input name="password" autocomplete="current-password" :invalid="$errors->has('password')" />
                </div>

                <div class="flex items-center justify-between text-sm">
                    <label class="flex items-center gap-2 text-teal-800/70">
                        <input type="checkbox" name="remember" value="1" class="rounded border-teal-900/20 text-teal-700 focus:ring-teal-600/30">
                        Ingat saya
                    </label>
                    <a href="{{ route('member.password.request') }}" class="font-medium text-teal-700 hover:text-orange-600">Lupa PIN?</a>
                </div>

                <button type="submit"
                        class="inline-flex w-full items-center justify-center rounded-full bg-teal-700 px-7 py-3 text-sm font-semibold text-white shadow-md transition hover:bg-teal-800">
                    Masuk
                </button>
            </form>

            <p class="mt-6 text-center text-sm text-teal-800/60">
                Belum punya akun? <a href="{{ route('komunitas') }}" class="font-semibold text-teal-700 hover:text-orange-600">Gabung komunitas</a>
            </p>
        </div>
    </section>

</x-layouts.public>
