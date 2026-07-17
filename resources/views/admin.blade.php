<!DOCTYPE html>
<html lang="id" class="antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Kheedma Academy · Admin</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    {{-- Staff only: the SPA shell is also served to guests (it shows its own
         login), so gate the key to shrink the exposure surface. The key is
         additionally referrer-restricted in Google Cloud Console. --}}
    @auth
        <meta name="google-maps-key" content="{{ config('services.google_maps.key') }}">
    @endauth
    @fonts
    @vite(['resources/js/admin/main.js'])
</head>
<body>
    <div id="admin-app"></div>
</body>
</html>
