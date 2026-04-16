## nbucic/audio-tts

An Audio TTS plugin for Android

### Installation

```bash
composer require nbucic/audio-tts
```

### PHP Usage (Livewire/Blade)

Use the `AudioTTS` facade:

@verbatim
<code-snippet name="Using AudioTTS Facade" lang="php">
use Nbucic\AudioTts\Facades\AudioTTS;

// Execute the plugin functionality
$result = AudioTTS::execute(['option1' => 'value']);

// Get the current status
$status = AudioTTS::getStatus();
</code-snippet>
@endverbatim

### Available Methods

- `AudioTTS::execute()`: Execute the plugin functionality
- `AudioTTS::getStatus()`: Get the current status

### Events

- `AudioTTSCompleted`: Listen with `#[OnNative(AudioTTSCompleted::class)]`

@verbatim
<code-snippet name="Listening for AudioTTS Events" lang="php">
use Native\Mobile\Attributes\OnNative;
use Nbucic\AudioTts\Events\AudioTTSCompleted;

#[OnNative(AudioTTSCompleted::class)]
public function handleAudioTTSCompleted($result, $id = null)
{
    // Handle the event
}
</code-snippet>
@endverbatim

### JavaScript Usage (Vue/React/Inertia)

@verbatim
<code-snippet name="Using AudioTTS in JavaScript" lang="javascript">
import { audioTTS } from '@nbucic/audio-tts';

// Execute the plugin functionality
const result = await audioTTS.execute({ option1: 'value' });

// Get the current status
const status = await audioTTS.getStatus();
</code-snippet>
@endverbatim