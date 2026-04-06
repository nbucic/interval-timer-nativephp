<div class="flex flex-col h-full">

    <div class="flex items-center justify-between px-4 py-4">
        <h1 class="text-xl font-bold text-white">Settings</h1>
        <button
            wire:click="save"
            class="bg-blue-600 hover:bg-blue-500 text-white text-sm font-semibold px-4 py-2 rounded-xl transition-colors"
        >
            @if($saved) Saved ✓ @else Save @endif
        </button>
    </div>

    <div class="flex-1 overflow-y-auto px-4 pb-8 space-y-6">

        {{-- ── Sound ──────────────────────────────────────────────────── --}}
        <section>
            <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2 px-1">Sound</h2>
            <div class="bg-gray-900 rounded-2xl border border-white/5 divide-y divide-white/5">

                {{-- Sound mode --}}
                <div class="px-4 py-4">
                    <p class="text-white text-sm font-medium mb-3">Sound Mode</p>
                    <div class="grid grid-cols-2 gap-3">
                        <button
                            wire:click="$set('soundMode', 'beep')"
                            class="flex flex-col items-center gap-2 p-4 rounded-xl border-2 transition-all
                                   {{ $soundMode === 'beep' ? 'border-blue-500 bg-blue-500/10' : 'border-white/10 bg-gray-800' }}"
                        >
                            <svg class="w-6 h-6 {{ $soundMode === 'beep' ? 'text-blue-400' : 'text-gray-500' }}"
                                 fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M19.114 5.636a9 9 0 0 1 0 12.728M16.463 8.288a5.25 5.25 0 0 1 0 7.424M6.75 8.25l4.72-4.72a.75.75 0 0 1 1.28.53v15.88a.75.75 0 0 1-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.009 9.009 0 0 1 2.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75Z"/>
                            </svg>
                            <span class="text-sm font-semibold {{ $soundMode === 'beep' ? 'text-blue-400' : 'text-gray-400' }}">
                                Beep
                            </span>
                            <span class="text-[10px] text-gray-600 text-center">Web Audio API</span>
                        </button>

                        <button
                            wire:click="$set('soundMode', 'voice')"
                            class="flex flex-col items-center gap-2 p-4 rounded-xl border-2 transition-all
                                   {{ $soundMode === 'voice' ? 'border-blue-500 bg-blue-500/10' : 'border-white/10 bg-gray-800' }}"
                        >
                            <svg class="w-6 h-6 {{ $soundMode === 'voice' ? 'text-blue-400' : 'text-gray-500' }}"
                                 fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M12 18.75a6 6 0 0 0 6-6v-1.5m-6 7.5a6 6 0 0 1-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 0 1-3-3V4.5a3 3 0 1 1 6 0v8.25a3 3 0 0 1-3 3Z"/>
                            </svg>
                            <span class="text-sm font-semibold {{ $soundMode === 'voice' ? 'text-blue-400' : 'text-gray-400' }}">
                                Voice
                            </span>
                            <span class="text-[10px] text-gray-600 text-center">Android TTS</span>
                        </button>
                    </div>
                </div>

                {{-- Volume --}}
                <div class="px-4 py-4">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-white text-sm font-medium">Volume</p>
                        <span class="text-gray-400 text-sm font-mono">{{ round($volume * 100) }}%</span>
                    </div>
                    <input
                        type="range"
                        wire:model.live="volume"
                        min="0" max="1" step="0.05"
                        class="w-full accent-blue-500 h-1.5"
                    >
                </div>

            </div>
        </section>

        {{-- ── Defaults ────────────────────────────────────────────────── --}}
        <section>
            <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2 px-1">
                Program Defaults
            </h2>
            <p class="text-gray-600 text-xs px-1 mb-3">Applied to new programs on creation.</p>

            <div class="bg-gray-900 rounded-2xl border border-white/5 divide-y divide-white/5">

                {{-- Default beep lead-in --}}
                <div class="flex items-center justify-between px-4 py-3.5">
                    <div>
                        <p class="text-white text-sm font-medium">Beep Lead-in</p>
                        <p class="text-gray-500 text-xs mt-0.5">Countdown seconds before segment ends</p>
                    </div>
                    <div class="flex gap-2">
                        <button
                            wire:click="$set('defaultBeepLeadIn', 3)"
                            class="px-3 py-1.5 rounded-lg text-sm font-semibold transition-colors
                                   {{ $defaultBeepLeadIn === 3 ? 'bg-blue-600 text-white' : 'bg-gray-800 text-gray-400' }}"
                        >3s</button>
                        <button
                            wire:click="$set('defaultBeepLeadIn', 5)"
                            class="px-3 py-1.5 rounded-lg text-sm font-semibold transition-colors
                                   {{ $defaultBeepLeadIn === 5 ? 'bg-blue-600 text-white' : 'bg-gray-800 text-gray-400' }}"
                        >5s</button>
                    </div>
                </div>

                {{-- Default end sound --}}
                <div class="flex items-center justify-between px-4 py-3.5">
                    <div>
                        <p class="text-white text-sm font-medium">End Sound</p>
                        <p class="text-gray-500 text-xs mt-0.5">Plays once on program completion</p>
                    </div>
                    <div class="flex gap-2">
                        <button
                            wire:click="$set('defaultEndSound', 'triple')"
                            class="px-3 py-1.5 rounded-lg text-sm font-semibold transition-colors
                                   {{ $defaultEndSound === 'triple' ? 'bg-blue-600 text-white' : 'bg-gray-800 text-gray-400' }}"
                        >Triple</button>
                        <button
                            wire:click="$set('defaultEndSound', 'chime')"
                            class="px-3 py-1.5 rounded-lg text-sm font-semibold transition-colors
                                   {{ $defaultEndSound === 'chime' ? 'bg-blue-600 text-white' : 'bg-gray-800 text-gray-400' }}"
                        >Chime</button>
                    </div>
                </div>

            </div>
        </section>

        {{-- ── Display ─────────────────────────────────────────────────── --}}
        <section>
            <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2 px-1">Display</h2>
            <div class="bg-gray-900 rounded-2xl border border-white/5">
                <div class="flex items-center justify-between px-4 py-3.5">
                    <div>
                        <p class="text-white text-sm font-medium">Keep Screen On</p>
                        <p class="text-gray-500 text-xs mt-0.5">Prevent sleep during active timer</p>
                    </div>
                    <button
                        wire:click="$set('keepScreenOn', {{ $keepScreenOn ? 'false' : 'true' }})"
                        class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors
                               {{ $keepScreenOn ? 'bg-blue-600' : 'bg-gray-700' }}"
                    >
                        <span
                            class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform
                                   {{ $keepScreenOn ? 'translate-x-6' : 'translate-x-1' }}"
                        ></span>
                    </button>
                </div>
            </div>
        </section>

        {{-- ── About ───────────────────────────────────────────────────── --}}
        <section>
            <div class="bg-gray-900 rounded-2xl border border-white/5 px-4 py-4">
                <p class="text-gray-500 text-xs">
                    Interval Timer · Built with NativePHP Mobile {{ app('nativephp.version', '3.x') }}
                    + Laravel {{ app()->version() }} + PHP 8.5
                </p>
            </div>
        </section>

    </div>
</div>
