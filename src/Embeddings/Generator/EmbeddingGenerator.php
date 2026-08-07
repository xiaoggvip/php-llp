<?php

declare(strict_types=1);

namespace PhpLLP\Embeddings\Generator;

use PhpLLP\Contracts\EmbeddingInterface;
use PhpLLP\Embeddings\Document;

class EmbeddingGenerator
{
    /** @var EmbeddingInterface */
    private $embeddingProvider;

    /** @var int */
    private $batchSize;

    /**
     * @param EmbeddingInterface $embeddingProvider
     * @param int $batchSize
     */
    public function __construct(EmbeddingInterface $embeddingProvider, int $batchSize = 100)
    {
        $this->embeddingProvider = $embeddingProvider;
        $this->batchSize = $batchSize;
    }

    /**
     * Generate embedding for a single document
     *
     * @param Document $document
     * @return Document
     */
    public function generate(Document $document): Document
    {
        $embedding = $this->embeddingProvider->embed($document->getContent());
        $document->setEmbedding($embedding);
        return $document;
    }

    /**
     * Generate embeddings for multiple documents
     *
     * @param Document[] $documents
     * @return Document[]
     */
    public function generateBatch(array $documents): array
    {
        $results = [];
        $batches = array_chunk($documents, $this->batchSize);

        foreach ($batches as $batch) {
            $texts = [];
            $docMap = [];

            foreach ($batch as $index => $doc) {
                $texts[] = $doc->getContent();
                $docMap[] = $index;
            }

            $embeddings = $this->embeddingProvider->embedBatch($texts);

            foreach ($batch as $index => $doc) {
                if (isset($embeddings[$index])) {
                    $doc->setEmbedding($embeddings[$index]);
                }
                $results[] = $doc;
            }
        }

        return $results;
    }

    /**
     * Generate embedding for a text string
     *
     * @param string $text
     * @return array<int, float>
     */
    public function generateForText(string $text): array
    {
        return $this->embeddingProvider->embed($text);
    }

    /**
     * Generate embeddings for multiple texts
     *
     * @param string[] $texts
     * @return array<int, array<int, float>>
     */
    public function generateForTexts(array $texts): array
    {
        return $this->embeddingProvider->embedBatch($texts);
    }

    /**
     * @param int $batchSize
     * @return self
     */
    public function setBatchSize(int $batchSize): self
    {
        $this->batchSize = $batchSize;
        return $this;
    }
}