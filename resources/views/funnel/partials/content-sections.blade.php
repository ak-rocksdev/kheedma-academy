@foreach ($sections as $section)
    <div class="rounded-3xl border border-teal-900/10 bg-white/70 p-6 shadow-sm backdrop-blur sm:p-8">
        @if ($section->heading)
            <h2 class="text-lg font-bold text-teal-900">{{ $section->heading }}</h2>
        @endif
        {{-- Body is sanitized on write (SectionBodySanitizer); raw output is safe. --}}
        <div @class(['kh-prose', 'mt-2' => $section->heading])>{!! $section->body !!}</div>
    </div>
@endforeach
