<?php

declare(strict_types=1);

namespace PhpLLP\Embeddings;

class Document
{
    /** @var string */
    private $id;

    /** @var string */
    private $content;

    /** @var array<int, float> */
    private $embedding = [];

    /** @var string */
    private $hash;

    /** @var array<string, mixed> */
    private $metadata = [];

    /**
     * @param array{id?: string, content?: string, embedding?: array<int, float>, metadata?: array<string, mixed>} $data
     */
    public function __construct(array $data = [])
    {
        $this->id = $data['id'] ?? uniqid('doc_', true);
        $this->content = $data['content'] ?? '';
        $this->embedding = $data['embedding'] ?? [];
        $this->metadata = $data['metadata'] ?? [];
        $this->hash = $data['hash'] ?? md5($this->content);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        $this->hash = md5($content);
        return $this;
    }

    /**
     * @return array<int, float>
     */
    public function getEmbedding(): array
    {
        return $this->embedding;
    }

    /**
     * @param array<int, float> $embedding
     * @return self
     */
    public function setEmbedding(array $embedding): self
    {
        $this->embedding = $embedding;
        return $this;
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
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

    /**
     * @param string $key
     * @param mixed $value
     * @return self
     */
    public function addMetadata(string $key, $value): self
    {
        $this->metadata[$key] = $value;
        return $this;
    }

    /**
     * @return array{id: string, content: string, embedding: array<int, float>, hash: string, metadata: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'content' => $this->content,
            'embedding' => $this->embedding,
            'hash' => $this->hash,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }
}