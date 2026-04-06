import './bootstrap';
import { initAudio } from './audio';

// Boot Alpine after Livewire (Livewire v3 integrates automatically)
document.addEventListener('alpine:init', () => {
    Alpine.data('timerAudio', () => ({
        audio: null,
        soundMode: 'beep',
        volume: 0.8,
        interval: null,
        init() {
            console.log('timerAudio init', this.$wire);
            this.audio = initAudio();
            // Wait for Alpine and Livewire to be ready
            const boot = () => {
                if (typeof this.$wire === 'undefined') {
                    console.log('Waiting for $wire...');
                    // Not ready yet, retry on next tick
                    this.$nextTick(boot);
                    return;
                }

                console.log('$wire ready', this.$wire);

                // Ticker logic: poll wire.tick() every 1 000 ms when the timer is active
                this.$watch('$wire.state', state => {
                    // Handle Livewire Enum serialization (v3)
                    const stateName = (typeof state === 'string') ? state : state?.value || state?.s || state;
                    console.log('State changed:', stateName);

                    clearInterval(this.interval);
                    if (['RUNNING', 'PAUSE', 'COOLDOWN'].includes(stateName)) {
                        this.interval = setInterval(() => this.$wire.tick(), 1000);
                    }
                });

                console.log('Wire state: ', this.$wire.phaseIndex);

                // Boot on first render
                const initialState = (typeof this.$wire.state === 'string') ? this.$wire.state : this.$wire.state?.value || this.$wire.state?.s || this.$wire.state;
                if (['RUNNING', 'PAUSE', 'COOLDOWN'].includes(initialState)) {
                    this.interval = setInterval(() => this.$wire.tick(), 1000);
                }

                console.log('Initial state:', initialState);

                // Receive settings from Livewire
                this.$wire.on('settingsLoaded', ({ soundMode, volume, program }) => {
                    console.log('Program: ', {program});
                    console.log('settingsLoaded', { soundMode, volume });
                    this.soundMode = soundMode;
                    this.audio.setVolume(volume);
                });

                // Request initial settings if already loaded
                this.$wire.requestSettings();

                // Beep trigger from Livewire
                this.$wire.on('playBeep', ({ reason }) => {
                    console.log('playBeep', reason);
                    if (this.soundMode === 'voice') {
                        this.audio.speak(this.voiceText(reason));
                    } else {
                        this.audio.beep();
                    }
                });
                // Pause beep
                this.$wire.on('playPauseBeep', () => {
                    this.audio.pauseBeep();
                });
                // End sound
                this.$wire.on('playEndSound', ({ sound }) => {
                    if (sound === 'triple') {
                        this.audio.tripleBeep();
                    } else {
                        this.audio.chime();
                    }
                });
            };

            this.$nextTick(boot);
        },
        voiceText(reason) {
            const map = {
                countdown:   this.$wire?.countdownLabel ?? 'Get ready',
                rep_end:     'Done',
                pause_end:   'Go',
                cooldown_end: 'Next',
            };
            return map[reason] ?? 'Beep';
        },
    }));
});

// Alpine is automatically started by Livewire v3.
