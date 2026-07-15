{{-- Bar navigasi bawah untuk mobile: selalu terlihat, ramah jangkauan jempol,
     aman terhadap home-indicator iOS. Isi bar dirakit pemakainya dari
     <x-nav.bottom-nav-item>. --}}
@props(['label'])

<nav {{ $attributes->merge(['class' => 'fixed inset-x-0 bottom-0 z-40 border-t border-teal-900/10 bg-white/95 backdrop-blur md:hidden']) }}
     style="padding-bottom: env(safe-area-inset-bottom)"
     aria-label="{{ $label }}">
    <div class="mx-auto flex max-w-2xl">
        {{ $slot }}
    </div>
</nav>
