<?php

declare(strict_types=1);

namespace PhpLLP\VectorStore;

use PhpLLP\Embeddings\Distances\Distance;
use PhpLLP\Embeddings\Document;
use PhpLLP\Http\HttpClient;
use PhpLLP\Support\Json;

class ChromaDBVectorStore extends VectorStoreBase
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
        $this->baseUrl = rtrim($config['base_url'] ?? 'http://localhost:8000', '/');
        $this->apiKey = $config['api_key'] ?? '';
    }

    public function initialize(): void
    {
        $distance = 'cosine';
        if ($this->distanceMetric->getName() === 'euclidean') {
            $distance = 'l2';
        }

        $payload = [
            'name' => $this->collectionName,
            'metadata' => [
                'hnsw:space' => $distance,
            ],
        ];

        $this->httpClient->post(
            $this->baseUrl . '/api/v2/collections',
            $this->getHeaders(),
            $payload
        );
    }

    public function addDocument(Document $document): string
    {
        $this->ensureInitialized();
        $id = $document->getId();

        $payload = [
            'ids' => [$id],
            'embeddings' => [$document->getEmbedding()],
            'documents' => [$document->getContent()],
            'metadatas' => [$document->getMetadata()],
        ];

        $this->httpClient->post(
            $this->baseUrl . '/api/v2/collections/' . $this->collectionName . '/add',
            $this->getHeaders(),
            $payload
        );

        return $id;
    }

    public function addDocuments(array $documents): array
    {
        $this->ensureInitialized();
        $ids = [];
        $embeddings = [];
        $contents = [];
        $metadatas = [];

        foreach ($documents as $document) {
            $ids[] = $document->getId();
            $embeddings[] = $document->getEmbedding();
            $contents[] = $document->getContent();
            $metadatas[] = $document->getMetadata();
        }

        $this->httpClient->post(
            $this->baseUrl . '/api/v2/collections/' . $this->collectionName . '/add',
            $this->getHeaders(),
            [
                'ids' => $ids,
                'embeddings' => $embeddings,
                'documents' => $contents,
                'metadatas' => $metadatas,
            ]
        );

        return $ids;
    }

    public function delete(string $id): bool
    {
        $this->httpClient->post(
            $this->baseUrl . '/api/v2/collections/' . $this->collectionName . '/delete',
            $this->getHeaders(),
            ['ids' => [$id]]
        );
        return true;
    }

    public function deleteBatch(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        $this->httpClient->post(
            $this->baseUrl . '/api/v2/collections/' . $this->collectionName . '/delete',
            $this->getHeaders(),
            ['ids' => $ids]
        );

        return count($ids);
    }

    public function getById(string $id): ?Document
    {
        $response = $this->httpClient->get(
            $this->baseUrl . '/api/v2/collections/' . $this->collectionName . '/get?ids=' . urlencode($id),
            $this->getHeaders()
        );

        $data = Json::decode($response->getBody());
        $ids = $data['ids'] ?? [];

        if (empty($ids)) {
            return null;
        }

        return $this->rowToDocument($data, 0);
    }

    public function list(array $filters = []): array
    {
        $payload = [
            'limit' => 10000,
        ];

        if (!empty($filters)) {
            $where = [];
            foreach ($filters as $key => $value) {
                $where[$key] = $value;
            }
            $payload['where'] = $where;
        }

        $response = $this->httpClient->post(
            $this->baseUrl . '/api/v2/collections/' . $this->collectionName . '/get',
            $this->getHeaders(),
            $payload
        );

        $data = Json::decode($response->getBody());
        $ids = $data['ids'] ?? [];
        $documents = [];

        foreach ($ids as $index => $id) {
            $documents[] = $this->rowToDocument($data, $index);
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
            'query_embeddings' => [$queryVector],
            'n_results' => $topK,
            'include' => ['documents', 'metadatas', 'distances'],
        ];

        if (!empty($filters)) {
            $where = [];
            foreach ($filters as $key => $value) {
                $where[$key] = $value;
            }
            $payload['where'] = $where;
        }

        $response = $this->httpClient->post(
            $this->baseUrl . '/api/v2/collections/' . $this->collectionName . '/query',
            $this->getHeaders(),
            $payload
        );

        $data = Json::decode($response->getBody());
        $ids = $data['ids'][0] ?? [];
        $distances = $data['distances'][0] ?? [];
        $documents = $data['documents'][0] ?? [];
        $metadatas = $data['metadatas'][0] ?? [];

        $results = [];
        foreach ($ids as $index => $id) {
            $results[] = [
                'document' => new Document([
                    'id' => $id,
                    'content' => $documents[$index] ?? '',
                    'metadata' => $metadatas[$index] ?? [],
                ]),
                'score' => 1 - ($distances[$index] ?? 1),
            ];
        }

        return $results;
    }

    public function count(): int
    {
        $response = $this->httpClient->get(
            $this->baseUrl . '/api/v2/collections/' . $this->collectionName,
            $this->getHeaders()
        );

        $data = Json::decode($response->getBody());
        return (int)($data['count'] ?? 0);
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
            $response = $this->httpClient->get(
                $this->baseUrl . '/api/v2/collections/' . $this->collectionName,
                $this->getHeaders()
            );
            $data = Json::decode($response->getBody());
            if (isset($data['name'])) {
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
            $this->baseUrl . '/api/v2/collections/' . $this->collectionName,
            $this->getHeaders()
        );
        $this->initialized = false;
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
     * @param array<string, mixed> $data
     * @param int $index
     * @return Document
     */
    private function rowToDocument(array $data, int $index): Document
    {
        return new Document([
            'id' => $data['ids'][$index] ?? '',
            'content' => $data['documents'][$index] ?? '',
            'metadata' => $data['metadatas'][$index] ?? [],
        ]);
    }
}