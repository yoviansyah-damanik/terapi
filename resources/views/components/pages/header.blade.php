@props([
    'title' => 'Judul Halaman',
    'description' => null,
    'actions' => null,
])

<div class="flex flex-col gap-4 mb-6 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h1 class="text-2xl font-bold text-zinc-900 dark:text-primary-dark-100">{{ $title }}</h1>
        <p class="text-sm text-zinc-600 dark:text-primary-dark-400">{{ $description }}</p>
    </div>

    {{ $actions }}
</div>
