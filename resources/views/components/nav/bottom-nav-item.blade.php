{{-- Satu tujuan pada bottom nav: ikon (slot) + label, state aktif oranye. --}}
@props(['href', 'label', 'active' => false])

<a href="{{ $href }}"
   @if ($active) aria-current="page" @endif
   @class([
       'flex flex-1 flex-col items-center gap-1 py-2.5 text-[0.65rem] font-semibold',
       'text-orange-600' => $active,
       'text-teal-800/60' => ! $active,
   ])>
    {{ $slot }}
    {{ $label }}
</a>
