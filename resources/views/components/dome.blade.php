@props(['variant' => 'teal'])

{{--
    Kheedma supergraphic — interlocking arch/dome motif (GSM).
    Symbolises collaboration and an inclusive, protective ecosystem.
    The dome (kubah) = "growing together"; maps naturally onto an academy.
--}}
<svg {{ $attributes->merge(['class' => 'block', 'aria-hidden' => 'true']) }}
     viewBox="0 0 240 140" fill="none" xmlns="http://www.w3.org/2000/svg">
    {{-- back arch --}}
    <path d="M30 140 V72 a40 40 0 0 1 80 0 V140"
          stroke="currentColor" stroke-width="3"
          class="{{ $variant === 'orange' ? 'text-orange-500' : 'text-teal-300' }}" />
    {{-- front arch, interlocking --}}
    <path d="M130 140 V72 a40 40 0 0 1 80 0 V140"
          stroke="currentColor" stroke-width="3"
          class="{{ $variant === 'orange' ? 'text-orange-500' : 'text-teal-300' }}" />
    {{-- centre keystone arch, accent --}}
    <path d="M80 140 V72 a40 40 0 0 1 80 0 V140"
          stroke="currentColor" stroke-width="3.5"
          class="{{ $variant === 'orange' ? 'text-teal-600' : 'text-orange-500' }}" />
</svg>
