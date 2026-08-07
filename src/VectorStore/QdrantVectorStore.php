<?php

declare(strict_types=1);

namespace PhpLLP\VectorStore;

use PhpLLP\Embeddings\Distances\Distance;
use PhpLLP\Embeddings\Document;
use PhpLLP\Http\HttpClient;
use PhpLLP\Support\Json;

class QdrantVectorStore extends VectorStoreBase
{
    /** @var array<string, mixed> */
    private $config;

    /** @var HttpClient */
    private $httpClient;

    /** @var string */
    private $baseUrl;

    /** @var string */
    private $apiKey;

    /**
     * @param string $collectionName
     * @param Distance|null $distanceMetric
     * @param int $dimension
     * @param array<string, mixed> $config
     */
    public function __construct(
        string $collectionName = 'default',
        Distance $distanceMetric = null,
        int $dimension = 1536,
        array $config = []
    ) {
        parent::__construct($collectionName, $distanceMetric, $dimension);
        $this->config = $config;
        $this->httpClient = new HttpClient();
        $this->baseUrl = rtrim($config['base_url'] ?? 'http://localhost:6333', '/');
        $this->apiKey = $config['api_key'] ?? '';
    }

    public function initialize(): void
    {
        $distance = 'Cosine';
        if ($this->distanceMetric->getName() === 'euclidean') {
            $distance = 'Euclid';
        }

        $payload = [
            'vectors' => [
                'size' => $this->dimension,
                'distance' => $distance,
            ],
        ];

        $headers = ['Content-Type' => 'application/json'];
        if ($this->apiKey) {
            $headers['api-key'] = $this->apiKey;
        }

        $this->httpClient->put(
            $this->baseUrl . '/collections/' . $this->collectionName,
            $headers,
            $payload
        );
    }

    public function addDocument(Document $document): string
    {
        $id = $document->getId();
        $payload = [
            'points' => [
                [
                    'id' => $id,
                    'vector' => $document->getEmbedding(),
                    'payload' => [
                        'content' => $document->getContent(),
                        'metadata' => $document->getMetadata(),
                        'hash' => $document->getHash(),
                    ],
                ],
            ],
        ];

        $headers = ['Content-Type' => 'application/json'];
        if ($this->apiKey) {
            $headers['api-key'] = $this->apiKey;
        }

        $this->httpClient->put(
            $this->baseUrl . '/collections/' . $this->collectionName . '/points',
            $headers,
            $payload
        );

        return $id;
    }

    public function addDocuments(array $documents): array
    {
        $points = [];
        $ids = [];

        foreach ($documents as $document) {
            $id = $document->getId();
            $ids[] = $id;
            $points[] = [
                'id' => $id,
                'vector' => $document->getEmbedding(),
                'payload' => [
                    'content' => $document->getContent(),
                    'metadata' => $document->getMetadata(),
                    'hash' => $document->getHash(),
                ],
            ];
        }

        $headers = ['Content-Type' => 'application/json'];
        if ($this->apiKey) {
            $headers['api-key'] = $this->apiKey;
        }

        $this->httpClient->put(
            $this->baseUrl . '/collections/' . $this->collectionName . '/points',
            $headers,
            ['points' => $points]
        );

        return $ids;
    }

    public function delete(string $id): bool
    {
        $headers = ['Content-Type' => 'application/json'];
        if ($this->apiKey) {
            $headers['api-key'] = $this->apiKey;
        }

        $this->httpClient->delete(
            $this->baseUrl . '/collections/' . $this->collectionName . '/points/' . $id,
            $headers
        );

        return true;
    }

    public function deleteBatch(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        $headers = ['Content-Type' => 'application/json'];
        if ($this->apiKey) {
            $headers['api-key'] = $this->apiKey;
        }

        $this->httpClient->post(
            $this->baseUrl . '/collections/' . $this->collectionName . '/points/delete',
            $headers,
            ['points' => $ids]
        );

        return count($ids);
    }

    public function getById(string $id): ?Document
    {
        $headers = [];
        if ($this->apiKey) {
            $headers['api-key'] = $this->apiKey;
        }

        $response = $this->httpClient->get(
            $this->baseUrl . '/collections/' . $this->collectionName . '/points/' . $id,
            $headers
        );

        $data = Json::decode($response->getBody());
        $result = $data['result'] ?? null;

        if (!$result) {
            return null;
        }

        return $this->pointToDocument($result);
    }

    public function list(array $filters = []): array
    {
        $headers = ['Content-Type' => 'application/json'];
        if ($this->apiKey) {
            $headers['api-key'] = $this->apiKey;
        }

        $payload = [
            'limit' => 10000,
        ];

        if (!empty($filters)) {
            $conditions = [];
            foreach ($filters as $key => $value) {
                $conditions[] = [
                    'key' => 'metadata.' . $key,
                    'match' => ['value' => $value],
                ];
            }
            $payload['filter'] = ['must' => $conditions];
        }

        $response = $this->httpClient->post(
            $this->baseUrl . '/collections/' . $this->collectionName . '/points/scroll',
            $headers,
            $payload
        );

        $data = Json::decode($response->getBody());
        $points = $data['result']['points'] ?? [];

        $documents = [];
        foreach ($points as $point) {
            $documents[] = $this->pointToDocument($point);
        }

        return $documents;
    }

    public function similaritySearch(
        array $queryVector,
        int $topK = 5,
        float $threshold = 0.0,
        array $filters = []
    ): array {
        $headers = ['Content-Type' => 'application/json'];
        if ($this->apiKey) {
            $headers['api-key'] = $this->apiKey;
        }

        $payload = [
            'vector' => $queryVector,
            'limit' => $topK,
            'score_threshold' => $threshold,
        ];

        if (!empty($filters)) {
            $conditions = [];
            foreach ($filters as $key => $value) {
                $conditions[] = [
                    'key' => 'metadata.' . $key,
                    'match' => ['value' => $value],
                ];
            }
            $payload['filter'] = ['must' => $conditions];
        }

        $response = $this->httpClient->post(
            $this->baseUrl . '/collections/' . $this->collectionName . '/points/search',
            $headers,
            $payload
        );

        $data = Json::decode($response->getBody());
        $results = $data['result'] ?? [];

        $searchResults = [];
        foreach ($results as $point) {
            $searchResults[] = [
                'document' => $this->pointToDocument($point),
                'score' => $point['score'] ?? 0,
            ];
        }

        return $searchResults;
    }

    public function count(): int
    {
        $headers = [];
        if ($this->apiKey) {
            $headers['api-key'] = $this->apiKey;
        }

        $response = $this->httpClient->get(
            $this->baseUrl . '/collections/' . $this->collectionName,
            $headers
        );

        $data = Json::decode($response->getBody());
        return (int)($data['result']['points_count'] ?? 0);
    }

    public function clear(): void
    {
        $headers = [];
        if ($this->apiKey) {
            $headers['api-key'] = $this->apiKey;
        }

        $this->httpClient->delete(
            $this->baseUrl . '/collections/' . $this->collectionName,
            $headers
        );

        $this->initialize();
    }

    /**
     * @param array<string, mixed> $point
     * @return Document
     */
    private function pointToDocument(array $point): Document
    {
        $payload = $point['payload'] ?? [];
        return new Document([
            'id' => $point['id'] ?? '',
            'content' => $payload['content'] ?? '',
            'embedding' => $point['vector'] ?? [],
            'metadata' => $payload['metadata'] ?? [],
            'hash' => $payload['hash'] ?? '',
        ]);
    }
}