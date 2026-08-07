<?php

declare(strict_types=1);

namespace PhpLLP\Embeddings\Reader;

use PhpLLP\Embeddings\Document;

class FileReader implements DataReader
{
    /** @var string[] */
    private $filePaths;

    /** @var array<string, mixed> */
    private $metadata;

    /**
     * @param string[] $filePaths
     * @param array<string, mixed> $metadata
     */
    public function __construct(array $filePaths = [], array $metadata = [])
    {
        $this->filePaths = $filePaths;
        $this->metadata = $metadata;
    }

    public function read(): array
    {
        $documents = [];
        foreach ($this->filePaths as $path) {
            $documents = array_merge($documents, $this->readFrom($path));
        }
        return $documents;
    }

    public function readFrom(string $source): array
    {
        if (!file_exists($source)) {
            return [];
        }

        $content = file_get_contents($source);
        if ($content === false) {
            return [];
        }

        $metadata = array_merge($this->metadata, [
            'source' => $source,
            'type' => 'file',
            'filename' => basename($source),
            'size' => filesize($source),
        ]);

        return [new Document([
            'content' => $content,
            'metadata' => $metadata,
        ])];
    }

    /**
     * @param string $path
     * @return self
     */
    public function addFile(string $path): self
    {
        $this->filePaths[] = $path;
        return $this;
    }

    /**
     * @param string[] $paths
     * @return self
     */
    public function setFiles(array $paths): self
    {
        $this->filePaths = $paths;
        return $this;
    }
}