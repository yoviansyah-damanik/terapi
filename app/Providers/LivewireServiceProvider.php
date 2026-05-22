<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Component;

class LivewireServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    /**
     * Mendaftarkan macro toast untuk semua Livewire component
     */
    public function boot(): void
    {
        Component::macro('toastSuccess', function (string $message, ?string $title = null, int $duration = 5000): void {
            $this->toast('success', $message, $title, $duration);
        });

        Component::macro('toastError', function (string $message, ?string $title = null, int $duration = 5000): void {
            $this->toast('error', $message, $title, $duration);
        });

        Component::macro('toastWarning', function (string $message, ?string $title = null, int $duration = 5000): void {
            $this->toast('warning', $message, $title, $duration);
        });

        Component::macro('toastInfo', function (string $message, ?string $title = null, int $duration = 5000): void {
            $this->toast('info', $message, $title, $duration);
        });

        Component::macro('toast', function (string $type, string $message, ?string $title = null, int $duration = 5000, bool $dismissible = true): void {
            $this->dispatch('toast',
                type: $type,
                message: $message,
                title: $title,
                duration: $duration,
                dismissible: $dismissible,
            );
        });
    }
}
