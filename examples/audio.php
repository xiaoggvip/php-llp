<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use PhpLLP\LLP;

$llp = new LLP([
    'provider' => 'openai',
    'api_key' => getenv('OPENAI_API_KEY') ?: 'your-api-key',
]);

// Transcribe audio file
$result = $llp->transcribe('/path/to/audio.mp3');
echo "语音转文字: " . json_encode($result, JSON_PRETTY_PRINT) . "\n\n";

// With options
$result = $llp->transcribe('/path/to/audio.mp3', [
    'model' => 'whisper-1',
    'language' => 'zh',
]);
echo "指定语言: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";