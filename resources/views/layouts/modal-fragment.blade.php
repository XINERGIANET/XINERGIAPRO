<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full antialiased">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Xinergia PRO' }}</title>
    @php
        $viteEntries = ['resources/js/app.js'];
        $manifestPath = public_path('build/manifest.json');
        if (file_exists($manifestPath)) {
            $manifest = json_decode(file_get_contents($manifestPath), true) ?: [];
            if (isset($manifest['resources/css/app.css'])) {
                $viteEntries = ['resources/css/app.css', 'resources/js/app.js'];
            }
        }
    @endphp
    @vite($viteEntries)
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css">
</head>

<body class="min-h-full bg-[#F4F6FA] font-sans text-slate-800">
    @yield('content')
</body>

</html>
