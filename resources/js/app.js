import './bootstrap';
import {initAudio} from './audio';
import audioTTS from "../../packages/nbucic/audio-tts/resources/js/audioTTS.js";

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
            this.soundMode = this.$wire.soundMode;
            this.volume = this.$wire.volume;
            this.keepScreenOn = this.$wire.keepScreenOn;
            this.program = this.$wire.program;
            this.audio = initAudio(this.volume);

            console.log('[TTS] timerAudio init: soundMode=' + this.soundMode
                + ', keepScreenOn=' + this.keepScreenOn
                + ', AndroidTTS=' + (typeof audioTTS.speak === 'function'));

            // Ticker logic: poll wire.tick() every 1 000 ms when the timer is active
            this.$watch('$wire.state', async state => {
                console.log('State changed:', state);

                clearInterval(this.interval);
                if (['PREPARE', 'RUNNING', 'PAUSE', 'COOLDOWN'].includes(state)) {
                    this.interval = setInterval(() => this.$wire.tick(), 1000);
                    await this._acquireWakeLock();
                } else {
                    await this._releaseWakeLock();
                }
            });

            this.$wire.on('playBeep', async ({reason}) => {
                console.log(`Beep, reason: ${reason}, mode: ${this.soundMode}`);
                if (this.soundMode === 'voice') {
                    const text = this.voiceText(reason);
                    console.log('[TTS] playBeep: reason=' + reason + ', voiceText="' + text + '"');
                    await audioTTS.speak(text);
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
                    this.audio = initAudio(volume);
                }
                if (keepScreenOn !== undefined) this.keepScreenOn = keepScreenOn;
            });
        },

        voiceText(reason) {
            const map = {
                prepare: this.$wire?.countdownLabel ?? '',
                countdown: this.$wire?.countdownLabel ?? 'Get ready',
                rep_end: 'Done',
                pause_end: 'Go',
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
                // Re-acquire automatically if the OS releases it (e.g., tab hidden then shown)
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
    Alpine.data('settingsSounds', () => ({
        soundMode: 'beep',
        volume: 0.8,

        init() {
            this.soundMode = this.$wire.soundMode;
            this.volume = this.$wire.volume;
            this.audio = initAudio(1);

            this.$wire.on('playBeepSound', ({sound}) => {
                console.log('Playing beep sound');
                if (sound === 'triple') {
                    this.audio.tripleBeep();
                } else {
                    this.audio.chime();
                }
            });

            this.$wire.on('play-TTS-Sound', ({text}) => {
                console.log('Playing ho ho ho');
                audioTTS.speak(text).then(() => {
                });
            })
        }
    }))
});

// Alpine is automatically started by Livewire v3.
