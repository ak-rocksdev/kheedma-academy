{{-- Dropdown akun di header: Akun Saya / Panel Admin + Keluar.
     compact = versi mobile (avatar saja, target sentuh lebih besar). --}}
@props(['compact' => false])

<details class="account-menu relative">
    <summary class="flex cursor-pointer list-none items-center gap-1.5 transition hover:text-orange-600 [&::-webkit-details-marker]:hidden">
        <span @class([
            'flex items-center justify-center rounded-full bg-teal-700 font-bold text-white',
            'h-8 w-8 text-xs' => $compact,
            'h-6 w-6 text-[0.65rem]' => ! $compact,
        ])>
            {{ strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}
        </span>
        @unless ($compact)
            {{ auth()->user()->name }}
            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M6 8l4 4 4-4" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        @endunless
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
