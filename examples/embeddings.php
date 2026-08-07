<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use PhpLLP\LLP;
use PhpLLP\Embeddings\Generator\EmbeddingGenerator;
use PhpLLP\Embeddings\Generator\OpenAIEmbeddingGenerator;
use PhpLLP\Embeddings\Splitter\DocumentSplitter;
use PhpLLP\Embeddings\Document;
use PhpLLP\Http\HttpClient;

// Using LLP facade
$llp = new LLP([
    'provider' => 'openai',
    'api_key' => getenv('OPENAI_API_KEY') ?: 'your-api-key',
]);

$embedding = $llp->embed('你好世界');
echo "嵌入向量维度: " . count($embedding) . "\n";
echo "前5个值: " . implode(', ', array_slice($embedding, 0, 5)) . "\n\n";

// Advanced: document processing pipeline
$httpClient = new HttpClient();
$embeddingProvider = new OpenAIEmbeddingGenerator([
    'api_key' => getenv('OPENAI_API_KEY') ?: 'your-api-key',
], $httpClient);

$generator = new EmbeddingGenerator($embeddingProvider);

// Split long text into chunks
$longText = "这是一段很长的文本...\n\n" . str_repeat("段落内容。\n\n", 50);
$splitter = new DocumentSplitter(500, 50);
$chunks = $splitter->splitText($longText);
echo "分割后的文档数量: " . count($chunks) . "\n";

// Generate embeddings for all chunks
$documents = $generator->generateBatch($chunks);
echo "成功生成嵌入的文档数量: " . count($documents) . "\n";

foreach ($documents as $doc) {
    echo "  - {$doc->getId()}: 嵌入维度 " . count($doc->getEmbedding()) . "\n";
}