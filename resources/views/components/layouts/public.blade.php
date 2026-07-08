@props([
    'title' => null,
    'description' => 'Kheedma Academy membimbing pemula tumbuh menjadi affiliate marketer yang amanah dan profesional. Belajar dengan niat ibadah, berkembang dengan keberkahan.',
])

<!DOCTYPE html>
<html lang="id" class="antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php($pageTitle = $title ? $title.' · Kheedma Academy' : 'Kheedma Academy · Serving with Purpose, Growing with Barakah')
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $description }}">
    <link rel="canonical" href="{{ url()->current() }}">

    {{-- Favicons --}}
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">
    <meta name="theme-color" content="#05312b">

    {{-- Open Graph / Twitter --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Kheedma Academy">
    <meta property="og:locale" content="id_ID">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:image" content="{{ asset('images/kheedma-academy-og.jpg') }}">
    <meta property="og:image:width" content="1660">
    <meta property="og:image:height" content="1640">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $pageTitle }}">
    <meta name="twitter:description" content="{{ $description }}">
    <meta name="twitter:image" content="{{ asset('images/kheedma-academy-og.jpg') }}">

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen flex flex-col">

    {{-- ───────────────────────── Header ───────────────────────── --}}
    <header class="sticky top-0 z-40 border-b border-teal-900/5 bg-sand-50/85 backdrop-blur">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
            <a href="{{ url('/') }}" class="group flex items-center" aria-label="Beranda Kheedma Academy">
                <x-logo variant="horizontal" class="h-9 transition-transform group-hover:-translate-y-0.5 md:h-10" />
            </a>

            <nav class="hidden items-center gap-9 text-sm font-medium text-teal-800 md:flex">
                <a href="{{ url('/') }}" class="transition hover:text-orange-600">Beranda</a>
                <a href="{{ url('/#tentang') }}" class="transition hover:text-orange-600">Tentang</a>
                <a href="{{ url('/#program') }}" class="transition hover:text-orange-600">Program</a>
                <a href="{{ url('/#nilai') }}" class="transition hover:text-orange-600">Nilai</a>
                @auth
                    <details class="account-menu relative">
                        <summary class="flex cursor-pointer list-none items-center gap-1.5 transition hover:text-orange-600 [&::-webkit-details-marker]:hidden">
                            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-teal-700 text-[0.65rem] font-bold text-white">
                                {{ strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}
                            </span>
                            {{ auth()->user()->name }}
                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M6 8l4 4 4-4" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </summary>
                        <div class="absolute right-0 z-50 mt-3 w-52 rounded-xl border border-teal-900/10 bg-white p-1.5 shadow-lg">
                            @if (auth()->user()->hasAnyRole(['admin', 'mentor']))
                                <a href="{{ url('/admin') }}" class="block rounded-lg px-3.5 py-2 text-sm text-teal-800 transition hover:bg-sand-50 hover:text-orange-600">Panel Admin</a>
                            @else
                                <a href="{{ route('member.area') }}" class="block rounded-lg px-3.5 py-2 text-sm text-teal-800 transition hover:bg-sand-50 hover:text-orange-600">Akun Saya</a>
                            @endif
                            <form method="POST" action="{{ route('member.logout') }}">
                                @csrf
                                <button type="submit" class="block w-full rounded-lg px-3.5 py-2 text-left text-sm text-teal-800 transition hover:bg-sand-50 hover:text-orange-600">
                                    Keluar
                                </button>
                            </form>
                        </div>
                    </details>
                @else
                    <a href="{{ route('member.login') }}" class="transition hover:text-orange-600">Masuk</a>
                @endauth
            </nav>

            <a href="{{ url('/daftar') }}"
               class="inline-flex items-center rounded-full bg-orange-500 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-orange-600 hover:shadow">
                Daftar
            </a>
        </div>
    </header>

    {{-- ───────────────────────── Content ───────────────────────── --}}
    <main class="flex-1">
        {{ $slot }}
    </main>

    {{-- ───────────────────────── Footer ───────────────────────── --}}
    <footer class="relative mt-24 overflow-hidden bg-teal-900 text-sand-100">
        <div class="supergraphic pointer-events-none absolute inset-x-0 bottom-0 h-40 opacity-10"></div>
        <div class="relative mx-auto max-w-6xl px-6 py-16">
            <div class="flex flex-col gap-10 md:flex-row md:items-start md:justify-between">
                <div class="max-w-sm">
                    <x-logo variant="horizontal" tone="ondark" class="h-10" />
                    <p class="mt-5 text-sm leading-relaxed text-sand-100/70">
                        Serving with Purpose, Growing with Barakah. Kami membimbing umat tumbuh
                        secara strategis, profesional, dan berlandaskan nilai halal &amp; thayyib.
                    </p>
                </div>
                <div class="grid grid-cols-2 gap-10 text-sm">
                    <div>
                        <p class="font-display text-xs uppercase tracking-[0.25em] text-orange-400">Jelajahi</p>
                        <ul class="mt-4 space-y-2.5 text-sand-100/80">
                            <li><a href="{{ url('/#program') }}" class="transition hover:text-white">Program</a></li>
                            <li><a href="{{ url('/#nilai') }}" class="transition hover:text-white">Nilai</a></li>
                            <li><a href="{{ url('/daftar') }}" class="transition hover:text-white">Pendaftaran</a></li>
                        </ul>
                    </div>
                    <div>
                        <p class="font-display text-xs uppercase tracking-[0.25em] text-orange-400">Kheedma</p>
                        <ul class="mt-4 space-y-2.5 text-sand-100/80">
                            <li><span class="text-sand-100/50">Agency &amp; Academy</span></li>
                            <li><span class="text-sand-100/50">Muslim Growth Partner</span></li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="mt-12 border-t border-white/10 pt-6 text-xs text-sand-100/50">
                © {{ date('Y') }} Kheedma Academy. Khidmat · Amanah · Itqan · Barakah.
            </div>
        </div>
    </footer>

    @include('funnel.partials.lock-modal')

</body>
</html>
