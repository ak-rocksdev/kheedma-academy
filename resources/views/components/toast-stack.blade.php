{{-- Toast landing zone, rendered once by the public layout. Controllers
     flash one-shot notices with:
         return back()->with('toast', ['type' => 'success', 'message' => '...']);
     Types: success | error | warning | info (see <x-toast>). --}}
<div id="kh-toasts" aria-live="polite"
     class="pointer-events-none fixed inset-x-4 top-20 z-[70] flex flex-col items-center gap-2 sm:inset-x-auto sm:right-6 sm:top-24 sm:items-end">
    @if (session('toast'))
        <x-toast :type="session('toast')['type'] ?? 'success'" :message="session('toast')['message'] ?? ''" />
    @endif
</div>
