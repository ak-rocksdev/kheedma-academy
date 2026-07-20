<x-layouts.public title="Lupa PIN"
    description="Atur ulang PIN akun Kheedma Academy kamu.">

    @php
        $field = 'mt-1.5 w-full rounded-lg bg-white px-3.5 py-2.5 text-sm text-teal-900 outline-none transition placeholder:text-teal-900/30 focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20';
    @endphp

    <section class="relative overflow-hidden">
        <div class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full bg-teal-100 blur-3xl"></div>

        <div class="relative mx-auto max-w-md px-6 py-16 sm:py-20">
            <div class="text-center">
                <x-logo variant="stacked" class="mx-auto h-20" />
                <p class="mt-8 font-display text-xs uppercase tracking-[0.3em] text-orange-600">Akun</p>
                <h1 class="mt-3 text-3xl font-bold leading-tight text-teal-900">Lupa PIN?</h1>
                <p class="mx-auto mt-4 text-sm leading-relaxed text-teal-800/70">
                    Masukkan emailmu. Kami kirimkan tautan untuk mengatur ulang PIN.
                </p>
            </div>

            @if (session('status'))
                <div class="mt-8 rounded-xl border border-teal-600/30 bg-teal-50 px-5 py-4 text-sm text-teal-800">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('member.password.email') }}" class="mt-10 space-y-6 rounded-3xl border border-teal-900/10 bg-white/70 p-6 shadow-sm backdrop-blur sm:p-8">
                @csrf

                <div>
                    <label for="email" class="block text-sm font-medium text-teal-800">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required
                           class="{{ $field }} @error('email') border border-red-400 @else border border-teal-900/15 @enderror"
                           placeholder="kamu@email.com">
                    @error('email') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <button type="submit"
                        class="inline-flex w-full items-center justify-center rounded-full bg-teal-700 px-7 py-3 text-sm font-semibold text-white shadow-md transition hover:bg-teal-800">
                    Kirim Tautan
                </button>
            </form>

            <p class="mt-6 text-center text-sm text-teal-800/70">
                Ingat PIN-mu? <a href="{{ route('member.login') }}" class="font-semibold text-teal-700 hover:text-orange-600">Masuk</a>
            </p>
        </div>
    </section>

</x-layouts.public>
