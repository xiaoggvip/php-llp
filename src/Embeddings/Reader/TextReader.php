<?php

declare(strict_types=1);

namespace PhpLLP\Embeddings\Reader;

use PhpLLP\Embeddings\Document;

class TextReader implements DataReader
{
    /** @var string */
    private $source;

    /** @var array<string, mixed> */
    private $metadata;

    /**
     * @param string $source
     * @param array<string, mixed> $metadata
     */
    public function __construct(string $source = '', array $metadata = [])
    {
        $this->source = $source;
        $this->metadata = $metadata;
    }

    public function read(): array
    {
        return $this->readFrom($this->source);
    }

    public function readFrom(string $source): array
    {
        $content = $this->loadContent($source);
        if ($content === '') {
            return [];
        }

        $metadata = array_merge($this->metadata, [
            'source' => $source,
            'type' => 'text',
        ]);

        return [new Document([
            'content' => $content,
            'metadata' => $metadata,
        ])];
    }

    /**
     * @param string $source
     * @return string
     */
    private function loadContent(string $source): string
    {
        if (file_exists($source)) {
            $content = file_get_contents($source);
            return $content !== false ? $content : '';
        }

        if (strpos($source, 'http://') === 0 || strpos($source, 'https://') === 0) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $source,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; phpLLP Bot)',
            ]);
            $content = curl_exec($ch);
            curl_close($ch);
            return $content !== false ? $content : '';
        }

        return $source;
    }

    /**
     * @param array<string, mixed> $metadata
     * @return self
     */
    public function setMetadata(array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }
}