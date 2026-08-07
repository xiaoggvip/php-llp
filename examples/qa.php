<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use PhpLLP\LLP;
use PhpLLP\VectorStore\VectorStoreFactory;
use PhpLLP\Embeddings\Document;
use PhpLLP\Embeddings\Generator\OpenAIEmbeddingGenerator;
use PhpLLP\Http\HttpClient;

// Step 1: Initialize LLP
$llp = new LLP([
    'provider' => 'openai',
    'api_key' => getenv('OPENAI_API_KEY') ?: 'your-api-key',
    'model' => 'gpt-4o',
]);

// Step 2: Create a vector store and add knowledge base
$store = VectorStoreFactory::create('filesystem', [
    'collection' => 'knowledge_base',
    'path' => __DIR__ . '/../storage/vector_store',
]);

// Add documents to knowledge base
$knowledge = [
    ['content' => 'PHP 是一种广泛使用的开源服务器端脚本语言，特别适合 Web 开发。', 'metadata' => ['category' => 'programming']],
    ['content' => 'LLP (PHP Library for LLM) 是一个零依赖的 PHP LLM 应用开发库。', 'metadata' => ['category' => 'library']],
    ['content' => '向量数据库用于存储和检索高维向量嵌入，是 RAG 系统的核心组件。', 'metadata' => ['category' => 'concept']],
    ['content' => 'RAG (Retrieval-Augmented Generation) 结合了信息检索和文本生成。', 'metadata' => ['category' => 'concept']],
    ['content' => '嵌入模型将文本转换为向量表示，用于语义搜索和相似度计算。', 'metadata' => ['category' => 'concept']],
];

$httpClient = new HttpClient();
$embeddingProvider = new OpenAIEmbeddingGenerator([
    'api_key' => getenv('OPENAI_API_KEY') ?: 'your-api-key',
], $httpClient);

foreach ($knowledge as $item) {
    $embedding = $embeddingProvider->embed($item['content']);
    $doc = new Document([
        'content' => $item['content'],
        'embedding' => $embedding,
        'metadata' => $item['metadata'],
    ]);
    $store->addDocument($doc);
}

echo "知识库已建立，共 " . $store->count() . " 条文档\n\n";

// Step 3: Ask a question using RAG
$question = "什么是RAG？";
echo "问题: {$question}\n\n";

// Use LLP's ask method (RAG pipeline)
$result = $llp->ask($question, [
    'vector_store' => 'filesystem',
    'top_k' => 3,
    'threshold' => 0.3,
]);

echo "回答: {$result}\n\n";

// Another question
$question2 = "PHP和LLP有什么关系？";
echo "问题: {$question2}\n\n";

$result2 = $llp->ask($question2);
echo "回答: {$result2}\n";