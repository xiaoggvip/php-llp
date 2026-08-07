<?php

declare(strict_types=1);

namespace PhpLLP\Embeddings\Generator;

use PhpLLP\Contracts\EmbeddingInterface;
use PhpLLP\Embeddings\Document;
use PhpLLP\Http\HttpClient;
use PhpLLP\Support\Json;

class OllamaEmbeddingGenerator implements EmbeddingInterface
{
    /** @var array<string, mixed> */
    private $config;

    /** @var HttpClient */
    private $httpClient;

    /** @var string */
    private $baseUrl;

    /** @var string */
    private $model;

    /**
     * @param array<string, mixed> $config
     * @param HttpClient $httpClient
     */
    public function __construct(array $config, HttpClient $httpClient)
    {
        $this->config = $config;
        $this->httpClient = $httpClient;
        $this->baseUrl = rtrim($config['base_url'] ?? 'http://localhost:11434/api', '/');
        $this->model = $config['model'] ?? 'llama2';
    }

    public function embed(string $text): array
    {
        return $this->embedBatch([$text])[0] ?? [];
    }

    public function embedBatch(array $texts): array
    {
        $result = [];
        foreach ($texts as $text) {
            $payload = [
                'model' => $this->model,
                'prompt' => $text,
            ];

            $response = $this->httpClient->post(
                $this->baseUrl . '/embeddings',
                ['Content-Type' => 'application/json'],
                $payload
            );

            $data = Json::decode($response->getBody());
            $result[] = $data['embedding'] ?? [];
        }

        return $result;
    }

    public function embedText(string $text, array $options = []): array
    {
        return $this->embed($text);
    }

    public function embedDocument(Document $document, array $options = []): Document
    {
        $embedding = $this->embed($document->getContent());
        $document->setEmbedding($embedding);
        return $document;
    }

    public function embedDocuments(array $documents, array $options = []): array
    {
        $texts = [];
        foreach ($documents as $doc) {
            $texts[] = $doc->getContent();
        }

        $embeddings = $this->embedBatch($texts);

        foreach ($documents as $i => $doc) {
            if (isset($embeddings[$i])) {
                $doc->setEmbedding($embeddings[$i]);
            }
        }

        return $documents;
    }
}