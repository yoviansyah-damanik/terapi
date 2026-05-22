<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="{{ Vite::image('logo-icon.png') }}" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

    <title>{{ ($title ?? config('app.name')) . ' - ' . config('hospital.name', '') }}</title>

    @fluxAppearance
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body x-data x-init="if (localStorage.getItem('darkMode') === 'true') {
    document.documentElement.classList.add('dark');
}" class="min-h-screen bg-background dark:bg-primary-dark-900">
    {{ $slot }}

    <x-toast position="top-right" />

    @fluxScripts
</body>

@include('components.app-version', ['class' => 'fixed bottom-3 right-3'])

</html>
