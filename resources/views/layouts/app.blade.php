<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-gray-950">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#030712">
    {{-- Prevent text selection / context menus for native feel --}}
    <meta name="mobile-web-app-capable" content="yes">
    <title>{{ $title ?? config('app.name', 'Interval Timer') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full flex flex-col bg-gray-950 text-white overflow-hidden select-none">

    {{-- ── EDGE Top Bar ─────────────────────────────────────────────────── --}}
    {{--
        Shows the program name when the timer is running, the app name otherwise.
        Livewire components broadcast 'topbar-title' events to update this.
    --}}
    <header
        class="safe-top flex-none"
        x-data="{ title: '{{ config('app.name') }}' }"
        @topbar-title.window="title = $event.detail.title"
    >
        <div class="h-14 flex items-center justify-center px-4 border-b border-white/5">
            <span
                class="text-base font-semibold tracking-wide text-white/90 truncate max-w-xs"
                x-text="title"
            ></span>
        </div>
    </header>

    {{-- ── Page content ─────────────────────────────────────────────────── --}}
    <main class="flex-1 overflow-y-auto overflow-x-hidden">
        {{ $slot }}
    </main>

    {{-- ── EDGE Bottom Nav ──────────────────────────────────────────────── --}}
    <nav
        class="safe-bottom flex-none border-t border-white/5 bg-gray-950/95 backdrop-blur"
        x-data="{ tab: '{{ request()->routeIs('timer*') ? 'timer' : (request()->routeIs('settings*') ? 'settings' : 'library') }}' }"
        @popstate.window="tab = location.pathname.startsWith('/timer') ? 'timer' : (location.pathname === '/settings' ? 'settings' : 'library')"
    >
        <div class="h-16 grid grid-cols-3">

            {{-- Library --}}
            <a href="/"
               @click="tab = 'library'"
               class="flex flex-col items-center justify-center gap-1 transition-colors"
               :class="tab === 'library' ? 'text-blue-400' : 'text-gray-500 hover:text-gray-300'"
               wire:navigate
            >
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M3.75 9.776c.112-.017.227-.026.344-.026h15.812c.117 0 .232.009.344.026m-16.5 0a2.25 2.25 0 0 0-1.883 2.542l.857 6a2.25 2.25 0 0 0 2.227 1.932H19.05a2.25 2.25 0 0 0 2.227-1.932l.857-6a2.25 2.25 0 0 0-1.883-2.542m-16.5 0V6A2.25 2.25 0 0 1 6 3.75h3.879a1.5 1.5 0 0 1 1.06.44l2.122 2.12a1.5 1.5 0 0 0 1.06.44H18A2.25 2.25 0 0 1 20.25 9v.776"/>
                </svg>
                <span class="text-[10px] font-medium">Library</span>
            </a>

            {{-- Timer --}}
            <a href="/timer"
               @click="tab = 'timer'"
               class="flex flex-col items-center justify-center gap-1 transition-colors"
               :class="tab === 'timer' ? 'text-blue-400' : 'text-gray-500 hover:text-gray-300'"
               wire:navigate
            >
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                </svg>
                <span class="text-[10px] font-medium">Timer</span>
            </a>

            {{-- Settings --}}
            <a href="/settings"
               @click="tab = 'settings'"
               class="flex flex-col items-center justify-center gap-1 transition-colors"
               :class="tab === 'settings' ? 'text-blue-400' : 'text-gray-500 hover:text-gray-300'"
               wire:navigate
            >
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                </svg>
                <span class="text-[10px] font-medium">Settings</span>
            </a>

        </div>
    </nav>

    @livewireScripts
</body>
</html>
