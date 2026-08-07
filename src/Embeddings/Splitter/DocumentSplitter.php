<?php

declare(strict_types=1);

namespace PhpLLP\Embeddings\Splitter;

use PhpLLP\Embeddings\Document;

class DocumentSplitter
{
    /** @var int */
    private $chunkSize;

    /** @var int */
    private $chunkOverlap;

    /** @var string */
    private $separator;

    /**
     * @param int $chunkSize
     * @param int $chunkOverlap
     * @param string $separator
     */
    public function __construct(int $chunkSize = 1000, int $chunkOverlap = 200, string $separator = "\n\n")
    {
        $this->chunkSize = $chunkSize;
        $this->chunkOverlap = $chunkOverlap;
        $this->separator = $separator;
    }

    /**
     * Split a single document into multiple chunks
     *
     * @param Document $document
     * @return Document[]
     */
    public function split(Document $document): array
    {
        return $this->splitText($document->getContent(), $document->getMetadata());
    }

    /**
     * Split text into multiple documents
     *
     * @param string $text
     * @param array<string, mixed> $metadata
     * @return Document[]
     */
    public function splitText(string $text, array $metadata = []): array
    {
        if (empty($text)) {
            return [];
        }

        $chunks = $this->splitBySeparator($text);
        $result = [];
        $index = 0;

        foreach ($chunks as $chunk) {
            if (strlen(trim($chunk)) === 0) {
                continue;
            }

            $doc = new Document([
                'content' => trim($chunk),
                'metadata' => array_merge($metadata, ['chunk_index' => $index]),
            ]);

            $result[] = $doc;
            $index++;
        }

        return $result;
    }

    /**
     * @param string $text
     * @return string[]
     */
    private function splitBySeparator(string $text): array
    {
        $parts = explode($this->separator, $text);
        $chunks = [];
        $currentChunk = '';

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            if (strlen($currentChunk) + strlen($part) + strlen($this->separator) <= $this->chunkSize) {
                $currentChunk .= ($currentChunk !== '' ? $this->separator : '') . $part;
            } else {
                if ($currentChunk !== '') {
                    $chunks[] = $currentChunk;
                }

                if (strlen($part) > $this->chunkSize) {
                    $subChunks = $this->splitLongText($part);
                    foreach ($subChunks as $subChunk) {
                        $chunks[] = $subChunk;
                    }
                    $currentChunk = '';
                } else {
                    $currentChunk = $part;
                }
            }
        }

        if ($currentChunk !== '') {
            $chunks[] = $currentChunk;
        }

        return $chunks;
    }

    /**
     * @param string $text
     * @return string[]
     */
    private function splitLongText(string $text): array
    {
        $result = [];
        $length = strlen($text);
        $start = 0;

        while ($start < $length) {
            $end = min($start + $this->chunkSize, $length);
            $chunk = substr($text, $start, $end - $start);
            $result[] = $chunk;

            if ($end >= $length) {
                break;
            }

            $start = $end - $this->chunkOverlap;
        }

        return $result;
    }

    /**
     * @param int $chunkSize
     * @return self
     */
    public function setChunkSize(int $chunkSize): self
    {
        $this->chunkSize = $chunkSize;
        return $this;
    }

    /**
     * @param int $chunkOverlap
     * @return self
     */
    public function setChunkOverlap(int $chunkOverlap): self
    {
        $this->chunkOverlap = $chunkOverlap;
        return $this;
    }
}