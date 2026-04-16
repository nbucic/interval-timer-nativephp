<?php

namespace Nbucic\AudioTts;

class AudioTTS
{
    /**
     * Get the current status
     */
    public function getStatus(): ?object
    {
        if (function_exists('nativephp_call')) {
            $result = nativephp_call('AudioTTS.GetStatus', '{}');

            if ($result) {
                $decoded = json_decode($result);
                return $decoded->data ?? null;
            }
        }

        return null;
    }

    /**
     * Execute the plugin functionality
     */
    public function speak(string $text, float $volume = 1.0): mixed
    {
        if (function_exists('nativephp_call')) {
            $result = nativephp_call('AudioTTS.Speak', json_encode(['text' => $text, 'volume' => $volume]));

            if ($result) {
                $decoded = json_decode($result);
                return $decoded->data ?? null;
            }
        }

        return null;
    }
}
