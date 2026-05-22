@props([
    'name',
    'title' => 'Konfirmasi',
    'message' => 'Apakah Anda yakin ingin melanjutkan?',
    'confirmText' => 'Ya, Lanjutkan',
    'cancelText' => 'Batal',
    'variant' => 'danger',
    'icon' => 'exclamation-triangle',
])

<x-organisms.modal :name="$name" maxWidth="md" title="">
    <div class="space-y-6">
        <div class="flex items-start gap-4">
            <div @class([
                'p-3 rounded-full',
                'bg-red-100 text-red-600 dark:bg-red-900/30' => $variant === 'danger',
                'bg-amber-100 text-amber-600 dark:bg-amber-900/30' =>
                    $variant === 'warning',
                'bg-blue-100 text-blue-600 dark:bg-blue-900/30' => $variant === 'info',
            ])>
                <flux:icon :name="$icon" class="size-6" />
            </div>

            <div class="flex-1">
                <flux:heading size="lg">{{ $title }}</flux:heading>
                <flux:subheading class="mt-2">{{ $message }}</flux:subheading>
            </div>
        </div>

        
        <x-slot:footer>
            <div class="flex justify-end gap-3">
            <flux:button variant="ghost" x-on:click="$flux.modal('{{ $name }}').close()">
                {{ $cancelText }}
            </flux:button>
            {{ $slot }}
        </div>
    </x-slot:footer>
    </div>
    </x-organisms.modal>
