<?php

declare(strict_types=1);

namespace PhpLLP\VectorStore;

use PhpLLP\Embeddings\Distances\CosineDistance;
use PhpLLP\Embeddings\Distances\Distance;
use PhpLLP\Embeddings\Document;

abstract class VectorStoreBase
{
    /** @var string */
    protected $collectionName;

    /** @var Distance */
    protected $distanceMetric;

    /** @var int */
    protected $dimension;

    /**
     * @param string $collectionName
     * @param Distance|null $distanceMetric
     * @param int $dimension
     */
    public function __construct(
        string $collectionName = 'default',
        Distance $distanceMetric = null,
        int $dimension = 1536
    ) {
        $this->collectionName = $collectionName;
        $this->distanceMetric = $distanceMetric ?? new CosineDistance();
        $this->dimension = $dimension;
    }

    abstract public function initialize(): void;

    /**
     * @param Document $document
     * @return string Document ID
     */
    abstract public function addDocument(Document $document): string;

    /**
     * @param Document[] $documents
     * @return string[]
     */
    abstract public function addDocuments(array $documents): array;

    /**
     * @param string $id
     * @return bool
     */
    abstract public function delete(string $id): bool;

    /**
     * @param string[] $ids
     * @return int Number of deleted documents
     */
    abstract public function deleteBatch(array $ids): int;

    /**
     * @param string $id
     * @return Document|null
     */
    abstract public function getById(string $id): ?Document;

    /**
     * @param array<string, mixed> $filters
     * @return Document[]
     */
    abstract public function list(array $filters = []): array;

    /**
     * @param array<int, float> $queryVector
     * @param int $topK
     * @param float $threshold
     * @param array<string, mixed> $filters
     * @return array<int, array{document: Document, score: float}>
     */
    abstract public function similaritySearch(
        array $queryVector,
        int $topK = 5,
        float $threshold = 0.0,
        array $filters = []
    ): array;

    /**
     * @param array<int, float> $queryVector
     * @param string $text
     * @param int $topK
     * @param float $threshold
     * @return array<int, array{document: Document, score: float}>
     */
    public function similaritySearchByText(
        array $queryVector,
        string $text,
        int $topK = 5,
        float $threshold = 0.0
    ): array {
        return $this->similaritySearch($queryVector, $topK, $threshold);
    }

    abstract public function count(): int;

    abstract public function clear(): void;

    public function getCollectionName(): string
    {
        return $this->collectionName;
    }

    public function getDistanceMetric(): Distance
    {
        return $this->distanceMetric;
    }

    public function getDimension(): int
    {
        return $this->dimension;
    }

    /**
     * @param array<int, float> $a
     * @param array<int, float> $b
     * @return float
     */
    protected function calculateDistance(array $a, array $b): float
    {
        return $this->distanceMetric->calculate($a, $b);
    }

    /**
     * @param array<int, float> $a
     * @param array<int, float> $b
     * @return float
     */
    protected function cosineSimilarity(array $a, array $b): float
    {
        return $this->distanceMetric->calculate($a, $b);
    }
}