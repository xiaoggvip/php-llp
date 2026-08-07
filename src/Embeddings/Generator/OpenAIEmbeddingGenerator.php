<?php

declare(strict_types=1);

namespace PhpLLP\Embeddings\Generator;

use PhpLLP\Contracts\EmbeddingInterface;
use PhpLLP\Http\HttpClient;
use PhpLLP\Support\Json;

class OpenAIEmbeddingGenerator implements EmbeddingInterface
{
    /** @var array<string, mixed> */
    private $config;

    /** @var HttpClient */
    private $httpClient;

    /** @var string */
    private $apiKey;

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
        $this->apiKey = $config['api_key'] ?? '';
        $this->baseUrl = rtrim($config['base_url'] ?? 'https://api.openai.com/v1', '/');
        $this->model = $config['model'] ?? 'text-embedding-3-small';
    }

    public function embed(string $text): array
    {
        return $this->embedBatch([$text])[0] ?? [];
    }

    public function embedBatch(array $texts): array
    {
        $payload = [
            'model' => $this->model,
            'input' => $texts,
        ];

        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->apiKey,
        ];

        $response = $this->httpClient->post(
            $this->baseUrl . '/embeddings',
            $headers,
            $payload
        );

        $data = Json::decode($response->getBody());
        $embeddings = $data['data'] ?? [];

        $result = [];
        foreach ($embeddings as $item) {
            $result[] = $item['embedding'] ?? [];
        }

        return $result;
    }

    /**
     * @return string
     */
    public function getModel(): string
    {
        return $this->model;
    }
}