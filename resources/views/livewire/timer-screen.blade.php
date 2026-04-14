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
    {{-- ── No program loaded: history list ───────────────────────────── --}}
    @if(! $programId)
        <div class="flex-1 flex flex-col overflow-hidden">

            {{-- Header --}}
            <div class="flex items-center justify-between px-5 pt-5 pb-3 flex-none">
                <h2 class="text-white font-bold text-lg">Recent Runs</h2>
                <a href="/" wire:navigate
                   class="bg-blue-600 hover:bg-blue-500 text-white text-sm font-semibold px-4 py-2 rounded-xl transition-colors">
                    Open Library
                </a>
            </div>

            {{-- History list --}}
            @if(count($history) === 0)
                <div class="flex-1 flex flex-col items-center justify-center text-center px-6">
                    <div class="w-16 h-16 rounded-full bg-gray-900 flex items-center justify-center mb-4">
                        <svg class="w-8 h-8 text-gray-600" fill="none" stroke="currentColor" stroke-width="1.5"
                             viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                        </svg>
                    </div>
                    <p class="text-gray-500 text-sm">No runs yet — complete a program to see your history here.</p>
                </div>
            @else
                <div class="flex-1 overflow-y-auto px-4 pb-6 space-y-2">
                    @foreach($history as $entry)
                        @php
                            $mins = intdiv($entry['total_duration'], 60);
                            $secs = $entry['total_duration'] % 60;
                            $formattedDuration = sprintf('%d:%02d', $mins, $secs);
                            $formattedDate = \Carbon\Carbon::parse($entry['completed_at'])->diffForHumans();
                        @endphp

                        @if($entry['program_exists'])
                            <a href="/timer/{{ $entry['program_id'] }}" wire:navigate
                               class="flex items-center gap-3 bg-gray-900 hover:bg-gray-800 border border-white/5
                                      hover:border-white/10 rounded-2xl px-4 py-3.5 transition-colors group">
                                <div class="w-9 h-9 rounded-full bg-gray-800 group-hover:bg-gray-700 flex items-center
                                            justify-center flex-none transition-colors">
                                    <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor"
                                         stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 3l14 9-14 9V3z"/>
                                    </svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-white font-semibold text-sm truncate">{{ $entry['program_name'] }}</p>
                                    <p class="text-gray-500 text-xs mt-0.5">{{ $formattedDate }}</p>
                                </div>
                                <span class="text-gray-400 font-mono text-sm flex-none">{{ $formattedDuration }}</span>
                            </a>
                        @else
                            <div class="flex items-center gap-3 bg-gray-900/50 border border-white/5 rounded-2xl
                                        px-4 py-3.5 opacity-40 cursor-not-allowed">
                                <div class="w-9 h-9 rounded-full bg-gray-800 flex items-center justify-center flex-none">
                                    <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor"
                                         stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 3l14 9-14 9V3z"/>
                                    </svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-gray-500 font-semibold text-sm truncate">{{ $entry['program_name'] }}</p>
                                    <p class="text-gray-600 text-xs mt-0.5">{{ $formattedDate }} · deleted</p>
                                </div>
                                <span class="text-gray-600 font-mono text-sm flex-none">{{ $formattedDuration }}</span>
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif

        </div>
    @else

        {{-- ── Active timer ────────────────────────────────────────────────── --}}
        {{-- Phase strip — one proportional segment per phase --}}
        @php
            $totalDuration = collect($phases)->sum(function (array $p) {
                return ($p['duration'] * $p['repetitions'])
                     + ($p['pause'] * max(0, $p['repetitions'] - 1));
            });
            // Last phase has no cooldown counted; others include it
            foreach ($phases as $i => $p) {
                if ($i < count($phases) - 1) {
                    $totalDuration += $p['cooldown'];
                }
            }
        @endphp
        <div class="h-1 flex flex-none gap-px bg-gray-900">
            @foreach($phases as $i => $phase)
                @php
                    $phaseDuration = ($phase['duration'] * $phase['repetitions'])
                                   + ($phase['pause'] * max(0, $phase['repetitions'] - 1))
                                   + ($i < count($phases) - 1 ? $phase['cooldown'] : 0);
                    $pct = $totalDuration > 0 ? round($phaseDuration / $totalDuration * 100, 2) : 0;
                    $isActive  = $i === $phaseIndex && !in_array($state->value, ['IDLE', 'COMPLETED']);
                    $isDone    = $i < $phaseIndex || $state->value === 'COMPLETED';
                @endphp
                <div
                    class="h-full transition-opacity duration-300"
                    style="width: {{ $pct }}%;
                           background: {{ $phase['color'] }};
                           opacity: {{ $isActive ? '1' : ($isDone ? '0.2' : '0.35') }}"
                ></div>
            @endforeach
        </div>

        @php
            $ringColor = match($state->value) {
                'PAUSE', 'PAUSED' => '#6b7280',
                'COOLDOWN'        => '#f97316',
                'COMPLETED'       => '#22c55e',
                default           => $phaseColor,
            };
            $ringOpacity = $state->value === 'PAUSED' ? '0.4' : '1';
            $circumference = 2 * M_PI * 120;
            $progress = ($programTotalDuration > 0 && !in_array($state->value, ['IDLE', 'PREPARE']))
                ? $totalRemaining / $programTotalDuration
                : 1.0;
            $dashOffset = $circumference * (1 - $progress);
        @endphp

        {{-- ── Ring + inner content ────────────────────────────────────────── --}}
        <div
            class="flex-1 flex flex-col items-center justify-center px-4"
            x-data="{
                holdTimer: null,
                holding: false,
                longPressEnabled() {
                    const platform = '{{ config('app.long_press_pause', 'all') }}';
                    if (platform === 'all') return true;
                    return navigator.userAgent.toLowerCase().includes('android');
                },
                startHold(e) {
                    if (!this.longPressEnabled()) return;
                    if (!['RUNNING', 'PAUSE', 'COOLDOWN'].includes($wire.state)) return;
                    this.holding = true;
                    this.holdTimer = setTimeout(() => {
                        $wire.pause();
                        this.holding = false;
                    }, 1500);
                },
                cancelHold() {
                    clearTimeout(this.holdTimer);
                    this.holdTimer = null;
                    this.holding = false;
                }
            }"
            @pointerdown="startHold"
            @pointerup="cancelHold"
            @pointercancel="cancelHold"
            @pointerleave="cancelHold"
        >

            <div
                class="relative w-[min(92vw,380px)] aspect-square transition-opacity duration-300"
                :class="{ 'opacity-60': holding }"
            >
                {{-- SVG ring --}}
                <svg
                    viewBox="0 0 260 260"
                    class="w-full h-full -rotate-90"
                    aria-hidden="true"
                >
                    {{-- Track --}}
                    <circle
                        cx="130" cy="130" r="120"
                        fill="none"
                        stroke="#1f2937"
                        stroke-width="5"
                    />
                    {{-- Progress --}}
                    <circle
                        cx="130" cy="130" r="120"
                        fill="none"
                        stroke="{{ $ringColor }}"
                        stroke-width="5"
                        stroke-linecap="round"
                        stroke-dasharray="{{ $circumference }}"
                        stroke-dashoffset="{{ $dashOffset }}"
                        opacity="{{ $ringOpacity }}"
                        style="transition: stroke-dashoffset 0.9s linear, stroke 0.5s ease, opacity 0.5s ease;"
                    />
                </svg>

                {{-- Inner content --}}
                <div class="absolute inset-0 flex flex-col items-center justify-center gap-1">

                    {{-- Phase / state label --}}
                    <span class="text-gray-400 font-semibold text-xl leading-tight tracking-wide">
                        {{ $this->segmentLabel() }}
                    </span>

                    {{-- Main countdown --}}
                    <span
                        class="font-black tabular-nums leading-none
                               {{ $state->value === 'COMPLETED' ? 'text-green-400 text-[72px]' : 'text-white text-[72px]' }}"
                        :class="{ 'cooldown-glow': '{{ $state->value }}' === 'COOLDOWN' }"
                    >
                        {{ $state->value === 'COMPLETED' ? '✓' : $this->formattedRemaining() }}
                    </span>

                    {{-- Rep counter --}}
                    @if($this->repLabel())
                        <span class="text-gray-500 text-base leading-tight">
                            Rep {{ $this->repLabel() }}
                        </span>
                    @endif

                </div>
            </div>

            {{-- State messages below ring --}}
            @if($state->value === 'COMPLETED')
                <p class="text-green-300 font-semibold text-base mt-4 animate-pulse">
                    Program Complete!
                </p>
            @elseif($state->value === 'COOLDOWN')
                <p class="text-orange-300/70 text-sm font-medium mt-4 tracking-wide animate-pulse">
                    Take deep breaths
                </p>
            @endif

            {{-- Total remaining --}}
            @if(in_array($state->value, ['RUNNING', 'PAUSE', 'COOLDOWN', 'PAUSED']) && $totalRemaining > 0)
                <p class="text-gray-600 text-sm mt-3">
                    {{ $this->formattedTotal() }} total remaining
                </p>
            @endif

            {{-- Hold-to-pause hint (ambient, non-intrusive) --}}
            @if(in_array($state->value, ['RUNNING', 'PAUSE', 'COOLDOWN']))
                <p
                    class="text-gray-800 text-xs mt-2 select-none"
                    x-show="longPressEnabled()"
                >
                    Hold to pause
                </p>
            @endif

        </div>

        {{-- ── Controls ───────────────────────────────────────────────────── --}}
        <div class="flex-none px-6 pb-6 space-y-3">

            {{-- Primary action --}}
            @if($state === StateMachine::idle)
                @if(count($phases) === 0)
                    <div class="w-full text-center text-amber-400 text-sm font-medium py-4 px-4
                                bg-amber-950/40 border border-amber-800/40 rounded-3xl">
                        No phases — edit this program to add at least one phase before starting.
                    </div>
                @else
                    <button
                        wire:click="start"
                        class="w-full bg-blue-600 hover:bg-blue-500 active:bg-blue-700 text-white font-bold
                       text-xl py-5 rounded-3xl transition-colors shadow-lg shadow-blue-900/40"
                    >
                        Start
                    </button>
                @endif

            @elseif($state === StateMachine::prepare)
                {{-- No primary action during prepare — user just waits --}}

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
