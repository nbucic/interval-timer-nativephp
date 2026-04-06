import './bootstrap';
import Alpine from 'alpinejs';
import { initAudio } from './audio';

window.Alpine = Alpine;

// Boot Alpine after Livewire (Livewire v3 integrates automatically)
document.addEventListener('alpine:init', () => {
    Alpine.data('timerAudio', () => ({
        audio: null,
        soundMode: 'beep',
        volume: 0.8,
        init() {
            this.audio = initAudio();
            // Receive settings from Livewire
            this.$wire.on('settingsLoaded', ({ soundMode, volume }) => {
                this.soundMode = soundMode;
                this.audio.setVolume(volume);
            });
            // Beep trigger from Livewire
            this.$wire.on('playBeep', ({ reason }) => {
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
        },
        voiceText(reason) {
            const map = {
                countdown:   this.$wire.countdownLabel ?? 'Get ready',
                rep_end:     'Done',
                pause_end:   'Go',
                cooldown_end: 'Next',
            };
            return map[reason] ?? 'Beep';
        },
    }));
});

Alpine.start();
