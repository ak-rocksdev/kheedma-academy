@props([
    'title' => null,
    'description' => 'Kheedma Academy — kami membimbing pemula tumbuh menjadi affiliate marketer yang amanah dan profesional. Belajar dengan niat ibadah, berkembang dengan keberkahan.',
])

<!DOCTYPE html>
<html lang="id" class="antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' — Kheedma Academy' : 'Kheedma Academy — Serving with Purpose, Growing with Barakah' }}</title>
    <meta name="description" content="{{ $description }}">
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen flex flex-col">

    {{-- ───────────────────────── Header ───────────────────────── --}}
    <header class="sticky top-0 z-40 border-b border-teal-900/5 bg-sand-50/85 backdrop-blur">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
            <a href="{{ url('/') }}" class="group flex items-center" aria-label="Kheedma Academy — beranda">
                <x-logo variant="horizontal" class="h-9 transition-transform group-hover:-translate-y-0.5 md:h-10" />
            </a>

            <nav class="hidden items-center gap-9 text-sm font-medium text-teal-800 md:flex">
                <a href="{{ url('/') }}" class="transition hover:text-orange-600">Beranda</a>
                <a href="{{ url('/#tentang') }}" class="transition hover:text-orange-600">Tentang</a>
                <a href="{{ url('/#program') }}" class="transition hover:text-orange-600">Program</a>
                <a href="{{ url('/#nilai') }}" class="transition hover:text-orange-600">Nilai</a>
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
                        secara strategis, profesional, dan berlandaskan nilai — halal &amp; thayyib.
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

</body>
</html>
