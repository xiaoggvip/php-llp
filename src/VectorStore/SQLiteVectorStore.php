<?php

declare(strict_types=1);

namespace PhpLLP\VectorStore;

use PhpLLP\Embeddings\Distances\Distance;
use PhpLLP\Embeddings\Document;
use PhpLLP\Support\Json;

class SQLiteVectorStore extends VectorStoreBase
{
    /** @var string */
    private $dbPath;

    /** @var \PDO */
    private $pdo;

    /**
     * @param string $collectionName
     * @param Distance|null $distanceMetric
     * @param int $dimension
     * @param string $dbPath
     */
    public function __construct(
        string $collectionName = 'default',
        Distance $distanceMetric = null,
        int $dimension = 1536,
        string $dbPath = ':memory:'
    ) {
        parent::__construct($collectionName, $distanceMetric, $dimension);
        $this->dbPath = $dbPath;
        $this->initializeConnection();
        $this->initialize();
    }

    private function initializeConnection(): void
    {
        $this->pdo = new \PDO('sqlite:' . $this->dbPath);
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    public function initialize(): void
    {
        $tableName = $this->getTableName();
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS {$tableName} (
                id TEXT PRIMARY KEY,
                content TEXT NOT NULL,
                embedding TEXT NOT NULL,
                metadata TEXT,
                hash TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_{$tableName}_hash ON {$tableName}(hash)");
    }

    public function addDocument(Document $document): string
    {
        $id = $document->getId();
        $tableName = $this->getTableName();

        $stmt = $this->pdo->prepare("
            INSERT OR REPLACE INTO {$tableName} (id, content, embedding, metadata, hash)
            VALUES (:id, :content, :embedding, :metadata, :hash)
        ");

        $stmt->execute([
            ':id' => $id,
            ':content' => $document->getContent(),
            ':embedding' => Json::encode($document->getEmbedding()),
            ':metadata' => Json::encode($document->getMetadata()),
            ':hash' => $document->getHash(),
        ]);

        return $id;
    }

    public function addDocuments(array $documents): array
    {
        $ids = [];
        foreach ($documents as $document) {
            $ids[] = $this->addDocument($document);
        }
        return $ids;
    }

    public function delete(string $id): bool
    {
        $tableName = $this->getTableName();
        $stmt = $this->pdo->prepare("DELETE FROM {$tableName} WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function deleteBatch(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        $tableName = $this->getTableName();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("DELETE FROM {$tableName} WHERE id IN ({$placeholders})");
        $stmt->execute($ids);
        return $stmt->rowCount();
    }

    public function getById(string $id): ?Document
    {
        $tableName = $this->getTableName();
        $stmt = $this->pdo->prepare("SELECT * FROM {$tableName} WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->rowToDocument($row);
    }

    public function list(array $filters = []): array
    {
        $tableName = $this->getTableName();
        $sql = "SELECT * FROM {$tableName}";
        $params = [];

        if (!empty($filters)) {
            $conditions = [];
            foreach ($filters as $key => $value) {
                $conditions[] = "metadata LIKE :{$key}";
                $params[":{$key}"] = '%"' . $key . '": "' . $value . '"%';
            }
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $documents = [];
        foreach ($rows as $row) {
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
        $sql = "SELECT * FROM {$tableName}";
        $params = [];

        if (!empty($filters)) {
            $conditions = [];
            foreach ($filters as $key => $value) {
                $conditions[] = "metadata LIKE :{$key}";
                $params[":{$key}"] = '%"' . $key . '": "' . $value . '"%';
            }
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $results = [];
        foreach ($rows as $row) {
            $embedding = Json::decode($row['embedding']);
            if (empty($embedding)) {
                continue;
            }

            $score = $this->calculateDistance($queryVector, $embedding);

            if ($score >= $threshold) {
                $results[] = [
                    'document' => $this->rowToDocument($row),
                    'score' => $score,
                ];
            }
        }

        usort($results, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        return array_slice($results, 0, $topK);
    }

    public function count(): int
    {
        $tableName = $this->getTableName();
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM {$tableName}");
        return (int)$stmt->fetchColumn();
    }

    public function clear(): void
    {
        $tableName = $this->getTableName();
        $this->pdo->exec("DELETE FROM {$tableName}");
    }

    /**
     * @return string
     */
    private function getTableName(): string
    {
        return 'vs_' . $this->collectionName;
    }

    /**
     * @param array<string, mixed> $row
     * @return Document
     */
    private function rowToDocument(array $row): Document
    {
        return new Document([
            'id' => $row['id'],
            'content' => $row['content'],
            'embedding' => Json::decode($row['embedding']),
            'metadata' => $row['metadata'] ? Json::decode($row['metadata']) : [],
            'hash' => $row['hash'] ?? '',
        ]);
    }
}