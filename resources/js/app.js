import './bootstrap';
import { initAudio } from './audio';

// Boot Alpine after Livewire (Livewire v3 integrates automatically)
document.addEventListener('alpine:init', () => {
    Alpine.data('timerAudio', () => ({
        timer: null,
        audio: null,
        soundMode: 'beep',
        volume: 0.8,
        keepScreenOn: true,
        interval: null,
        _wakeLock: null,

        init() {
            this.soundMode    = this.$wire.soundMode;
            this.volume       = this.$wire.volume;
            this.keepScreenOn = this.$wire.keepScreenOn;
            this.program      = this.$wire.program;
            this.audio        = initAudio(this.volume);

            // Ticker logic: poll wire.tick() every 1 000 ms when the timer is active
            this.$watch('$wire.state', state => {
                console.log('State changed:', state);

                clearInterval(this.interval);
                if (['PREPARE', 'RUNNING', 'PAUSE', 'COOLDOWN'].includes(state)) {
                    this.interval = setInterval(() => this.$wire.tick(), 1000);
                    this._acquireWakeLock();
                } else {
                    this._releaseWakeLock();
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

            // Sync settings changes saved from the Settings screen
            this.$wire.on('settingsLoaded', ({soundMode, volume, keepScreenOn}) => {
                if (soundMode !== undefined) this.soundMode = soundMode;
                if (volume !== undefined) {
                    this.volume = volume;
                    this.audio  = initAudio(volume);
                }
                if (keepScreenOn !== undefined) this.keepScreenOn = keepScreenOn;
            });
        },

        voiceText(reason) {
            const map = {
                prepare:      this.$wire?.countdownLabel ?? '',
                countdown:    this.$wire?.countdownLabel ?? 'Get ready',
                rep_end:      'Done',
                pause_end:    'Go',
                cooldown_end: 'Next',
            };
            return map[reason] ?? 'Beep';
        },

        async _acquireWakeLock() {
            if (!this.keepScreenOn) return;
            if (!('wakeLock' in navigator)) return;
            if (this._wakeLock) return; // already held
            try {
                this._wakeLock = await navigator.wakeLock.request('screen');
                console.log('Wake lock acquired');
                // Re-acquire automatically if the OS releases it (e.g. tab hidden then shown)
                this._wakeLock.addEventListener('release', () => {
                    this._wakeLock = null;
                });
            } catch (e) {
                console.warn('Wake lock request failed:', e.message);
            }
        },

        async _releaseWakeLock() {
            if (!this._wakeLock) return;
            try {
                await this._wakeLock.release();
                console.log('Wake lock released');
            } catch (e) {
                console.warn('Wake lock release failed:', e.message);
            }
            this._wakeLock = null;
        },
    }));
});

// Alpine is automatically started by Livewire v3.