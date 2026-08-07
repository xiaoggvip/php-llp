<?php

declare(strict_types=1);

namespace PhpLLP\VectorStore;

use PhpLLP\Embeddings\Distances\Distance;
use PhpLLP\Embeddings\Document;
use PhpLLP\Http\HttpClient;
use PhpLLP\Support\Json;

class PostgresVectorStore extends VectorStoreBase
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
        $this->baseUrl = rtrim($config['base_url'] ?? 'http://localhost:5432', '/');
        $this->apiKey = $config['api_key'] ?? '';
    }

    public function initialize(): void
    {
        $tableName = $this->getTableName();
        $dim = $this->dimension;

        $sql = "
            CREATE TABLE IF NOT EXISTS {$tableName} (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                content TEXT NOT NULL,
                embedding vector({$dim}),
                metadata JSONB,
                hash TEXT,
                created_at TIMESTAMP DEFAULT NOW()
            )
        ";

        if ($this->apiKey) {
            $this->httpClient->post(
                $this->baseUrl . '/_sql',
                ['Authorization' => 'Bearer ' . $this->apiKey],
                ['query' => $sql]
            );
        }
    }

    public function addDocument(Document $document): string
    {
        $this->ensureInitialized();
        $id = $document->getId();
        $tableName = $this->getTableName();
        $embedding = $document->getEmbedding();

        $sql = "
            INSERT INTO {$tableName} (id, content, embedding, metadata, hash)
            VALUES ($1, $2, $3, $4, $5)
            ON CONFLICT (id) DO UPDATE SET
                content = EXCLUDED.content,
                embedding = EXCLUDED.embedding,
                metadata = EXCLUDED.metadata,
                hash = EXCLUDED.hash
            RETURNING id
        ";

        $this->executeQuery($sql, [
            $id,
            $document->getContent(),
            '[' . implode(',', $embedding) . ']',
            Json::encode($document->getMetadata()),
            $document->getHash(),
        ]);

        return $id;
    }

    public function addDocuments(array $documents): array
    {
        $this->ensureInitialized();
        $ids = [];
        foreach ($documents as $document) {
            $ids[] = $this->addDocument($document);
        }
        return $ids;
    }

    public function delete(string $id): bool
    {
        $tableName = $this->getTableName();
        $sql = "DELETE FROM {$tableName} WHERE id = $1";
        $this->executeQuery($sql, [$id]);
        return true;
    }

    public function deleteBatch(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        $tableName = $this->getTableName();
        $placeholders = implode(',', array_map(function ($i) { return '$' . ($i + 1); }, range(0, count($ids) - 1)));
        $sql = "DELETE FROM {$tableName} WHERE id IN ({$placeholders})";
        $this->executeQuery($sql, $ids);
        return count($ids);
    }

    public function getById(string $id): ?Document
    {
        $tableName = $this->getTableName();
        $sql = "SELECT * FROM {$tableName} WHERE id = $1";
        $result = $this->executeQuery($sql, [$id]);

        if (empty($result)) {
            return null;
        }

        return $this->rowToDocument($result[0]);
    }

    public function list(array $filters = []): array
    {
        $tableName = $this->getTableName();
        $sql = "SELECT * FROM {$tableName}";
        $params = [];

        if (!empty($filters)) {
            $conditions = [];
            $i = 1;
            foreach ($filters as $key => $value) {
                $conditions[] = "metadata->>'{$key}' = $" . $i;
                $params[] = $value;
                $i++;
            }
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $result = $this->executeQuery($sql, $params);
        $documents = [];
        foreach ($result as $row) {
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
        $tableName = $this->getTableName();
        $vectorStr = '[' . implode(',', $queryVector) . ']';

        $sql = "SELECT *, 1 - (embedding <=> '{$vectorStr}'::vector) AS score FROM {$tableName}";
        $params = [];

        $conditions = [];
        if (!empty($filters)) {
            $i = 1;
            foreach ($filters as $key => $value) {
                $conditions[] = "metadata->>'{$key}' = $" . $i;
                $params[] = $value;
                $i++;
            }
        }

        if ($threshold > 0) {
            $conditions[] = "1 - (embedding <=> '{$vectorStr}'::vector) >= " . $threshold;
        }

        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= " ORDER BY score DESC LIMIT {$topK}";

        $result = $this->executeQuery($sql, $params);

        $results = [];
        foreach ($result as $row) {
            $results[] = [
                'document' => $this->rowToDocument($row),
                'score' => (float)($row['score'] ?? 0),
            ];
        }

        return $results;
    }

    public function count(): int
    {
        $tableName = $this->getTableName();
        $result = $this->executeQuery("SELECT COUNT(*) as count FROM {$tableName}");
        return (int)($result[0]['count'] ?? 0);
    }

    /**
     * 确保表已初始化，如果不存在则自动创建
     */
    private function ensureInitialized(): void
    {
        if ($this->initialized) {
            return;
        }

        try {
            $tableName = $this->getTableName();
            $result = $this->executeQuery("SELECT to_regclass('{$tableName}') as exists_check");
            if (!empty($result) && !empty($result[0]['exists_check'])) {
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
        $tableName = $this->getTableName();
        $this->executeQuery("DELETE FROM {$tableName}");
        $this->initialized = false;
    }

    /**
     * @return string
     */
    private function getTableName(): string
    {
        return 'vs_' . $this->collectionName;
    }

    /**
     * @param string $sql
     * @param array<int, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    private function executeQuery(string $sql, array $params = []): array
    {
        $payload = ['query' => $sql];
        if (!empty($params)) {
            $payload['params'] = $params;
        }

        $headers = ['Content-Type' => 'application/json'];
        if ($this->apiKey) {
            $headers['Authorization'] = 'Bearer ' . $this->apiKey;
        }

        $response = $this->httpClient->post(
            $this->baseUrl . '/_sql',
            $headers,
            $payload
        );

        $data = Json::decode($response->getBody());
        return $data['rows'] ?? [];
    }

    /**
     * @param array<string, mixed> $row
     * @return Document
     */
    private function rowToDocument(array $row): Document
    {
        $embedding = [];
        if (isset($row['embedding'])) {
            $embeddingStr = is_string($row['embedding']) ? $row['embedding'] : '';
            $embedding = json_decode($embeddingStr, true) ?: [];
        }

        $metadata = [];
        if (isset($row['metadata'])) {
            $metadataStr = is_string($row['metadata']) ? $row['metadata'] : '';
            $metadata = json_decode($metadataStr, true) ?: [];
        }

        return new Document([
            'id' => $row['id'] ?? '',
            'content' => $row['content'] ?? '',
            'embedding' => $embedding,
            'metadata' => $metadata,
            'hash' => $row['hash'] ?? '',
        ]);
    }
}