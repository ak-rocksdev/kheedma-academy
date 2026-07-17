{{-- Isi menu akun: tujuan sesuai peran + Keluar. Dipakai dropdown header
     (desktop) dan drop-up bottom nav (mobile). --}}
@if (auth()->user()->hasAnyRole(['admin', 'mentor']))
    <a href="{{ url('/admin') }}" class="flex items-center gap-2.5 rounded-lg px-3.5 py-2 text-sm text-teal-800 transition hover:bg-sand-50 hover:text-orange-600">
        <svg class="h-4 w-4 shrink-0 text-teal-700/70" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/></svg>
        Panel Admin
    </a>
@else
    <a href="{{ route('member.area') }}" class="flex items-center gap-2.5 rounded-lg px-3.5 py-2 text-sm text-teal-800 transition hover:bg-sand-50 hover:text-orange-600">
        <svg class="h-4 w-4 shrink-0 text-teal-700/70" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="3.5"/><path d="M5 19.5c1.2-3 3.8-4.5 7-4.5s5.8 1.5 7 4.5" stroke-linecap="round"/></svg>
        Akun Saya
    </a>
    <a href="{{ route('member.area', ['bagian' => 'kelas']) }}" class="flex items-center gap-2.5 rounded-lg px-3.5 py-2 text-sm text-teal-800 transition hover:bg-sand-50 hover:text-orange-600">
        <svg class="h-4 w-4 shrink-0 text-teal-700/70" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m12 4.5 9 4-9 4-9-4 9-4Z" stroke-linejoin="round"/><path d="M6.5 10.5v4.2c0 1.5 2.5 3 5.5 3s5.5-1.5 5.5-3v-4.2M21 8.5V14" stroke-linecap="round"/></svg>
        Kelas Saya
    </a>
@endif
<div class="my-1 border-t border-teal-900/10"></div>
<form method="POST" action="{{ route('member.logout') }}">
    @csrf
    <button type="submit" class="flex w-full items-center gap-2.5 rounded-lg px-3.5 py-2 text-left text-sm font-semibold text-orange-700 transition hover:bg-orange-500/10">
        <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 20.5H6a2 2 0 0 1-2-2v-13a2 2 0 0 1 2-2h3" stroke-linecap="round"/><path d="m15.5 16.5 4.5-4.5-4.5-4.5M20 12H9.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Keluar
    </button>
</form>
