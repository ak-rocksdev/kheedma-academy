@props(['old' => null])

{{-- Interactive social-follow nudge: CTA buttons open TikTok/Instagram in a
     new tab and reveal a check mark once clicked (app.js); the pill answer
     below is the actual `followed_socials` field. --}}
<div>
    <span class="block text-sm font-medium text-teal-800">Sudah follow sosial media kami?</span>

    <div class="mt-2 grid gap-3 sm:grid-cols-2">
        <a href="https://www.tiktok.com/@kheedmaacademy" target="_blank" rel="noopener" data-social-cta
           class="flex items-center justify-between gap-3 rounded-2xl border border-teal-900/15 bg-white px-5 py-3.5 text-sm font-semibold text-teal-800 transition hover:border-teal-600/40 hover:text-orange-600">
            <span>
                TikTok
                <span class="block text-xs font-normal text-teal-800/70">@kheedmaacademy</span>
            </span>
            <span class="flex items-center gap-2">
                <span data-social-check class="hidden text-teal-600">✓</span>
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M7 13 13 7M7 7h6v6" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </span>
        </a>
        <a href="https://www.instagram.com/kheedmaacademy" target="_blank" rel="noopener" data-social-cta
           class="flex items-center justify-between gap-3 rounded-2xl border border-teal-900/15 bg-white px-5 py-3.5 text-sm font-semibold text-teal-800 transition hover:border-teal-600/40 hover:text-orange-600">
            <span>
                Instagram
                <span class="block text-xs font-normal text-teal-800/70">@kheedmaacademy</span>
            </span>
            <span class="flex items-center gap-2">
                <span data-social-check class="hidden text-teal-600">✓</span>
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M7 13 13 7M7 7h6v6" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </span>
        </a>
    </div>

    <div class="mt-3 flex gap-3">
        <label class="cursor-pointer rounded-full border border-teal-900/15 px-5 py-2 text-sm font-medium text-teal-800 transition has-[:checked]:border-teal-600 has-[:checked]:bg-teal-700 has-[:checked]:text-white">
            <input type="radio" name="followed_socials" value="1" class="sr-only" @checked($old === '1')>
            Ya, sudah follow
        </label>
        <label class="cursor-pointer rounded-full border border-teal-900/15 px-5 py-2 text-sm font-medium text-teal-800 transition has-[:checked]:border-teal-600 has-[:checked]:bg-teal-700 has-[:checked]:text-white">
            <input type="radio" name="followed_socials" value="0" class="sr-only" @checked($old === '0')>
            Belum
        </label>
    </div>
    <p data-follow-nudge class="hidden mt-2 text-xs text-orange-700">Follow dulu yuk, klik tombol di atas, lalu tandai "Ya, sudah follow".</p>
    @error('followed_socials') <p data-server-error-for="followed_socials" class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
</div>
