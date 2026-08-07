<?php

declare(strict_types=1);

namespace PhpLLP\Contracts;

use PhpLLP\Embeddings\Document;

interface VectorStoreInterface
{
    /**
     * Add a single document to the vector store
     *
     * @param Document $document
     */
    public function addDocument(Document $document): void;

    /**
     * Add multiple documents to the vector store
     *
     * @param array<int, Document> $documents
     */
    public function addDocuments(array $documents): void;

    /**
     * Perform similarity search
     *
     * @param array<int, float> $embedding
     * @param int $k
     * @param array<string, mixed> $filters
     * @return array<int, Document>
     */
    public function similaritySearch(array $embedding, int $k = 4, array $filters = []): array;

    /**
     * Get document by ID
     *
     * @param string $id
     * @return Document|null
     */
    public function getDocument(string $id): ?Document;

    /**
     * Delete document by ID
     *
     * @param string $id
     * @return bool
     */
    public function deleteDocument(string $id): bool;

    /**
     * Delete all documents
     */
    public function deleteAll(): void;

    /**
     * Get all documents
     *
     * @return array<int, Document>
     */
    public function getAll(): array;

    /**
     * Get the number of documents
     *
     * @return int
     */
    public function count(): int;

    /**
     * Persist the vector store to storage
     */
    public function save(): void;

    /**
     * Load the vector store from storage
     */
    public function load(): void;
}