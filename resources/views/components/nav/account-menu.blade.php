{{-- Dropdown akun di header desktop: Akun Saya / Panel Admin + Keluar.
     Di mobile perannya digantikan item "Akun" pada bottom nav. --}}
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
        <x-nav.account-menu-items />
    </div>
</details>
