<div class="flex flex-col h-full">

    {{-- Header --}}
    <div class="flex items-center justify-between px-4 py-4">
        <h1 class="text-xl font-bold text-white">Programs</h1>
        <button
            wire:click="openCreate"
            class="flex items-center gap-1.5 bg-blue-600 hover:bg-blue-500 active:bg-blue-700
                   text-white text-sm font-semibold px-3 py-2 rounded-xl transition-colors"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
            </svg>
            New
        </button>
    </div>

    {{-- Create modal --}}
    @if($showCreate)
    <div
        class="fixed inset-0 z-50 flex items-end justify-center bg-black/60 backdrop-blur-sm"
        x-data
        @keydown.escape.window="$wire.cancelCreate()"
    >
        <div class="w-full bg-gray-900 rounded-t-2xl p-6 pb-8 safe-bottom space-y-4"
             @click.stop>
            <h2 class="text-lg font-bold text-white">New Program</h2>
            <input
                type="text"
                wire:model="newName"
                wire:keydown.enter="createProgram"
                placeholder="Program name…"
                maxlength="60"
                autofocus
                class="w-full bg-gray-800 text-white placeholder-gray-500 rounded-xl px-4 py-3
                       border border-white/10 focus:border-blue-500 focus:outline-none text-base"
            >
            <div class="flex gap-3">
                <button
                    wire:click="cancelCreate"
                    class="flex-1 bg-gray-800 hover:bg-gray-700 text-gray-300 font-semibold py-3 rounded-xl transition-colors"
                >Cancel</button>
                <button
                    wire:click="createProgram"
                    class="flex-1 bg-blue-600 hover:bg-blue-500 text-white font-semibold py-3 rounded-xl transition-colors"
                >Create &amp; Edit</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Program list --}}
    <div class="flex-1 overflow-y-auto px-4 pb-4 space-y-2">

        @forelse($programs as $program)
        <div
            class="flex items-center gap-3 bg-gray-900 rounded-2xl px-4 py-4
                   border border-white/5 active:bg-gray-800 transition-colors"
            x-data="{ swipeX: 0, startX: 0, dragging: false }"
        >
            {{-- Tap → go to timer --}}
            <a
                href="/timer/{{ $program->id }}"
                wire:navigate
                class="flex-1 min-w-0"
            >
                <div class="flex items-center gap-3">
                    {{-- Phase colour dots --}}
                    <div class="flex gap-1 flex-shrink-0">
                        @foreach(array_slice($program->phases, 0, 5) as $phase)
                        <span
                            class="w-2.5 h-2.5 rounded-full"
                            style="background: {{ $phase->color }}"
                        ></span>
                        @endforeach
                        @if(count($program->phases) === 0)
                        <span class="w-2.5 h-2.5 rounded-full bg-gray-700"></span>
                        @endif
                    </div>

                    <div class="min-w-0">
                        <p class="text-white font-semibold text-base truncate">{{ $program->name }}</p>
                        <p class="text-gray-500 text-xs mt-0.5">
                            {{ count($program->phases) }} {{ Str::plural('phase', count($program->phases)) }}
                            @if($program->totalDuration() > 0)
                                · {{ $program->formattedDuration() }}
                            @endif
                        </p>
                    </div>
                </div>
            </a>

            {{-- Edit --}}
            <a
                href="/programs/{{ $program->id }}/edit"
                wire:navigate
                class="p-2 text-gray-500 hover:text-gray-300 transition-colors flex-shrink-0"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/>
                </svg>
            </a>

            {{-- Delete --}}
            <button
                wire:click="deleteProgram('{{ $program->id }}')"
                wire:confirm="Delete '{{ addslashes($program->name) }}'?"
                class="p-2 text-gray-600 hover:text-red-500 transition-colors flex-shrink-0"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
                </svg>
            </button>
        </div>

        @empty
        <div class="flex flex-col items-center justify-center py-24 text-center">
            <div class="w-16 h-16 rounded-full bg-gray-900 flex items-center justify-center mb-4">
                <svg class="w-8 h-8 text-gray-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                </svg>
            </div>
            <p class="text-gray-400 font-medium">No programs yet</p>
            <p class="text-gray-600 text-sm mt-1">Tap <strong class="text-gray-400">New</strong> to create one</p>
        </div>
        @endforelse

    </div>
</div>
