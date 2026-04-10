@php use App\Enum\StateMachine; @endphp
{{--
    TimerScreen — full-screen timer UI.

    JS ticker: Alpine polls wire.tick() every 1 000 ms when the timer is active.
    Audio:     timerAudio Alpine component (defined in app.js) listens to
               Livewire events: playBeep, playPauseBeep, playEndSound.
    Beep lead-in + cooldown breathing glow driven by CSS + Alpine state.
--}}
<div
    class="flex flex-col h-full"
    x-data="timerAudio"
>
    {{-- ── No program loaded ───────────────────────────────────────────── --}}
    @if(! $programId)
        <div class="flex-1 flex flex-col items-center justify-center text-center px-6">
            <div class="w-20 h-20 rounded-full bg-gray-900 flex items-center justify-center mb-5">
                <svg class="w-10 h-10 text-gray-600" fill="none" stroke="currentColor" stroke-width="1.5"
                     viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                </svg>
            </div>
            <p class="text-gray-400 font-medium text-lg">No program selected</p>
            <p class="text-gray-600 text-sm mt-1">Go to Library and tap a program to start</p>
            <a href="/" wire:navigate
               class="mt-6 bg-blue-600 hover:bg-blue-500 text-white font-semibold px-6 py-3 rounded-2xl transition-colors">
                Open Library
            </a>
        </div>
    @else

        {{-- ── Active timer ────────────────────────────────────────────────── --}}
        {{-- Phase progress bar --}}
        <div class="h-1 bg-gray-800 flex-none">
            @if($phaseIndex < collect($phases ?? [])->count() || true)
                <div
                    class="h-full transition-all duration-1000 ease-linear rounded-r-full"
                    style="background: {{ $phaseColor }}; width: {{ $totalRemaining > 0 && $state !== 'completed' ? '100%' : '0%' }}"
                ></div>
            @endif
        </div>

        {{-- Phase label strip --}}
        <div
            class="flex items-center justify-center px-4 pt-4 pb-2"
        >
            <div class="flex items-center gap-2">
            <span
                class="w-3 h-3 rounded-full shrink-0"
                style="background: {{ $state->value === 'PAUSE' ? '#6b7280' : ($state->value === 'COOLDOWN' ? '#f97316' : $phaseColor) }}"
            ></span>
                <span class="text-gray-300 font-semibold text-[96px]">
                {{ $this->segmentLabel() }}
            </span>
                @if($this->repLabel())
                    <span class="text-gray-600 text-sm">— Rep {{ $this->repLabel() }}</span>
                @endif
            </div>
        </div>

        {{-- ── BIG countdown ───────────────────────────────────────────────── --}}
        <div class="flex-1 flex flex-col items-center justify-center px-6">

            {{-- Main digit --}}
            <div
                class="text-[96px] font-black leading-none tabular-nums transition-colors duration-500
                   {{ $state->value === 'COMPLETED' ? 'text-green-400' : '' }}"
                :class="{
                'cooldown-glow': '{{ $state->value }}' === 'COOLDOWN',
                'opacity-50':    '{{ $state->value }}' === 'PAUSED',
            }"
            >
            {{ $state->value === 'COMPLETED' ? '✓' : $this->formattedRemaining() }}
            </div>

            {{-- Completed message --}}
            @if($state->value === 'COMPLETED')
                <p class="text-green-300 font-semibold text-xl mt-3 animate-pulse">
                    Program Complete!
                </p>
            @endif

            {{-- Cooldown breathing prompt --}}
            @if($state->value === 'COOLDOWN')
                <p class="text-orange-300/70 text-sm font-medium mt-3 tracking-wide animate-pulse">
                    Take deep breaths
                </p>
            @endif

            {{-- Paused label --}}
            @if($state->value === 'PAUSED')
                <p class="text-gray-500 text-base mt-3">Paused</p>
            @endif

            {{-- Total remaining --}}
            @if(in_array($state->value, ['RUNNING', 'PAUSE', 'COOLDOWN', 'PAUSED']) && $totalRemaining > 0)
                <p class="text-gray-600 text-sm mt-5">
                    {{ $this->formattedTotal() }} total remaining
                </p>
            @endif

        </div>

        {{-- ── Controls ───────────────────────────────────────────────────── --}}
        <div class="flex-none px-6 pb-6 space-y-3">

            {{-- Primary action --}}
            @if($state === StateMachine::idle)
                <button
                    wire:click="start"
                    class="w-full bg-blue-600 hover:bg-blue-500 active:bg-blue-700 text-white font-bold
                   text-xl py-5 rounded-3xl transition-colors shadow-lg shadow-blue-900/40"
                >
                    Start
                </button>

            @elseif($state === StateMachine::paused)
                <button
                    wire:click="resume"
                    class="w-full bg-blue-600 hover:bg-blue-500 active:bg-blue-700 text-white font-bold
                   text-xl py-5 rounded-3xl transition-colors"
                >
                    Resume
                </button>

            @elseif($state === StateMachine::completed)
                <button
                    wire:click="restart"
                    class="w-full bg-green-600 hover:bg-green-500 active:bg-green-700 text-white font-bold
                   text-xl py-5 rounded-3xl transition-colors"
                >
                    Run Again
                </button>

            @else
                <button
                    wire:click="pause"
                    class="w-full bg-yellow-600 hover:bg-yellow-500 active:bg-yellow-700 text-white font-bold
                   text-xl py-5 rounded-3xl transition-colors"
                >
                    Pause
                </button>
            @endif

            {{-- Secondary actions row --}}
            <div class="flex gap-3">
                <a
                    href="/programs/{{ $programId }}/edit"
                    wire:navigate
                    class="flex-1 flex items-center justify-center gap-2 bg-gray-900 hover:bg-gray-800
                       text-gray-400 hover:text-white font-semibold py-3.5 rounded-2xl transition-colors
                       border border-white/5"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/>
                    </svg>
                    Edit
                </a>

                @if(! in_array($state->value, ['IDLE', 'COMPLETED']))
                    <button
                        wire:click="discard"
                        wire:confirm="Discard this run? No history will be saved."
                        class="flex-1 flex items-center justify-center gap-2 bg-gray-900 hover:bg-gray-800
                       text-gray-600 hover:text-red-400 font-semibold py-3.5 rounded-2xl transition-colors
                       border border-white/5"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M6 18 18 6M6 6l12 12"/>
                        </svg>
                        Discard
                    </button>
                @endif
            </div>

        </div>

    @endif
</div>
