/**
 * Audio engine for the interval timer.
 */
export function initAudio(volume = 1.0) {
    volume = Math.max(0, Math.min(1, volume));
    let ctx = null;

    function getCtx() {
        if (!ctx) {
            ctx = new (window.AudioContext)();
        }
        if (ctx.state === 'suspended') {
            ctx.resume();
        }
        return ctx;
    }

    /** Play a single short beep at the given frequency and duration. */
    function tone(freq = 880, durationMs = 120, delayMs = 0) {
        const c = getCtx();
        const osc = c.createOscillator();
        const gain = c.createGain();
        const start = c.currentTime + delayMs / 1000;
        const end = start + durationMs / 1000;

        osc.type = 'sine';
        osc.frequency.setValueAtTime(freq, start);
        gain.gain.setValueAtTime(volume, start);
        gain.gain.exponentialRampToValueAtTime(0.001, end);

        osc.connect(gain);
        gain.connect(c.destination);
        osc.start(start);
        osc.stop(end);
    }

    /** Single countdown beep (800 Hz, 100 ms). */
    function beep() {
        tone(800, 100);
    }

    /** Prepare-phase beep -- three rapid beeps at the same tone (800 Hz, 100 ms x 3). */
    function prepareBeep() {
        tone(800, 100, 0);
        tone(800, 100, 150);
        tone(800, 100, 300);
    }

    /** Gentle single beep on user pause (600 Hz, 80 ms). */
    function pauseBeep() {
        tone(600, 80);
    }

    /**
     * End sound: triple beep (three 880 Hz tones 150 ms apart).
     * finish-triple.mp3 semantics, synthesized.
     */
    function tripleBeep() {
        tone(880, 120, 0);
        tone(880, 120, 150);
        tone(880, 120, 300);
    }

    /**
     * End sound: chime (descending 3-tone chord, warm).
     * finish-chime.mp3 semantics, synthesized.
     */
    function chime() {
        tone(1046.5, 300, 0);    // C6
        tone(880, 300, 120);  // A5
        tone(698.5, 400, 240);  // F5
    }

    return {
        beep,
        prepareBeep,
        pauseBeep,
        tripleBeep,
        chime,
    };
}
