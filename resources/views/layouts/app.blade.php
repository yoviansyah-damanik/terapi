{{--
    Jika butuh bantuan dalam pengembangan ataupun ingin mentraktir kopi, silahkan hubungi saya.
    Yoviansyah Rizki Pratama
    +62 812 2277 8197
    yoviansyahrizkypratama@gmail.com
--}}

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="{{ Vite::image('logo-icon.png') }}" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

    <title>{{ ($title ?? config('app.alias_name')) . ' - ' . config('app.alias_name', '') }}</title>

    @fluxAppearance
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')

    <!-- PDF.js Library -->
    <script src="https://cdn.jsdelivr.net/npm/pdfjs-dist@4.2.67/build/pdf.min.mjs" type="module"></script>
</head>

<body x-data x-init="if (localStorage.getItem('darkMode') === 'true') {
    document.documentElement.classList.add('dark');
}"
    class="bg-zinc-100 bg-linear-to-b flex font-body antialiased from-white from-0% to-primary-50 to-100% dark:from-primary-dark-950 dark:to-primary-dark-900">
    {{-- Sidebar (Segmen 2) --}}
    <x-organisms.sidebar />

    {{-- Content Wrapper (Segmen 1: Header, Main, Footer) --}}
    <div class="flex flex-1 w-screen lg:w-full flex-col h-screen">
        {{-- Header (Fixed Top) --}}
        <x-organisms.header />

        {{-- Main Content (Scrollable) --}}
        <main class="flex-1 overflow-y-auto pb-24">
            <div class="container mx-auto px-4 lg:px-6 py-6">
                {{ $slot }}
            </div>
        </main>
    </div>

    @include('components.app-version', ['class' => 'bottom-3 right-3 fixed'])

    <x-toast position="top-right" />

    @fluxScripts
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    @stack('scripts')


</body>

</html>
