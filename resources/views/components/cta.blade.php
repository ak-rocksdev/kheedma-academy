@props(['href' => '#', 'label' => 'Daftar Sekarang'])

{{-- Primary call-to-action: orange pill with forward arrow. --}}
<a href="{{ $href }}"
   {{ $attributes->merge(['class' => 'inline-flex items-center gap-2 rounded-full bg-orange-500 px-7 py-3.5 text-sm font-semibold text-white shadow-md transition hover:bg-orange-600 hover:shadow-lg']) }}>
    {{ $label }}
    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M4 10h12M11 5l5 5-5 5" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
</a>
