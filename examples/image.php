<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use PhpLLP\LLP;

$llp = new LLP([
    'provider' => 'openai',
    'api_key' => getenv('OPENAI_API_KEY') ?: 'your-api-key',
]);

// Generate a single image
$result = $llp->image('A beautiful sunset over the ocean, with golden clouds');
echo "单张图片: " . json_encode($result, JSON_PRETTY_PRINT) . "\n\n";

// Generate with custom options
$result = $llp->image('A futuristic city at night', [
    'size' => '1024x1024',
    'style' => 'vivid',
    'n' => 2,
]);
echo "多张图片: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";