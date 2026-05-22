@props(['menus' => config('sidebar')])

<flux:sidebar sticky collapsible
    class="z-50 p-0! relative [:where(&)]:w-72 flex flex-col h-screen border-r  text-white shadow-xl border-0! bg-gradient-to-b from-primary-dark-700 from-0% to-primary-700 to-100% dark:from-primary-dark-900 dark:to-primary-dark-800">

    {{-- Sidebar Header --}}
    <flux:sidebar.header class="p-0!">
        <a href="{{ route('home') }}" wire:navigate
            class="flex items-center justify-start gap-4 bg-primary-950 dark:bg-primary-dark-950 p-4 w-full">
            <img src="{{ Vite::image('logo-icon.png') }}" class="h-12" alt="Logo" />
            <div class="flex flex-col">
                <div class="font-bold text-secondary-500 tracking-widest">
                    {{ config('app.alias_name') }}
                </div>
                <div class="text-xs">
                    {{ config('app.name') }}
                </div>
            </div>
        </a>
    </flux:sidebar.header>

    {{-- Sidebar Body --}}
    <div class="py-2 px-4 flex flex-col justify-between h-full sidebar-scrollbar overflow-auto">
        <flux:sidebar.nav variant="outline" class="flex-1 h-full overflow-auto">
            @foreach ($menus as $menu)
                @if (isset($menu['children']) && count($menu['children']) > 0)
                    @php
                        $expandedRoutes = isset($menu['expanded'])
                            ? (is_array($menu['expanded'])
                                ? $menu['expanded']
                                : [$menu['expanded']])
                            : [];
                        $isExpanded = false;
                        foreach ($expandedRoutes as $routePattern) {
                            if (request()->is($routePattern) || request()->routeIs($routePattern)) {
                                $isExpanded = true;
                                break;
                            }
                        }
                    @endphp
                    <flux:sidebar.group icon="{{ $menu['icon'] }}" heading="{{ $menu['title'] }}" expandable
                        :expanded="$isExpanded">
                        @foreach ($menu['children'] as $child)
                            @if (isset($child['children']) && count($child['children']) > 0)
                                @php
                                    $childExpandedRoutes = isset($child['expanded'])
                                        ? (is_array($child['expanded'])
                                            ? $child['expanded']
                                            : [$child['expanded']])
                                        : [];
                                    $childIsExpanded = false;
                                    foreach ($childExpandedRoutes as $routePattern) {
                                        if (request()->is($routePattern) || request()->routeIs($routePattern)) {
                                            $childIsExpanded = true;
                                            break;
                                        }
                                    }
                                @endphp
                                {{-- Render grandchild --}}
                                <flux:sidebar.group heading="{{ $child['title'] }}" icon="{{ $child['icon'] ?? '' }}"
                                    expandable :expanded="$childIsExpanded">
                                    @foreach ($child['children'] as $grandchild)
                                        @if (isset($grandchild['children']) && count($grandchild['children']) > 0)
                                            @php
                                                $grandchildExpandedRoutes = isset($grandchild['expanded'])
                                                    ? (is_array($grandchild['expanded'])
                                                        ? $grandchild['expanded']
                                                        : [$grandchild['expanded']])
                                                    : [];
                                                $grandchildIsExpanded = false;
                                                foreach ($grandchildExpandedRoutes as $routePattern) {
                                                    if (request()->is($routePattern) || request()->routeIs($routePattern)) {
                                                        $grandchildIsExpanded = true;
                                                        break;
                                                    }
                                                }
                                            @endphp
                                            {{-- Render great-grandchild sub-group --}}
                                            <flux:sidebar.group heading="{{ $grandchild['title'] }}" icon="{{ $grandchild['icon'] ?? '' }}"
                                                expandable :expanded="$grandchildIsExpanded">
                                                @foreach ($grandchild['children'] as $greatGrandchild)
                                                    @php
                                                        $greatGrandchildActive = isset($greatGrandchild['active_match'])
                                                            ? request()->routeIs($greatGrandchild['active_match'])
                                                            : request()->routeIs($greatGrandchild['route'] ?? '');
                                                    @endphp
                                                    <x-sidebar.nav-item wire:navigate
                                                        href="{{ \Illuminate\Support\Facades\Route::has($greatGrandchild['route'] ?? '') ? route($greatGrandchild['route']) : '#' }}"
                                                        icon="{{ $greatGrandchild['icon'] ?? '' }}" :active="$greatGrandchildActive">
                                                        {{ $greatGrandchild['title'] }}
                                                    </x-sidebar.nav-item>
                                                @endforeach
                                            </flux:sidebar.group>
                                        @else
                                            @php
                                                $grandchildActive = isset($grandchild['active_match'])
                                                    ? request()->routeIs($grandchild['active_match'])
                                                    : request()->routeIs($grandchild['route'] ?? '');
                                            @endphp
                                            <x-sidebar.nav-item wire:navigate
                                                href="{{ \Illuminate\Support\Facades\Route::has($grandchild['route'] ?? '') ? route($grandchild['route']) : '#' }}"
                                                icon="{{ $grandchild['icon'] ?? '' }}" :active="$grandchildActive">
                                                {{ $grandchild['title'] }}
                                            </x-sidebar.nav-item>
                                        @endif
                                    @endforeach
                                </flux:sidebar.group>
                            @else
                                @php
                                    $childActiveMatch = $child['active_match'] ?? ($child['route'] ?? '');
                                    $childActive = false;
                                    $matches = is_array($childActiveMatch) ? $childActiveMatch : [$childActiveMatch];
                                    foreach ($matches as $match) {
                                        if (request()->routeIs($match) || request()->is($match)) {
                                            $childActive = true;
                                            break;
                                        }
                                    }
                                @endphp
                                <x-sidebar.nav-item wire:navigate
                                    href="{{ \Illuminate\Support\Facades\Route::has($child['route'] ?? '') ? route($child['route']) : '#' }}"
                                    icon="{{ $child['icon'] ?? '' }}" :active="$childActive">
                                    {{ $child['title'] }}
                                </x-sidebar.nav-item>
                            @endif
                        @endforeach
                    </flux:sidebar.group>
                @else
                    @php
                        $isActive = false;
                        if (isset($menu['active_match'])) {
                            $matchPattern = is_array($menu['active_match'])
                                ? $menu['active_match']
                                : [$menu['active_match']];
                            foreach ($matchPattern as $pattern) {
                                if (request()->routeIs($pattern)) {
                                    $isActive = true;
                                    break;
                                }
                            }
                        } else {
                            $isActive = request()->routeIs($menu['route'] ?? '');
                        }
                    @endphp
                    <x-sidebar.nav-item wire:navigate
                        href="{{ \Illuminate\Support\Facades\Route::has($menu['route'] ?? '') ? route($menu['route']) : '#' }}"
                        icon="{{ $menu['icon'] ?? '' }}" :active="$isActive">
                        {{ $menu['title'] }}
                    </x-sidebar.nav-item>
                @endif
            @endforeach
        </flux:sidebar.nav>
    </div>
</flux:sidebar>
