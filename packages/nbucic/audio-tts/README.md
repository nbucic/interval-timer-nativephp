# AudioTTS Plugin for NativePHP Mobile

An Audio TTS plugin for Android

## Installation

```bash
composer require nbucic/audio-tts
```

## Usage

```php
use Nbucic\AudioTts\Facades\AudioTTS;

// Execute functionality
$result = AudioTTS::execute(['option1' => 'value']);

// Get status
$status = AudioTTS::getStatus();
```

## Listening for Events

```php
use Livewire\Attributes\On;

#[On('native:Nbucic\AudioTts\Events\AudioTTSCompleted')]
public function handleAudioTTSCompleted($result, $id = null)
{
    // Handle the event
}
```

## License

MIT