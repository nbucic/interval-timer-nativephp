package com.nbucic.plugins.audio_tts

import android.content.Context
import android.media.AudioManager
import android.os.Bundle
import android.speech.tts.TextToSpeech
import android.speech.tts.UtteranceProgressListener
import android.util.Log
import androidx.fragment.app.FragmentActivity
import com.nativephp.mobile.bridge.BridgeFunction
import com.nativephp.mobile.bridge.BridgeResponse

private const val TAG = "AudioTTSEngine"

/**
 * Singleton TTS engine — initialized once at app startup, shared across all bridge functions.
 * Mirrors the lifecycle pattern from react-native-tts (TextToSpeechModule).
 */
private object TTSEngine {

    private var tts: TextToSpeech? = null

    @Volatile
    private var ready = false

    @Volatile
    private var initialized = false

    @Synchronized
    fun initialize(context: Context) {
        if (initialized) return
        initialized = true

        tts = TextToSpeech(context.applicationContext) { status ->
            if (status == TextToSpeech.SUCCESS) {
                val langResult = tts?.setLanguage(java.util.Locale.US)
                if (langResult == TextToSpeech.LANG_MISSING_DATA ||
                    langResult == TextToSpeech.LANG_NOT_SUPPORTED
                ) {
                    Log.e(TAG, "Language not supported (result=$langResult)")
                } else {
                    tts?.setPitch(0.9f)
                    tts?.setSpeechRate(0.85f)
                    ready = true
                    Log.d(TAG, "TTS engine ready")
                    attachProgressListener()
                }
            } else {
                Log.e(TAG, "TTS init failed with status=$status")
            }
        }
    }

    val isReady: Boolean get() = ready

    /**
     * Speak text at the given volume (0.0–1.0).
     *
     * Uses QUEUE_ADD so phrases queue rather than cutting each other off.
     * Uses Bundle params (API 21+) to set stream and volume — same approach as react-native-tts.
     */
    fun speak(text: String, volume: Float = 1.0f): Boolean {
        if (!ready) {
            Log.w(TAG, "speak() called before engine ready — dropping: \"$text\"")
            return false
        }

        val utteranceId = text.hashCode().toString()
        val params = Bundle().apply {
            putInt(TextToSpeech.Engine.KEY_PARAM_STREAM, AudioManager.STREAM_MUSIC)
            putFloat(TextToSpeech.Engine.KEY_PARAM_VOLUME, volume.coerceIn(0f, 1f))
        }

        val result = tts?.speak(text, TextToSpeech.QUEUE_ADD, params, utteranceId)
        return if (result == TextToSpeech.SUCCESS) {
            Log.d(TAG, "speak() queued: \"$text\"")
            true
        } else {
            Log.e(TAG, "speak() failed (result=$result) for: \"$text\"")
            false
        }
    }

    private fun attachProgressListener() {
        tts?.setOnUtteranceProgressListener(object : UtteranceProgressListener() {
            override fun onStart(utteranceId: String) {
                Log.d(TAG, "onStart utteranceId=$utteranceId")
            }

            override fun onDone(utteranceId: String) {
                Log.d(TAG, "onDone utteranceId=$utteranceId")
            }

            override fun onError(utteranceId: String) {
                Log.e(TAG, "onError utteranceId=$utteranceId")
            }
        })
    }

    fun shutdown() {
        tts?.stop()
        tts?.shutdown()
        tts = null
        ready = false
        initialized = false
        Log.d(TAG, "TTS engine shut down")
    }
}

/**
 * NativePHP bridge functions for the AudioTTS plugin.
 *
 * Each class is instantiated once at app startup by PluginBridgeFunctionRegistration,
 * so TTSEngine.initialize() runs exactly once across all bridge function constructors.
 */
object AudioTTSFunctions {

    /**
     * Speak text via Android TTS.
     *
     * Parameters:
     *   text   (String, required) — the phrase to speak
     *   volume (Float, optional, default 1.0) — output volume 0.0–1.0
     */
    class Speak(private val activity: FragmentActivity) : BridgeFunction {
        init {
            TTSEngine.initialize(activity.applicationContext)
        }

        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val text = parameters["text"] as? String
                ?: return BridgeResponse.error("INVALID_PARAMS", "Missing required parameter: text")
            val volume = (parameters["volume"] as? Number)?.toFloat() ?: 1.0f

            val queued = TTSEngine.speak(text, volume)
            return BridgeResponse.success(mapOf("queued" to queued, "text" to text))
        }
    }

    /**
     * Return engine readiness state.
     */
    class GetStatus(private val activity: FragmentActivity) : BridgeFunction {
        init {
            TTSEngine.initialize(activity.applicationContext)
        }

        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            return BridgeResponse.success(
                mapOf(
                    "ready" to TTSEngine.isReady,
                    "version" to "1.0.0"
                )
            )
        }
    }
}
