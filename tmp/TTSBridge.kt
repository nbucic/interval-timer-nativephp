package com.nativephp.mobile.bridge

import android.content.Context
import android.media.MediaPlayer
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.speech.tts.TextToSpeech
import android.speech.tts.UtteranceProgressListener
import android.util.Log
import android.webkit.JavascriptInterface
import java.io.File
import java.util.Locale
import java.util.concurrent.ConcurrentHashMap

/**
 * JavaScript interface that exposes Android TextToSpeech to the WebView.
 *
 * Fixed phrases are pre-synthesized to WAV files in the app cache directory
 * on first use and replayed via MediaPlayer on subsequent calls, eliminating
 * TTS engine startup latency during workouts.
 *
 * Cache TTL: CACHE_DAYS days. Stale or missing files are rebuilt automatically.
 *
 * Registered as window.AndroidTTS in the WebView.
 *
 * Threading notes:
 *   - @JavascriptInterface methods are called on a binder thread.
 *   - MediaPlayer playback is dispatched to the main thread via mainHandler.
 *   - TextToSpeech callbacks arrive on the main thread.
 */
class TTSBridge(private val context: Context) {

    private companion object {
        const val TAG = "TTSBridge"
        const val CACHE_DAYS = 3L

        val KNOWN_PHRASES = listOf(
            "Done", "Go", "Next", "Get ready",
            "3", "2", "1",
            "Rest", "Work", "Prepare",
        )
    }

    private val mainHandler = Handler(Looper.getMainLooper())
    private var tts: TextToSpeech? = null
    private val players = ConcurrentHashMap<String, MediaPlayer>()
    private var engineReady = false

    init {
        tts = TextToSpeech(context) { status ->
            if (status == TextToSpeech.SUCCESS) {
                tts?.language = Locale.US
                tts?.setPitch(0.9f)
                tts?.setSpeechRate(0.85f)
                engineReady = true
                setupUtteranceListener()
                prebuildCache()
                Log.d(TAG, "[TTS] engine ready")
            } else {
                Log.e(TAG, "[TTS] engine init failed with status $status")
            }
        }
    }

    private fun cacheFile(phrase: String): File {
        val safeName = phrase.lowercase().replace(Regex("[^a-z0-9]"), "_")
        return File(context.cacheDir, "tts_$safeName.wav")
    }

    private fun isCacheStale(file: File): Boolean {
        if (!file.exists()) return true
        val cutoffMs = System.currentTimeMillis() - CACHE_DAYS * 86_400_000L
        return file.lastModified() < cutoffMs
    }

    private fun setupUtteranceListener() {
        tts?.setOnUtteranceProgressListener(object : UtteranceProgressListener() {
            override fun onDone(utteranceId: String) {
                val file = cacheFile(utteranceId)
                if (file.exists() && file.length() > 0) {
                    try {
                        val player = MediaPlayer().apply {
                            setDataSource(file.absolutePath)
                            prepare()
                        }
                        players[utteranceId] = player
                        Log.d(TAG, "[TTS] cached and loaded player for: $utteranceId")
                    } catch (e: Exception) {
                        Log.e(TAG, "[TTS] failed to load MediaPlayer for $utteranceId: ${e.message}")
                    }
                }
            }
            override fun onError(utteranceId: String) {
                Log.e(TAG, "[TTS] synthesis error for: $utteranceId")
            }
            override fun onStart(utteranceId: String) {}
        })
    }

    private fun prebuildCache() {
        Log.d(TAG, "[TTS] prebuildCache: pre-building ${KNOWN_PHRASES.size} phrases")
        KNOWN_PHRASES.forEach { phrase ->
            val file = cacheFile(phrase)
            if (isCacheStale(file)) {
                Log.d(TAG, "[TTS] synthesizing to file: $phrase")
                val params = Bundle()
                tts?.synthesizeToFile(phrase, params, file, phrase)
            } else {
                try {
                    players[phrase] = MediaPlayer().apply {
                        setDataSource(file.absolutePath)
                        prepare()
                    }
                    Log.d(TAG, "[TTS] loaded cached player for: $phrase")
                } catch (e: Exception) {
                    Log.e(TAG, "[TTS] corrupt cache for $phrase, re-synthesizing: ${e.message}")
                    file.delete()
                    val params = Bundle()
                    tts?.synthesizeToFile(phrase, params, file, phrase)
                }
            }
        }
    }

    @JavascriptInterface
    fun speak(text: String) {
        Log.d(TAG, "[TTS] speak() entry: \"$text\"")

        val player = players[text]
        if (player != null) {
            mainHandler.post {
                try {
                    player.seekTo(0)
                    player.start()
                    Log.d(TAG, "[TTS] playing cached TTS: $text")
                } catch (e: Exception) {
                    Log.w(TAG, "[TTS] cached player failed for '$text', falling back: ${e.message}")
                    players.remove(text)
                    speakLive(text)
                }
            }
            return
        }

        Log.d(TAG, "[TTS] cache miss for \"$text\", calling speakLive()")
        speakLive(text)
    }

    private fun speakLive(text: String) {
        if (tts == null) {
            Log.e(TAG, "[TTS] speakLive: tts is null, cannot speak \"$text\"")
            return
        }
        if (engineReady) {
            Log.d(TAG, "[TTS] live TTS: $text")
            tts?.speak(text, TextToSpeech.QUEUE_FLUSH, null, null)
        } else {
            Log.w(TAG, "[TTS] engine not ready, dropping: $text")
        }
    }

    fun shutdown() {
        mainHandler.post {
            players.values.forEach {
                try { it.release() } catch (_: Exception) {}
            }
            players.clear()
        }
        tts?.shutdown()
        tts = null
        engineReady = false
    }
}
