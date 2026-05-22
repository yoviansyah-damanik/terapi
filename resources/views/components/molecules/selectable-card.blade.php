@props([
    'value',
    'model',
    'label',
    'description' => null,
    'icon' => null,
])

<label
    class="group flex items-center gap-3 px-4 py-3 text-sm cursor-pointer transition-all w-full
    rounded-xl border
    hover:shadow-sm hover:-translate-y-0.5
    has-[:checked]:border-primary-400 has-[:checked]:bg-primary-50 dark:has-[:checked]:bg-primary-900/20 has-[:checked]:text-primary-800 dark:has-[:checked]:text-primary-200
    border-zinc-200 dark:border-primary-dark-700 bg-white dark:bg-primary-dark-800 text-zinc-700 dark:text-primary-dark-300 hover:border-zinc-300 dark:hover:border-primary-dark-600">
    
    <div class="relative flex items-center">
        <input type="checkbox" wire:model.live="{{ $model }}" value="{{ $value }}"
            class="peer w-4 h-4 border-zinc-300 text-primary-600 rounded bg-white dark:bg-primary-dark-700 focus:ring-primary-500 dark:border-primary-dark-600" />
    </div>
    
    @if($icon)
        <flux:icon name="{{ $icon }}" variant="outline" class="w-5 h-5 opacity-70 group-has-[:checked]:opacity-100 group-has-[:checked]:text-primary-600 dark:group-has-[:checked]:text-primary-400" />
    @endif
    
    <div class="flex-1 min-w-0">
        <span class="block font-semibold group-has-[:checked]:text-primary-700 dark:group-has-[:checked]:text-primary-300">{{ $label }}</span>
        @if ($description)
            <span class="block text-xs opacity-75 mt-0.5 group-has-[:checked]:text-primary-600 dark:group-has-[:checked]:text-primary-400">{{ $description }}</span>
        @endif
    </div>
</label>
