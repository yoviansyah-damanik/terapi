<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="{{ Vite::image('logo-icon.png') }}" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
    <title>@yield('code', 'Error') — {{ config('app.alias_name', config('app.name')) }}</title>
    @fluxAppearance
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body x-data x-init="if (localStorage.getItem('darkMode') === 'true') document.documentElement.classList.add('dark')"
    class="min-h-screen flex flex-col items-center justify-between bg-zinc-50 dark:bg-primary-dark-950 font-body antialiased px-4 py-8">

    {{-- Branding atas --}}
    <div class="flex flex-col items-center gap-3">
        <img src="{{ Vite::image('logo-icon.png') }}" alt="Logo" class="w-10 h-10">
        <div class="text-center">
            <p class="text-xs font-semibold uppercase tracking-widest text-primary-600 dark:text-primary-400">
                {{ config('app.alias_name', config('app.name')) }}
            </p>
            <p class="text-xs text-zinc-400 dark:text-primary-dark-500">
                {{ config('app.name') }}
            </p>
        </div>
    </div>

    {{-- Konten error --}}
    <div class="w-full max-w-md text-center space-y-6">

        {{-- Kode error --}}
        <p class="text-9xl font-bold text-primary-100 dark:text-primary-950 select-none leading-none">
            @yield('code', '?')
        </p>

        {{-- Judul & pesan --}}
        <div class="space-y-2">
            <h1 class="text-2xl font-semibold text-zinc-800 dark:text-primary-dark-100">
                @yield('title', 'Terjadi Kesalahan')
            </h1>
            <p class="text-sm text-zinc-500 dark:text-primary-dark-400">
                @yield('message')
            </p>
        </div>

        {{-- Aksi --}}
        <div class="flex items-center justify-center gap-3 pt-2">
            <a href="{{ url()->previous() !== url()->current() ? url()->previous() : '/' }}"
                class="inline-flex items-center gap-1.5 text-sm text-zinc-500 dark:text-primary-dark-400 hover:text-zinc-800 dark:hover:text-primary-dark-100 transition-colors">
                ← Kembali
            </a>
            <span class="text-zinc-300 dark:text-primary-dark-700">|</span>
            <a href="{{ url('/') }}"
                class="inline-flex items-center gap-1.5 text-sm font-medium text-primary-600 dark:text-primary-400 hover:underline">
                Beranda
            </a>
        </div>
    </div>

    {{-- Copyright bawah --}}
    <div class="text-center space-y-0.5">
        <p class="text-xs text-zinc-400 dark:text-primary-dark-600">
            &copy; {{ date('Y') }} {{ config('hospital.name', config('app.name')) }}. Hak cipta dilindungi.
        </p>
    </div>

    @fluxScripts
</body>

</html>
