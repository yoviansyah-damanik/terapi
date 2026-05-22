<div x-data="{ url: '' }" @open-viewer.window="url = $event.detail; $flux.modal('viewer-modal').show()">
    <x-organisms.modal :closable="false" :dismissible="false" name="viewer-modal" maxWidth="md" title="">
        <div class="flex items-center justify-between px-4 py-3 bg-zinc-900 border-b border-zinc-800 shrink-0">
            <div class="flex items-center gap-2 text-white font-medium text-sm">
                <flux:icon.eye class="size-4" />
                <span>PACS Web Viewer</span>
            </div>
            <x-atoms.button icon="x-mark" variant="ghost" size="sm" class="text-zinc-400 hover:text-white"
                x-on:click="url = ''; $flux.modal('viewer-modal').close()" />
        </div>

        <div class="flex-1 w-full bg-black relative">
            <template x-if="url">
                <iframe :src="url" class="absolute inset-0 w-full h-full border-none"
                    allowfullscreen></iframe>
            </template>
            <template x-if="!url">
                <div class="flex items-center justify-center h-full text-zinc-500">
                    Memuat Viewer...
                </div>
            </template>
        </div>
    
    </x-organisms.modal>
</div>
