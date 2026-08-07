<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use PhpLLP\VectorStore\VectorStoreFactory;
use PhpLLP\Embeddings\Document;
use PhpLLP\Embeddings\Distances\CosineDistance;

// Create a filesystem-based vector store
$store = VectorStoreFactory::create('filesystem', [
    'collection' => 'my_documents',
    'path' => __DIR__ . '/../storage/vector_store',
    'dimension' => 3, // For demo purposes, use small dimension
]);

// Add documents with embeddings
$documents = [
    new Document([
        'id' => 'doc1',
        'content' => '苹果是一种水果，富含维生素C',
        'embedding' => [0.9, 0.1, 0.5],
        'metadata' => ['category' => 'fruit'],
    ]),
    new Document([
        'id' => 'doc2',
        'content' => '香蕉含有丰富的钾元素',
        'embedding' => [0.8, 0.2, 0.4],
        'metadata' => ['category' => 'fruit'],
    ]),
    new Document([
        'id' => 'doc3',
        'content' => ' PHP 是一种编程语言',
        'embedding' => [0.1, 0.9, 0.3],
        'metadata' => ['category' => 'tech'],
    ]),
    new Document([
        'id' => 'doc4',
        'content' => 'Python 适合数据分析和人工智能',
        'embedding' => [0.2, 0.85, 0.35],
        'metadata' => ['category' => 'tech'],
    ]),
];

$ids = $store->addDocuments($documents);
echo "添加文档: " . implode(', ', $ids) . "\n";
echo "文档总数: " . $store->count() . "\n\n";

// Search by vector similarity
$queryVector = [0.85, 0.15, 0.45]; // Similar to fruit documents
$results = $store->similaritySearch($queryVector, 2, 0.5);

echo "搜索结果 (Top 2):\n";
foreach ($results as $result) {
    echo "  - [{$result['score']}]: {$result['document']->getContent()}\n";
}

// Search with filters
$results = $store->similaritySearch($queryVector, 5, 0.0, ['category' => 'tech']);
echo "\n技术类文档搜索结果:\n";
foreach ($results as $result) {
    echo "  - [{$result['score']}]: {$result['document']->getContent()}\n";
}

// Get document by ID
$doc = $store->getById('doc1');
if ($doc) {
    echo "\n文档详情: {$doc->getContent()}\n";
}

// Clean up
$store->clear();
echo "\n已清空存储，文档总数: " . $store->count() . "\n";