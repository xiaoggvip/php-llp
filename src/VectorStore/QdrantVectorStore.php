<?php

declare(strict_types=1);

namespace PhpLLP\VectorStore;

use PhpLLP\Embeddings\Distances\Distance;
use PhpLLP\Embeddings\Document;
use PhpLLP\Http\HttpClient;
use PhpLLP\Http\HttpResponse;
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

    /** @var bool */
    private $initialized = false;

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

        $response = $this->httpClient->put(
            $this->baseUrl . '/collections/' . $this->collectionName,
            $headers,
            $payload
        );
        $this->checkHttpResponse($response, 'initialize collection');
    }

    public function addDocument(Document $document): string
    {
        $this->ensureInitialized();
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

        $response = $this->httpClient->put(
            $this->baseUrl . '/collections/' . $this->collectionName . '/points',
            $headers,
            $payload
        );
        $this->checkHttpResponse($response, 'addDocument');

        return $id;
    }

    public function addDocuments(array $documents): array
    {
        $this->ensureInitialized();
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

        $response = $this->httpClient->put(
            $this->baseUrl . '/collections/' . $this->collectionName . '/points',
            $headers,
            ['points' => $points]
        );
        $this->checkHttpResponse($response, 'addDocuments');

        return $ids;
    }

    public function delete(string $id): bool
    {
        $headers = ['Content-Type' => 'application/json'];
        if ($this->apiKey) {
            $headers['api-key'] = $this->apiKey;
        }

        $response = $this->httpClient->delete(
            $this->baseUrl . '/collections/' . $this->collectionName . '/points/' . $id,
            $headers
        );
        $this->checkHttpResponse($response, 'delete');

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

        $response = $this->httpClient->post(
            $this->baseUrl . '/collections/' . $this->collectionName . '/points/delete',
            $headers,
            ['points' => $ids]
        );
        $this->checkHttpResponse($response, 'deleteBatch');

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
        $this->checkHttpResponse($response, 'getById');
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
        $this->checkHttpResponse($response, 'list');
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
        $this->checkHttpResponse($response, 'similaritySearch');
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
        $this->checkHttpResponse($response, 'count');
        $data = Json::decode($response->getBody());
        return (int)($data['result']['points_count'] ?? 0);
    }

    /**
     * 确保集合已初始化，如果不存在则自动创建
     */
    private function ensureInitialized(): void
    {
        if ($this->initialized) {
            return;
        }

        try {
            $headers = [];
            if ($this->apiKey) {
                $headers['api-key'] = $this->apiKey;
            }

            $response = $this->httpClient->get(
                $this->baseUrl . '/collections/' . $this->collectionName,
                $headers
            );

            if ($response->isSuccess()) {
                $data = Json::decode($response->getBody());
                if (isset($data['result'])) {
                    $this->initialized = true;
                    return;
                }
            }
        } catch (\Exception $e) {
        }

        $this->initialize();
        $this->initialized = true;
    }

    public function clear(): void
    {
        $headers = [];
        if ($this->apiKey) {
            $headers['api-key'] = $this->apiKey;
        }

        $response = $this->httpClient->delete(
            $this->baseUrl . '/collections/' . $this->collectionName,
            $headers
        );
        $this->checkHttpResponse($response, 'clear');
        $this->initialized = false;
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