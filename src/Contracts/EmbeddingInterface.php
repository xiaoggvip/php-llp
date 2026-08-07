<?php

declare(strict_types=1);

namespace PhpLLP\Contracts;

use PhpLLP\Embeddings\Document;

interface EmbeddingInterface
{
    /**
     * Embed a single text string
     *
     * @param string $text
     * @param array<string, mixed> $options
     * @return array<int, float>
     */
    public function embedText(string $text, array $options = []): array;

    /**
     * Embed a Document object
     *
     * @param Document $document
     * @param array<string, mixed> $options
     * @return Document
     */
    public function embedDocument(Document $document, array $options = []): Document;

    /**
     * Embed multiple Documents
     *
     * @param array<int, Document> $documents
     * @param array<string, mixed> $options
     * @return array<int, Document>
     */
    public function embedDocuments(array $documents, array $options = []): array;
}