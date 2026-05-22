@props(['position' => 'top-right'])

@php
    $positionClasses = match ($position) {
        'top-left' => 'top-4 left-4',
        'top-right' => 'top-4 right-4',
        'top-center' => 'top-4 left-1/2 -translate-x-1/2',
        'bottom-left' => 'bottom-4 left-4',
        'bottom-right' => 'bottom-4 right-4',
        'bottom-center' => 'bottom-4 left-1/2 -translate-x-1/2',
        default => 'top-4 right-4',
    };
@endphp

<div x-data="toastNotification()" x-on:toast.window="addToast($event.detail)"
    class="fixed z-[9999] flex flex-col gap-3 {{ $positionClasses }}" style="pointer-events: none;">
    <template x-for="toast in toasts" :key="toast.id">
        <div x-show="toast.visible" x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-x-8" x-transition:enter-end="opacity-100 translate-x-0"
            x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 translate-x-0"
            x-transition:leave-end="opacity-0 translate-x-8"
            class="flex items-start gap-3 p-4 rounded-lg shadow-lg min-w-80 max-w-md"
            :class="getToastClasses(toast.type)" style="pointer-events: auto;">
            <div class="flex-shrink-0" x-html="getIcon(toast.type)"></div>

            <div class="flex-1 min-w-0">
                <p x-show="toast.title" class="text-sm font-semibold" x-text="toast.title"></p>
                <p class="text-sm" :class="toast.title ? 'mt-1 opacity-90' : ''" x-text="toast.message"></p>
            </div>

            <button x-show="toast.dismissible !== false" @click="removeToast(toast.id)"
                class="flex-shrink-0 p-1 transition-opacity rounded opacity-70 hover:opacity-100">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </template>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('toastNotification', () => ({
            toasts: [],

            addToast(raw) {
                // Livewire dispatch bisa mengirim data langsung atau terbungkus array
                const detail = (Array.isArray(raw) ? raw[0] : raw) || {};
                const id = Date.now() + Math.random();
                const toast = {
                    id,
                    type: detail.type || 'info',
                    title: detail.title || null,
                    message: detail.message || '',
                    duration: detail.duration ?? 3000,
                    dismissible: detail.dismissible ?? true,
                    visible: false,
                };

                this.toasts.push(toast);

                setTimeout(() => {
                    const t = this.toasts.find(t => t.id === id);
                    if (t) t.visible = true;
                }, 10);

                if (toast.duration > 0) {
                    setTimeout(() => this.removeToast(id), toast.duration);
                }
            },

            removeToast(id) {
                const toast = this.toasts.find(t => t.id === id);
                if (toast) {
                    toast.visible = false;
                    setTimeout(() => {
                        this.toasts = this.toasts.filter(t => t.id !== id);
                    }, 200);
                }
            },

            getToastClasses(type) {
                const classes = {
                    success: 'bg-primary-100 text-primary-500 dark:bg-primary-600/20',
                    error: 'bg-red-100 text-red-500 dark:bg-red-600/20',
                    warning: 'bg-secondary-100 text-secondary-700 dark:bg-secondary-600/20',
                    info: 'bg-blue-100 text-blue-500 dark:bg-blue-600/20',
                };
                return classes[type] || classes.info;
            },

            getIcon(type) {
                const icons = {
                    success: `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>`,
                    error: `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>`,
                    warning: `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>`,
                    info: `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>`,
                };
                return icons[type] || icons.info;
            }
        }));
    });
</script>
