/**
 * AudioTTS Plugin for NativePHP Mobile
 *
 * @example
 * import { audioTTS } from '@nbucic/audio-tts';
 *
 * // Execute functionality
 * const result = await audioTTS.execute({ option1: 'value' });
 *
 * // Get status
 * const status = await audioTTS.getStatus();
 */

const baseUrl = '/_native/api/call';

/**
 * Internal bridge call function
 * @private
 */
async function bridgeCall(method, params = {}) {
    const response = await fetch(baseUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        },
        body: JSON.stringify({ method, params })
    });

    const result = await response.json();

    if (result.status === 'error') {
        throw new Error(result.message || 'Native call failed');
    }

    const nativeResponse = result.data;
    if (nativeResponse && nativeResponse.data !== undefined) {
        return nativeResponse.data;
    }

    return nativeResponse;
}

/**
 * Speak text via Android TTS.
 * Falls back to Web Speech API when the native bridge is unavailable (browser dev).
 * @param {string} text - The phrase to speak
 * @param {number} [volume=1.0] - Output volume 0.0–1.0
 * @returns {Promise<any>}
 */
export async function speak(text, volume = 1.0) {
    try {
        console.log(`[TTS SPEAK] text: ${text}, volume: ${volume}`);
        return await bridgeCall('AudioTTS.Speak', { text, volume });
    } catch (e) {
        if ('speechSynthesis' in window) {
            const utt = new SpeechSynthesisUtterance(text);
            utt.volume = volume;
            speechSynthesis.speak(utt);
            return { queued: true, text, fallback: 'webSpeech' };
        }
        throw e;
    }
}

/**
 * Get the current status
 * @returns {Promise<Object>}
 */
export async function getStatus() {
    return bridgeCall('AudioTTS.GetStatus');
}

/**
 * AudioTTS namespace object
 */
export const audioTTS = {
    speak,
    getStatus
};

export default audioTTS;
