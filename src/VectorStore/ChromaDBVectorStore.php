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

    /** @var string */
    private $tenant;

    /** @var string */
    private $database;

    /** @var bool */
    private $cloudMode = false;

    /** @var string */
    private $collectionId = '';

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
        $this->tenant = $config['tenant'] ?? '';
        $this->database = $config['database'] ?? '';
        $this->cloudMode = ($this->database !== '' && $this->tenant !== '');
    }

    private function getApiPath(string $endpoint = ''): string
    {
        if ($this->tenant && $this->database) {
            $basePath = '/api/v2/tenants/' . $this->tenant . '/databases/' . $this->database;
        } else {
            $basePath = '/api/v2';
        }

        return $this->baseUrl . $basePath . '/collections' . ($endpoint ? '/' . ltrim($endpoint, '/') : '');
    }

    private function getCollectionPath(): string
    {
        if ($this->cloudMode && $this->collectionId) {
            return $this->collectionId;
        }
        return $this->collectionName;
    }

    /**
     * @param array<int, float> $embedding
     * @throws \RuntimeException
     */
    private function validateEmbeddingDimension(array $embedding): void
    {
        if ($this->dimension > 0 && count($embedding) !== $this->dimension) {
            throw new \RuntimeException(
                sprintf(
                    'Embedding 维度不匹配: 集合期望 %d 维，实际传入 %d 维。请检查 embedding 生成器或更新集合配置。',
                    $this->dimension,
                    count($embedding)
                )
            );
        }
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

        if ($this->dimension > 0) {
            $payload['dimension'] = $this->dimension;
        }

        if ($this->cloudMode) {
            $payload['id'] = $this->generateUuid();
        }

        $response = $this->httpClient->post(
            $this->getApiPath(),
            $this->getHeaders(),
            $payload
        );

        if (!$response->isSuccess()) {
            throw new \RuntimeException(
                sprintf('初始化集合失败: [%d] %s', $response->getStatusCode(), $response->getBody())
            );
        }

        if ($this->cloudMode) {
            $data = Json::decode($response->getBody());
            $this->collectionId = $data['id'] ?? ($payload['id'] ?? '');
        }
    }

    public function addDocument(Document $document): string
    {
        $this->ensureInitialized();
        $embedding = $document->getEmbedding();
        $this->validateEmbeddingDimension($embedding);

        $id = $document->getId();

        $payload = [
            'ids' => [$id],
            'embeddings' => [$embedding],
            'documents' => [$document->getContent()],
            'metadatas' => [$document->getMetadata()],
        ];

        $response = $this->httpClient->post(
            $this->getApiPath($this->getCollectionPath() . '/add'),
            $this->getHeaders(),
            $payload
        );

        if (!$response->isSuccess()) {
            throw new \RuntimeException(
                sprintf('添加文档失败: [%d] %s', $response->getStatusCode(), $response->getBody())
            );
        }

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
            $embedding = $document->getEmbedding();
            $this->validateEmbeddingDimension($embedding);

            $ids[] = $document->getId();
            $embeddings[] = $embedding;
            $contents[] = $document->getContent();
            $metadatas[] = $document->getMetadata();
        }

        $response = $this->httpClient->post(
            $this->getApiPath($this->getCollectionPath() . '/add'),
            $this->getHeaders(),
            [
                'ids' => $ids,
                'embeddings' => $embeddings,
                'documents' => $contents,
                'metadatas' => $metadatas,
            ]
        );

        if (!$response->isSuccess()) {
            throw new \RuntimeException(
                sprintf('批量添加文档失败: [%d] %s', $response->getStatusCode(), $response->getBody())
            );
        }

        return $ids;
    }

    public function delete(string $id): bool
    {
        $response = $this->httpClient->post(
            $this->getApiPath($this->getCollectionPath() . '/delete'),
            $this->getHeaders(),
            ['ids' => [$id]]
        );

        return $response->isSuccess();
    }

    public function deleteBatch(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        $response = $this->httpClient->post(
            $this->getApiPath($this->getCollectionPath() . '/delete'),
            $this->getHeaders(),
            ['ids' => $ids]
        );

        return $response->isSuccess() ? count($ids) : 0;
    }

    public function getById(string $id): ?Document
    {
        $response = $this->httpClient->post(
            $this->getApiPath($this->getCollectionPath() . '/get'),
            $this->getHeaders(),
            ['ids' => [$id]]
        );

        if (!$response->isSuccess()) {
            return null;
        }

        $body = $response->getBody();
        if ($body === '') {
            return null;
        }

        $data = Json::decode($body);
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
            $this->getApiPath($this->getCollectionPath() . '/get'),
            $this->getHeaders(),
            $payload
        );

        if (!$response->isSuccess()) {
            return [];
        }

        $body = $response->getBody();
        if ($body === '') {
            return [];
        }

        $data = Json::decode($body);
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
            $this->getApiPath($this->getCollectionPath() . '/query'),
            $this->getHeaders(),
            $payload
        );

        if (!$response->isSuccess()) {
            return [];
        }

        $body = $response->getBody();
        if ($body === '') {
            return [];
        }

        $data = Json::decode($body);
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
        $this->ensureInitialized();

        try {
            if ($this->cloudMode) {
                $data = $this->findCollectionByName();
                if ($data === null) {
                    return 0;
                }
                return (int)($data['count'] ?? 0);
            }

            $response = $this->httpClient->get(
                $this->getApiPath($this->getCollectionPath()),
                $this->getHeaders()
            );
            if (!$response->isSuccess() || $response->getBody() === '') {
                return 0;
            }
            $data = Json::decode($response->getBody());
            return (int)($data['count'] ?? 0);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Find collection by name in cloud mode
     *
     * @return array<string, mixed>|null
     */
    private function findCollectionByName(): ?array
    {
        if (!$this->cloudMode) {
            return null;
        }

        $response = $this->httpClient->get(
            $this->getApiPath() . '?name=' . urlencode($this->collectionName),
            $this->getHeaders()
        );

        if (!$response->isSuccess() || $response->getBody() === '') {
            return null;
        }

        $data = Json::decode($response->getBody());
        if (empty($data)) {
            return null;
        }

        $item = is_array($data) ? $data[0] : $data;
        return $item;
    }

    private function ensureInitialized(): void
    {
        if ($this->initialized) {
            return;
        }

        try {
            if ($this->cloudMode) {
                $item = $this->findCollectionByName();
                if ($item !== null) {
                    $this->collectionId = $item['id'] ?? '';
                    if ($this->checkDimensionFromData($item)) {
                        $this->initialized = true;
                        return;
                    }
                }
            } else {
                $response = $this->httpClient->get(
                    $this->getApiPath($this->getCollectionPath()),
                    $this->getHeaders()
                );
                if ($response->isSuccess() && $response->getBody() !== '') {
                    $data = Json::decode($response->getBody());
                    if (isset($data['name'])) {
                        if ($this->checkDimensionFromData($data)) {
                            $this->initialized = true;
                            return;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            $message = $e->getMessage();
            if (strpos($message, '404') !== false || strpos($message, 'does not exist') !== false) {
                $this->initialize();
                $this->initialized = true;
                return;
            }
            throw $e;
        }

        try {
            $this->initialize();
            $this->initialized = true;
        } catch (\Exception $e) {
            $message = $e->getMessage();
            if ($this->cloudMode && (strpos($message, '409') !== false || strpos($message, 'already exists') !== false)) {
                $this->clear();
                $this->initialize();
                $this->initialized = true;
                return;
            }
            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return bool True if dimension matches (or cannot be determined), false if mismatch was handled
     * @throws \RuntimeException When dimension mismatched and auto-recreate is disabled
     */
    private function checkDimensionFromData(array $data): bool
    {
        $remoteDimension = $data['dimension'] ?? null;
        if ($remoteDimension === null || $this->dimension <= 0 || (int)$remoteDimension === $this->dimension) {
            return true;
        }

        $autoRecreate = $this->config['auto_recreate_on_dimension_mismatch'] ?? false;

        if ($autoRecreate) {
            $this->clear();
            return false;
        }

        throw new \RuntimeException(
            sprintf(
                'Collection [%s] 维度不匹配: 现有集合维度为 %d，当前配置维度为 %d。' .
                '请手动清空旧集合后重试，或在配置中设置 auto_recreate_on_dimension_mismatch=true 自动重建。',
                $this->collectionName,
                $remoteDimension,
                $this->dimension
            )
        );
    }

    public function clear(): void
    {
        if ($this->cloudMode) {
            $this->httpClient->delete(
                $this->getApiPath($this->collectionName),
                $this->getHeaders()
            );
            $this->collectionId = '';
            $this->initialized = false;
            return;
        }

        $this->httpClient->delete(
            $this->getApiPath($this->getCollectionPath()),
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
            if ($this->cloudMode) {
                $headers['x-chroma-token'] = $this->apiKey;
            } else {
                $headers['Authorization'] = 'Bearer ' . $this->apiKey;
            }
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

    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}