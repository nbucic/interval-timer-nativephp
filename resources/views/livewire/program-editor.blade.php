@php use App\Enum\BeepLeadIn; @endphp
<div class="flex flex-col h-full">

    {{-- Header --}}
    <div class="flex items-center gap-3 px-4 py-4 border-b border-white/5">
        <a href="/" wire:navigate class="p-1 text-gray-400 hover:text-white">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
            </svg>
        </a>
        <div class="flex-1 min-w-0">
            <input
                type="text"
                wire:model.live.debounce.500ms="name"
                placeholder="Program name…"
                maxlength="60"
                class="w-full bg-transparent text-white font-bold text-lg placeholder-gray-600
                       focus:outline-none border-b border-transparent focus:border-blue-500 pb-0.5 transition-colors"
            >
        </div>
        <button
            wire:click="saveProgram"
            class="bg-blue-600 hover:bg-blue-500 active:bg-blue-700 text-white text-sm font-semibold
                   px-4 py-2 rounded-xl transition-colors shrink-0"
        >Save
        </button>
    </div>

    {{-- Scrollable body --}}
    <div class="flex-1 overflow-y-auto px-4 pb-6 space-y-5">

        {{-- Program settings --}}
        <div class="mt-4 bg-gray-900 rounded-2xl border border-white/5 divide-y divide-white/5">

            {{-- Beep lead-in --}}
            <div class="flex items-center justify-between px-4 py-3.5">
                <div>
                    <p class="text-white text-sm font-medium">Beep Lead-in</p>
                    <p class="text-gray-500 text-xs mt-0.5">Countdown beeps before each segment ends</p>
                </div>
                <div class="flex gap-2">
                    <button
                        wire:click="$set('beepLeadIn', 3)"
                        class="px-3 py-1.5 rounded-lg text-sm font-semibold transition-colors
                               {{ $beepLeadIn === BeepLeadIn::Three ? 'bg-blue-600 text-white' : 'bg-gray-800 text-gray-400' }}"
                    >3s
                    </button>
                    <button
                        wire:click="$set('beepLeadIn', 5)"
                        class="px-3 py-1.5 rounded-lg text-sm font-semibold transition-colors
                               {{ $beepLeadIn === BeepLeadIn::Five ? 'bg-blue-600 text-white' : 'bg-gray-800 text-gray-400' }}"
                    >5s
                    </button>
                </div>
            </div>

            {{-- End sound --}}
            <div class="flex items-center justify-between px-4 py-3.5">
                <div>
                    <p class="text-white text-sm font-medium">End Sound</p>
                    <p class="text-gray-500 text-xs mt-0.5">Plays once on program completion</p>
                </div>
                <div class="flex gap-2">
                    <button
                        wire:click="$set('endSound', 'triple')"
                        class="px-3 py-1.5 rounded-lg text-sm font-semibold transition-colors
                               {{ $endSound === 'triple' ? 'bg-blue-600 text-white' : 'bg-gray-800 text-gray-400' }}"
                    >Triple
                    </button>
                    <button
                        wire:click="$set('endSound', 'chime')"
                        class="px-3 py-1.5 rounded-lg text-sm font-semibold transition-colors
                               {{ $endSound === 'chime' ? 'bg-blue-600 text-white' : 'bg-gray-800 text-gray-400' }}"
                    >Chime
                    </button>
                </div>
            </div>
        </div>

        {{-- Total duration badge --}}
        @if($this->totalDuration() > 0)
            <div class="flex items-center justify-center">
            <span class="text-gray-500 text-sm">
                Total: <span class="text-white font-semibold">{{ $this->formattedDuration() }}</span>
            </span>
            </div>
        @endif

        {{-- Phases --}}
        <div>
            <div class="flex items-center justify-between mb-2">
                <h2 class="text-sm font-semibold text-gray-400 uppercase tracking-wider">
                    Phases ({{ count($phases) }}/10)
                </h2>
                @if(count($phases) < 10)
                    <button
                        wire:click="openAddPhase"
                        class="text-blue-400 text-sm font-semibold hover:text-blue-300"
                    >+ Add Phase
                    </button>
                @endif
            </div>

            <div class="space-y-2">
                @forelse($phases as $i => $phase)
                    <div class="flex items-center gap-2 bg-gray-900 rounded-2xl px-3 py-3 border border-white/5">

                        {{-- Colour swatch --}}
                        <div
                            class="w-3 h-10 rounded-full shrink-0"
                            style="background: {{ $phase['color'] }}"
                        ></div>

                        {{-- Info --}}
                        <div class="flex-1 min-w-0">
                            <p class="text-white font-medium text-sm truncate">{{ $phase['label'] }}</p>
                            <p class="text-gray-500 text-xs mt-0.5">
                                {{ $phase['duration'] }}s
                                × {{ $phase['repetitions'] }} {{ Str::plural('rep', $phase['repetitions']) }}
                                @if($phase['pause'] > 0)
                                    · {{ $phase['pause'] }}s pause
                                @endif
                                @if($phase['cooldown'] > 0 && !$loop->last)
                                    · {{ $phase['cooldown'] }}s cooldown
                                @endif
                            </p>
                        </div>

                        {{-- Reorder --}}
                        <div class="flex flex-col gap-0.5">
                            <button wire:click="movePhaseUp({{ $i }})"
                                    class="p-1 text-gray-600 hover:text-gray-400 {{ $i === 0 ? 'invisible' : '' }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2"
                                     viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="m4.5 15.75 7.5-7.5 7.5 7.5"/>
                                </svg>
                            </button>
                            <button wire:click="movePhaseDown({{ $i }})"
                                    class="p-1 text-gray-600 hover:text-gray-400 {{ $i === count($phases)-1 ? 'invisible' : '' }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2"
                                     viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                                </svg>
                            </button>
                        </div>

                        {{-- Edit --}}
                        <button wire:click="editPhase({{ $i }})" class="p-2 text-gray-500 hover:text-gray-300">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5"
                                 viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/>
                            </svg>
                        </button>

                        {{-- Delete --}}
                        <button wire:click="deletePhase({{ $i }})" class="p-2 text-gray-600 hover:text-red-500">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5"
                                 viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                @empty
                    <div class="flex flex-col items-center py-10 text-center">
                        <p class="text-gray-500 text-sm">No phases yet — add one above</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- ── Phase form sheet ─────────────────────────────────────────────── --}}
    @if($showPhaseForm)
        <div
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/60 backdrop-blur-sm"
            @keydown.escape.window="$wire.cancelPhaseForm()"
        >
            <div class="w-full bg-gray-900 rounded-t-2xl safe-bottom max-h-[90vh] overflow-y-auto"
                 @click.stop>

                <div
                    class="sticky top-0 bg-gray-900 flex items-center justify-between px-5 py-4 border-b border-white/5">
                    <h2 class="text-lg font-bold text-white">
                        {{ $editingPhaseIndex !== null ? 'Edit Phase' : 'Add Phase' }}
                    </h2>
                    <button wire:click="cancelPhaseForm" class="text-gray-500 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="px-5 py-5 space-y-5">

                    {{-- Label --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1.5">Phase
                            Name</label>
                        <input
                            type="text"
                            wire:model="phaseLabel"
                            placeholder="e.g. Work, Rest, Sprint…"
                            maxlength="40"
                            class="w-full bg-gray-800 text-white placeholder-gray-600 rounded-xl px-4 py-3
                               border border-white/10 focus:border-blue-500 focus:outline-none"
                        >
                        @error('phaseLabel') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Duration × Reps --}}
                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1.5">
                                Duration (sec)
                            </label>
                            <input type="number" wire:model="phaseDuration" min="1" max="3600"
                                   class="w-full bg-gray-800 text-white rounded-xl px-4 py-3
                                      border border-white/10 focus:border-blue-500 focus:outline-none text-center text-lg font-bold">
                            @error('phaseDuration') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1.5">
                                Repetitions (1–50)
                            </label>
                            <input type="number" wire:model.live="phaseReps" min="1" max="50"
                                   class="w-full bg-gray-800 text-white rounded-xl px-4 py-3
                                      border border-white/10 focus:border-blue-500 focus:outline-none text-center text-lg font-bold">
                            @error('phaseReps') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- Pause × Cooldown --}}
                    <div class="grid grid-cols-1 gap-4">
                        @if($phaseReps > 1)
                            <div>
                                <label
                                    class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1.5">
                                    Pause (sec)
                                </label>
                                <p class="text-gray-600 text-[10px] mb-1">Between reps</p>
                                <input type="number" wire:model="phasePause" min="0" max="3600"
                                       class="w-full bg-gray-800 text-white rounded-xl px-4 py-3
                                          border border-white/10 focus:border-blue-500 focus:outline-none text-center text-lg font-bold">
                            </div>
                        @endif
                        <div class="{{ $phaseReps > 1 ? '' : 'col-span-2' }}">
                            <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5
                                          {{ $this->editingIsLastPhase() ? 'text-gray-600' : 'text-gray-400' }}">
                                Cooldown (sec)
                            </label>
                            <p class="text-[10px] mb-1 {{ $this->editingIsLastPhase() ? 'text-gray-700' : 'text-gray-600' }}">
                                @if($this->editingIsLastPhase())
                                    not counted — add another phase
                                @else
                                    After final rep
                                @endif
                            </p>
                            <input type="number" wire:model="phaseCooldown" min="0" max="3600"
                                   @if($this->editingIsLastPhase()) disabled @endif
                                   class="w-full rounded-xl px-4 py-3 border text-center text-lg font-bold
                                          focus:outline-none transition-colors
                                          {{ $this->editingIsLastPhase()
                                              ? 'bg-gray-800/40 text-gray-600 border-white/5 cursor-not-allowed'
                                              : 'bg-gray-800 text-white border-white/10 focus:border-blue-500' }}">
                        </div>
                    </div>

                    {{-- Colour --}}
                    <div>
                        <label
                            class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Colour</label>
                        <div class="flex gap-3 flex-wrap">
                            @foreach($palette as $color)
                                <button
                                    wire:click="$set('phaseColor', '{{ $color }}')"
                                    class="w-8 h-8 rounded-full border-2 transition-all"
                                    style="background: {{ $color }}; border-color: {{ $phaseColor === $color ? 'white' : 'transparent' }}"
                                ></button>
                            @endforeach
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div class="flex gap-3 pb-2">
                        <button
                            wire:click="cancelPhaseForm"
                            class="flex-1 bg-gray-800 hover:bg-gray-700 text-gray-300 font-semibold py-3 rounded-xl"
                        >Cancel
                        </button>
                        <button
                            wire:click="savePhase"
                            class="flex-1 bg-blue-600 hover:bg-blue-500 text-white font-semibold py-3 rounded-xl"
                        >{{ $editingPhaseIndex !== null ? 'Update' : 'Add' }} Phase
                        </button>
                        @if( $editingPhaseIndex === null && count($phases) < 10)
                            <button wire:click="savePhaseAndAddNew"
                                    class="flex-1 bg-blue-600 hover:bg-blue-500 text-white font-semibold py-3 rounded-xl">
                                Add Phase And Start New
                            </button>
                        @endif

                    </div>

                </div>
            </div>
        </div>
    @endif

</div>
