import './bootstrap';
import { initAudio } from './audio';

// Boot Alpine after Livewire (Livewire v3 integrates automatically)
document.addEventListener('alpine:init', () => {
    Alpine.data('timerAudio', () => ({
        timer: null,
        audio: null,
        soundMode: 'beep',
        volume: 0.8,
        interval: null,
        init() {
            this.soundMode = this.$wire.soundMode;
            this.volume = this.$wire.volume;
            this.program = this.$wire.program;
            this.audio = initAudio(this.volume);

            // Ticker logic: poll wire.tick() every 1 000 ms when the timer is active
            this.$watch('$wire.state', state => {
                // Handle Livewire Enum serialization (v3)
                const stateName = state;
                console.log('State changed:', stateName);

                clearInterval(this.interval);
                if (['PREPARE', 'RUNNING', 'PAUSE', 'COOLDOWN'].includes(stateName)) {
                    this.interval = setInterval(() => this.$wire.tick(), 1000);
                }
            });

            this.$wire.on('playBeep', ({reason}) => {
                console.log('playBeep', reason);
                if (this.soundMode === 'voice') {
                    this.audio.speak(this.voiceText(reason));
                } else if (reason === 'prepare') {
                    this.audio.prepareBeep();
                } else {
                    this.audio.beep();
                }
            });

            this.$wire.on('playEndSound', ({sound}) => {
                if (sound === 'triple') {
                    this.audio.tripleBeep();
                } else {
                    this.audio.chime();
                }
            });

            this.$wire.on('playPauseBeep', () => {
                this.audio.pauseBeep();
            });
        },
        voiceText(reason) {
            const map = {
                prepare:     this.$wire?.countdownLabel ?? '',
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
