<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use PhpLLP\LLP;

$llp = new LLP([
    'provider' => 'openai',
    'api_key' => getenv('OPENAI_API_KEY') ?: 'your-api-key',
    'model' => 'gpt-4o',
]);

// Simple chat
$response = $llp->chat('你好，请介绍一下自己');
echo "简单对话: {$response}\n\n";

// Multi-turn conversation
$messages = [
    ['role' => 'system', 'content' => '你是一个有用的助手'],
    ['role' => 'user', 'content' => '什么是PHP？'],
    ['role' => 'assistant', 'content' => 'PHP是一种流行的服务器端脚本语言。'],
    ['role' => 'user', 'content' => '它有什么特点？'],
];

$response = $llp->conversation($messages);
echo "多轮对话: {$response}\n\n";

// Stream chat
foreach ($llp->chatStream('写一首关于春天的诗') as $chunk) {
    echo $chunk;
}
echo "\n\n";