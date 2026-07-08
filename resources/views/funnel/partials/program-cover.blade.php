{{-- Class cover. Real thumbnail when uploaded; otherwise a deterministic
     generative brand cover: teal gradient (picked by slug hash) + the
     supergraphic at low opacity + an arch (dome) monogram — level for
     affiliate classes, the name's initial for general ones.
     Caller passes: $program, optional $locked (bool), optional $class (sizing). --}}
@php
    $coverGradients = [
        'from-teal-600 to-teal-900',
        'from-teal-700 to-teal-950',
        'from-[#01534f] to-[#05312b]',
        'from-teal-800 via-teal-900 to-teal-950',
    ];
    $coverGradient = $coverGradients[abs(crc32($program->slug)) % count($coverGradients)];
    $coverMonogram = $program->isAffiliate()
        ? 'L'.$program->level
        : mb_strtoupper(mb_substr(trim($program->name), 0, 1));
    $locked = $locked ?? false;
@endphp
<div class="relative overflow-hidden bg-gradient-to-br {{ $coverGradient }} {{ $locked ? 'saturate-[0.4]' : '' }} {{ $class ?? '' }}">
    @if ($program->thumbnail_path)
        <img src="{{ asset('storage/'.$program->thumbnail_path) }}" alt="" class="absolute inset-0 h-full w-full object-cover" loading="lazy">
    @else
        <div class="supergraphic absolute inset-0 opacity-15"></div>
        <div class="relative flex h-full items-center justify-center">
            <div class="flex h-16 w-14 items-end justify-center rounded-t-full rounded-b-lg bg-white/10 pb-2.5 ring-1 ring-white/25 backdrop-blur-[1px]">
                <span class="font-display text-base font-bold tracking-wide text-white">{{ $coverMonogram }}</span>
            </div>
        </div>
    @endif
</div>
