{{-- Isi menu akun: tujuan sesuai peran + Keluar. Dipakai dropdown header
     (desktop) dan drop-up bottom nav (mobile). --}}
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
