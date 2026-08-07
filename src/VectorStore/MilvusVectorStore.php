<?php

declare(strict_types=1);

namespace PhpLLP\VectorStore;

use PhpLLP\Embeddings\Distances\Distance;
use PhpLLP\Embeddings\Document;
use PhpLLP\Http\HttpClient;
use PhpLLP\Support\Json;

class MilvusVectorStore extends VectorStoreBase
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
        $this->baseUrl = rtrim($config['base_url'] ?? 'http://localhost:19530', '/');
        $this->apiKey = $config['api_key'] ?? '';
    }

    public function initialize(): void
    {
        $distance = 'COSINE';
        if ($this->distanceMetric->getName() === 'euclidean') {
            $distance = 'L2';
        }

        $payload = [
            'collectionName' => $this->collectionName,
            'dimension' => $this->dimension,
            'metricType' => $distance,
            'description' => 'phpLLP vector collection',
            'autoID' => false,
        ];

        $this->httpClient->post(
            $this->baseUrl . '/v2/vectordb/collections/create',
            $this->getHeaders(),
            $payload
        );
    }

    public function addDocument(Document $document): string
    {
        $id = $document->getId();

        $payload = [
            'collectionName' => $this->collectionName,
            'data' => [
                [
                    'id' => $id,
                    'content' => $document->getContent(),
                    'embedding' => $document->getEmbedding(),
                    'metadata' => json_encode($document->getMetadata()),
                    'hash' => $document->getHash(),
                ],
            ],
        ];

        $this->httpClient->post(
            $this->baseUrl . '/v2/vectordb/entities/insert',
            $this->getHeaders(),
            $payload
        );

        return $id;
    }

    public function addDocuments(array $documents): array
    {
        $data = [];
        $ids = [];

        foreach ($documents as $document) {
            $id = $document->getId();
            $ids[] = $id;
            $data[] = [
                'id' => $id,
                'content' => $document->getContent(),
                'embedding' => $document->getEmbedding(),
                'metadata' => json_encode($document->getMetadata()),
                'hash' => $document->getHash(),
            ];
        }

        $this->httpClient->post(
            $this->baseUrl . '/v2/vectordb/entities/insert',
            $this->getHeaders(),
            [
                'collectionName' => $this->collectionName,
                'data' => $data,
            ]
        );

        return $ids;
    }

    public function delete(string $id): bool
    {
        $this->httpClient->post(
            $this->baseUrl . '/v2/vectordb/entities/delete',
            $this->getHeaders(),
            [
                'collectionName' => $this->collectionName,
                'filter' => 'id == "' . $id . '"',
            ]
        );
        return true;
    }

    public function deleteBatch(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        $expr = 'id in [' . implode(',', array_map(function ($id) { return '"' . $id . '"'; }, $ids)) . ']';

        $this->httpClient->post(
            $this->baseUrl . '/v2/vectordb/entities/delete',
            $this->getHeaders(),
            [
                'collectionName' => $this->collectionName,
                'filter' => $expr,
            ]
        );

        return count($ids);
    }

    public function getById(string $id): ?Document
    {
        $response = $this->httpClient->post(
            $this->baseUrl . '/v2/vectordb/entities/query',
            $this->getHeaders(),
            [
                'collectionName' => $this->collectionName,
                'filter' => 'id == "' . $id . '"',
                'outputFields' => ['content', 'metadata', 'hash'],
            ]
        );

        $data = Json::decode($response->getBody());
        $results = $data['data'] ?? [];

        if (empty($results)) {
            return null;
        }

        return $this->rowToDocument($results[0]);
    }

    public function list(array $filters = []): array
    {
        $payload = [
            'collectionName' => $this->collectionName,
            'limit' => 10000,
            'outputFields' => ['content', 'metadata', 'hash'],
        ];

        if (!empty($filters)) {
            $conditions = [];
            foreach ($filters as $key => $value) {
                $conditions[] = 'metadata LIKE "%' . $value . '%"';
            }
            $payload['filter'] = implode(' and ', $conditions);
        }

        $response = $this->httpClient->post(
            $this->baseUrl . '/v2/vectordb/entities/query',
            $this->getHeaders(),
            $payload
        );

        $data = Json::decode($response->getBody());
        $results = $data['data'] ?? [];

        $documents = [];
        foreach ($results as $row) {
            $documents[] = $this->rowToDocument($row);
        }

        return $documents;
    }

    public function similaritySearch(
        array $queryVector,
        int $topK = 5,
        float $threshold = 0.0,
        array $filters = []
    ): array {
        $payload = [
            'collectionName' => $this->collectionName,
            'data' => [$queryVector],
            'annsField' => 'embedding',
            'limit' => $topK,
            'outputFields' => ['content', 'metadata', 'hash'],
        ];

        if (!empty($filters)) {
            $conditions = [];
            foreach ($filters as $key => $value) {
                $conditions[] = 'metadata LIKE "%' . $value . '%"';
            }
            $payload['filter'] = implode(' and ', $conditions);
        }

        if ($threshold > 0) {
            $payload['scoreThreshold'] = $threshold;
        }

        $response = $this->httpClient->post(
            $this->baseUrl . '/v2/vectordb/entities/search',
            $this->getHeaders(),
            $payload
        );

        $data = Json::decode($response->getBody());
        $results = $data['data'] ?? [];

        $searchResults = [];
        foreach ($results as $row) {
            $searchResults[] = [
                'document' => $this->rowToDocument($row),
                'score' => $row['distance'] ?? 0,
            ];
        }

        return $searchResults;
    }

    public function count(): int
    {
        $response = $this->httpClient->post(
            $this->baseUrl . '/v2/vectordb/collections/describe',
            $this->getHeaders(),
            ['collectionName' => $this->collectionName]
        );

        $data = Json::decode($response->getBody());
        return (int)($data['entityCount'] ?? 0);
    }

    public function clear(): void
    {
        $this->httpClient->post(
            $this->baseUrl . '/v2/vectordb/collections/drop',
            $this->getHeaders(),
            ['collectionName' => $this->collectionName]
        );
        $this->initialize();
    }

    /**
     * @return array<string, string>
     */
    private function getHeaders(): array
    {
        $headers = ['Content-Type' => 'application/json'];
        if ($this->apiKey) {
            $headers['Authorization'] = 'Bearer ' . $this->apiKey;
        }
        return $headers;
    }

    /**
     * @param array<string, mixed> $row
     * @return Document
     */
    private function rowToDocument(array $row): Document
    {
        $metadata = [];
        if (isset($row['metadata'])) {
            $decoded = json_decode($row['metadata'], true);
            $metadata = is_array($decoded) ? $decoded : [];
        }

        return new Document([
            'id' => $row['id'] ?? '',
            'content' => $row['content'] ?? '',
            'embedding' => isset($row['embedding']) && is_array($row['embedding']) ? $row['embedding'] : [],
            'metadata' => $metadata,
            'hash' => $row['hash'] ?? '',
        ]);
    }
}