@props([
    'variant' => 'horizontal',  // horizontal | stacked
    'tone' => 'default',         // default (for light bg) | ondark (for dark bg)
    'alt' => 'Kheedma Academy',
])

@php
    $map = [
        'horizontal' => [
            'default' => ['file' => 'kheedma-academy-horizontal.png', 'w' => 1408, 'h' => 492],
            'ondark'  => ['file' => 'kheedma-academy-horizontal-ondark.png', 'w' => 1408, 'h' => 492],
        ],
        'stacked' => [
            'default' => ['file' => 'kheedma-academy-stacked.png', 'w' => 1180, 'h' => 918],
            'ondark'  => ['file' => 'kheedma-academy-stacked-ondark.png', 'w' => 1180, 'h' => 918],
        ],
    ];
    $cfg = $map[$variant][$tone] ?? $map['horizontal']['default'];
@endphp

<img
    src="{{ asset('images/'.$cfg['file']) }}"
    width="{{ $cfg['w'] }}"
    height="{{ $cfg['h'] }}"
    alt="{{ $alt }}"
    {{ $attributes->merge(['class' => 'w-auto select-none']) }}
/>
