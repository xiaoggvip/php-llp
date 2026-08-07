<?php

declare(strict_types=1);

namespace PhpLLP\Query;

use PhpLLP\Contracts\EmbeddingInterface;
use PhpLLP\Embeddings\Document;
use PhpLLP\VectorStore\VectorStoreBase;

class SemanticSearch
{
    /** @var EmbeddingInterface */
    private $embeddingProvider;

    /** @var VectorStoreBase */
    private $vectorStore;

    /**
     * @param EmbeddingInterface $embeddingProvider
     * @param VectorStoreBase $vectorStore
     */
    public function __construct(EmbeddingInterface $embeddingProvider, VectorStoreBase $vectorStore)
    {
        $this->embeddingProvider = $embeddingProvider;
        $this->vectorStore = $vectorStore;
    }

    /**
     * Search by text query
     *
     * @param string $query
     * @param int $topK
     * @param float $threshold
     * @param array<string, mixed> $filters
     * @return array<int, array{document: Document, score: float}>
     */
    public function search(
        string $query,
        int $topK = 5,
        float $threshold = 0.0,
        array $filters = []
    ): array {
        $queryEmbedding = $this->embeddingProvider->embed($query);
        return $this->vectorStore->similaritySearch($queryEmbedding, $topK, $threshold, $filters);
    }

    /**
     * Search by embedding vector
     *
     * @param array<int, float> $queryVector
     * @param int $topK
     * @param float $threshold
     * @param array<string, mixed> $filters
     * @return array<int, array{document: Document, score: float}>
     */
    public function searchByVector(
        array $queryVector,
        int $topK = 5,
        float $threshold = 0.0,
        array $filters = []
    ): array {
        return $this->vectorStore->similaritySearch($queryVector, $topK, $threshold, $filters);
    }

    /**
     * Add a document to the index
     *
     * @param Document $document
     * @return string
     */
    public function addDocument(Document $document): string
    {
        if (empty($document->getEmbedding())) {
            $embedding = $this->embeddingProvider->embed($document->getContent());
            $document->setEmbedding($embedding);
        }

        return $this->vectorStore->addDocument($document);
    }

    /**
     * Add multiple documents to the index
     *
     * @param Document[] $documents
     * @return string[]
     */
    public function addDocuments(array $documents): array
    {
        $ids = [];
        foreach ($documents as $document) {
            $ids[] = $this->addDocument($document);
        }
        return $ids;
    }

    public function getVectorStore(): VectorStoreBase
    {
        return $this->vectorStore;
    }

    public function getEmbeddingProvider(): EmbeddingInterface
    {
        return $this->embeddingProvider;
    }
}