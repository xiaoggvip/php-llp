<?php

declare(strict_types=1);

namespace PhpLLP\VectorStore;

use PhpLLP\Embeddings\Distances\Distance;
use PhpLLP\Embeddings\Document;
use PhpLLP\Http\HttpClient;
use PhpLLP\Support\Json;

class ElasticsearchVectorStore extends VectorStoreBase
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
        $this->baseUrl = rtrim($config['base_url'] ?? 'http://localhost:9200', '/');
        $this->apiKey = $config['api_key'] ?? '';
    }

    public function initialize(): void
    {
        $distance = 'cosine';
        if ($this->distanceMetric->getName() === 'euclidean') {
            $distance = 'l2_norm';
        }

        $payload = [
            'settings' => [
                'number_of_shards' => 1,
                'number_of_replicas' => 0,
            ],
            'mappings' => [
                'properties' => [
                    'content' => ['type' => 'text'],
                    'metadata' => ['type' => 'object', 'enabled' => true],
                    'hash' => ['type' => 'keyword'],
                    'vector' => [
                        'type' => 'dense_vector',
                        'dims' => $this->dimension,
                        'index' => true,
                        'similarity' => $distance,
                    ],
                ],
            ],
        ];

        $headers = $this->getHeaders();
        $this->httpClient->put(
            $this->baseUrl . '/' . $this->collectionName,
            $headers,
            $payload
        );
    }

    public function addDocument(Document $document): string
    {
        $this->ensureInitialized();
        $id = $document->getId();
        $payload = [
            'content' => $document->getContent(),
            'metadata' => $document->getMetadata(),
            'hash' => $document->getHash(),
            'vector' => $document->getEmbedding(),
        ];

        $this->httpClient->put(
            $this->baseUrl . '/' . $this->collectionName . '/_doc/' . $id,
            $this->getHeaders(),
            $payload
        );

        return $id;
    }

    public function addDocuments(array $documents): array
    {
        $this->ensureInitialized();
        $ids = [];
        $bulkPayload = '';

        foreach ($documents as $document) {
            $id = $document->getId();
            $ids[] = $id;

            $bulkPayload .= json_encode([
                'index' => [
                    '_index' => $this->collectionName,
                    '_id' => $id,
                ],
            ]) . "\n";

            $bulkPayload .= json_encode([
                'content' => $document->getContent(),
                'metadata' => $document->getMetadata(),
                'hash' => $document->getHash(),
                'vector' => $document->getEmbedding(),
            ]) . "\n";
        }

        if (!empty($bulkPayload)) {
            $this->httpClient->post(
                $this->baseUrl . '/_bulk',
                $this->getHeaders(['Content-Type' => 'application/x-ndjson']),
                $bulkPayload
            );
        }

        return $ids;
    }

    public function delete(string $id): bool
    {
        $this->httpClient->delete(
            $this->baseUrl . '/' . $this->collectionName . '/_doc/' . $id,
            $this->getHeaders()
        );
        return true;
    }

    public function deleteBatch(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        $bulkPayload = '';
        foreach ($ids as $id) {
            $bulkPayload .= json_encode([
                'delete' => [
                    '_index' => $this->collectionName,
                    '_id' => $id,
                ],
            ]) . "\n";
        }

        $this->httpClient->post(
            $this->baseUrl . '/_bulk',
            $this->getHeaders(['Content-Type' => 'application/x-ndjson']),
            $bulkPayload
        );

        return count($ids);
    }

    public function getById(string $id): ?Document
    {
        $response = $this->httpClient->get(
            $this->baseUrl . '/' . $this->collectionName . '/_doc/' . $id,
            $this->getHeaders()
        );

        $data = Json::decode($response->getBody());
        if (empty($data['found'])) {
            return null;
        }

        return $this->sourceToDocument($data['_source'] ?? [], $data['_id'] ?? $id);
    }

    public function list(array $filters = []): array
    {
        $query = ['match_all' => new \stdClass()];

        if (!empty($filters)) {
            $must = [];
            foreach ($filters as $key => $value) {
                $must[] = [
                    'term' => ['metadata.' . $key => $value],
                ];
            }
            $query = ['bool' => ['must' => $must]];
        }

        $response = $this->httpClient->post(
            $this->baseUrl . '/' . $this->collectionName . '/_search',
            $this->getHeaders(),
            ['query' => $query, 'size' => 10000]
        );

        $data = Json::decode($response->getBody());
        $hits = $data['hits']['hits'] ?? [];

        $documents = [];
        foreach ($hits as $hit) {
            $documents[] = $this->sourceToDocument($hit['_source'] ?? [], $hit['_id'] ?? '');
        }

        return $documents;
    }

    public function similaritySearch(
        array $queryVector,
        int $topK = 5,
        float $threshold = 0.0,
        array $filters = []
    ): array {
        $must = [];

        if (!empty($filters)) {
            foreach ($filters as $key => $value) {
                $must[] = [
                    'term' => ['metadata.' . $key => $value],
                ];
            }
        }

        $knn = [
            'field' => 'vector',
            'query_vector' => $queryVector,
            'k' => $topK,
            'num_candidates' => $topK * 4,
        ];

        if (!empty($must)) {
            $knn['filter'] = ['bool' => ['must' => $must]];
        }

        $payload = [
            'knn' => $knn,
            'size' => $topK,
        ];

        if ($threshold > 0) {
            $payload['min_score'] = $threshold;
        }

        $response = $this->httpClient->post(
            $this->baseUrl . '/' . $this->collectionName . '/_search',
            $this->getHeaders(),
            $payload
        );

        $data = Json::decode($response->getBody());
        $hits = $data['hits']['hits'] ?? [];

        $results = [];
        foreach ($hits as $hit) {
            $results[] = [
                'document' => $this->sourceToDocument($hit['_source'] ?? [], $hit['_id'] ?? ''),
                'score' => (float)($hit['_score'] ?? 0),
            ];
        }

        return $results;
    }

    public function count(): int
    {
        $response = $this->httpClient->get(
            $this->baseUrl . '/' . $this->collectionName . '/_count',
            $this->getHeaders()
        );

        $data = Json::decode($response->getBody());
        return (int)($data['count'] ?? 0);
    }

    /**
     * 确保索引已初始化，如果不存在则自动创建
     */
    private function ensureInitialized(): void
    {
        if ($this->initialized) {
            return;
        }

        try {
            $response = $this->httpClient->get(
                $this->baseUrl . '/' . $this->collectionName,
                $this->getHeaders()
            );
            $data = Json::decode($response->getBody());
            if (isset($data[$this->collectionName])) {
                $this->initialized = true;
                return;
            }
        } catch (\Exception $e) {
        }

        $this->initialize();
        $this->initialized = true;
    }

    public function clear(): void
    {
        $this->httpClient->delete(
            $this->baseUrl . '/' . $this->collectionName,
            $this->getHeaders()
        );
        $this->initialized = false;
    }

    /**
     * @param array<string, string> $extra
     * @return array<string, string>
     */
    private function getHeaders(array $extra = []): array
    {
        $headers = array_merge(['Content-Type' => 'application/json'], $extra);
        if ($this->apiKey) {
            $headers['Authorization'] = 'ApiKey ' . $this->apiKey;
        }
        return $headers;
    }

    /**
     * @param array<string, mixed> $source
     * @param string $id
     * @return Document
     */
    private function sourceToDocument(array $source, string $id): Document
    {
        return new Document([
            'id' => $id,
            'content' => $source['content'] ?? '',
            'embedding' => $source['vector'] ?? [],
            'metadata' => $source['metadata'] ?? [],
            'hash' => $source['hash'] ?? '',
        ]);
    }
}