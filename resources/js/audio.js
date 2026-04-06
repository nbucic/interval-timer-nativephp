/**
 * Audio engine for the interval timer.
 *
 * soundMode = 'beep'  → Web Audio API (synthesised, no network)
 * soundMode = 'voice' → Android TTS via NativePHP JS bridge (feminine, calm)
 *
 * All methods are no-ops until the user has interacted with the page,
 * satisfying the browser AudioContext autoplay policy.
 */
export function initAudio() {
    let ctx = null;
    let volume = 0.8;

    function getCtx() {
        if (!ctx) {
            ctx = new (window.AudioContext || window.webkitAudioContext)();
        }
        if (ctx.state === 'suspended') {
            ctx.resume();
        }
        return ctx;
    }

    /** Play a single short beep at the given frequency and duration. */
    function tone(freq = 880, durationMs = 120, delayMs = 0) {
        const c      = getCtx();
        const osc    = c.createOscillator();
        const gain   = c.createGain();
        const start  = c.currentTime + delayMs / 1000;
        const end    = start + durationMs / 1000;

        osc.type      = 'sine';
        osc.frequency.setValueAtTime(freq, start);
        gain.gain.setValueAtTime(volume * 0.6, start);
        gain.gain.exponentialRampToValueAtTime(0.001, end);

        osc.connect(gain);
        gain.connect(c.destination);
        osc.start(start);
        osc.stop(end);
    }

    /** Single countdown beep (800 Hz, 100 ms). */
    function beep() { tone(800, 100); }

    /** Gentle single beep on user pause (600 Hz, 80 ms). */
    function pauseBeep() { tone(600, 80); }

    /**
     * End sound: triple beep (three 880 Hz tones 150 ms apart).
     * finish-triple.mp3 semantics, synthesised.
     */
    function tripleBeep() {
        tone(880, 120, 0);
        tone(880, 120, 150);
        tone(880, 120, 300);
    }

    /**
     * End sound: chime (descending 3-tone chord, warm).
     * finish-chime.mp3 semantics, synthesised.
     */
    function chime() {
        tone(1046.5, 300, 0);    // C6
        tone(880,    300, 120);  // A5
        tone(698.5,  400, 240);  // F5
    }

    /**
     * Android TTS via NativePHP bridge.
     * Falls back to Web Speech API on web browser.
     */
    function speak(text) {
        if (window.NativePhp?.tts?.speak) {
            // NativePHP Mobile TTS plugin (feminine, calm pitch)
            window.NativePhp.tts.speak({
                text,
                voice: 'female',
                pitch: 0.9,
                rate: 0.85,
            });
            return;
        }
        // Browser fallback
        if ('speechSynthesis' in window) {
            const utt    = new SpeechSynthesisUtterance(text);
            utt.pitch    = 0.9;
            utt.rate     = 0.85;
            utt.volume   = volume;
            const voices = speechSynthesis.getVoices();
            const fem    = voices.find(v => v.name.toLowerCase().includes('female'))
                        || voices.find(v => v.lang.startsWith('en'));
            if (fem) utt.voice = fem;
            speechSynthesis.speak(utt);
        }
    }

    return {
        beep,
        pauseBeep,
        tripleBeep,
        chime,
        speak,
        setVolume(v) { volume = Math.max(0, Math.min(1, v)); },
    };
}
