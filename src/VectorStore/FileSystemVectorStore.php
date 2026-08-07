<?php

declare(strict_types=1);

namespace PhpLLP\VectorStore;

use PhpLLP\Embeddings\Distances\Distance;
use PhpLLP\Embeddings\Document;
use PhpLLP\Support\Json;

class FileSystemVectorStore extends VectorStoreBase
{
    /** @var string */
    private $path;

    /** @var array<string, array{document: Document, embedding: array<int, float>}> */
    private $store = [];

    /**
     * @param string $collectionName
     * @param Distance|null $distanceMetric
     * @param int $dimension
     * @param string $path
     */
    public function __construct(
        string $collectionName = 'default',
        Distance $distanceMetric = null,
        int $dimension = 1536,
        string $path = './vector_store'
    ) {
        parent::__construct($collectionName, $distanceMetric, $dimension);
        $this->path = rtrim($path, '/\\') . DIRECTORY_SEPARATOR . $collectionName . '.json';
        $this->load();
    }

    public function initialize(): void
    {
        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $this->save();
    }

    public function addDocument(Document $document): string
    {
        $id = $document->getId();
        $this->store[$id] = [
            'document' => $document,
            'embedding' => $document->getEmbedding(),
        ];
        $this->save();
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
        if (isset($this->store[$id])) {
            unset($this->store[$id]);
            $this->save();
            return true;
        }
        return false;
    }

    public function deleteBatch(array $ids): int
    {
        $count = 0;
        foreach ($ids as $id) {
            if (isset($this->store[$id])) {
                unset($this->store[$id]);
                $count++;
            }
        }
        if ($count > 0) {
            $this->save();
        }
        return $count;
    }

    public function getById(string $id): ?Document
    {
        return $this->store[$id]['document'] ?? null;
    }

    public function list(array $filters = []): array
    {
        $documents = [];
        foreach ($this->store as $entry) {
            $doc = $entry['document'];
            if ($this->matchesFilters($doc, $filters)) {
                $documents[] = $doc;
            }
        }
        return $documents;
    }

    public function similaritySearch(
        array $queryVector,
        int $topK = 5,
        float $threshold = 0.0,
        array $filters = []
    ): array {
        $results = [];

        foreach ($this->store as $entry) {
            $doc = $entry['document'];
            $embedding = $entry['embedding'];

            if (empty($embedding)) {
                continue;
            }

            if (!$this->matchesFilters($doc, $filters)) {
                continue;
            }

            $score = $this->calculateDistance($queryVector, $embedding);

            if ($score >= $threshold) {
                $results[] = [
                    'document' => $doc,
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
        return count($this->store);
    }

    public function clear(): void
    {
        $this->store = [];
        $this->save();
    }

    /**
     * @param Document $doc
     * @param array<string, mixed> $filters
     * @return bool
     */
    private function matchesFilters(Document $doc, array $filters): bool
    {
        if (empty($filters)) {
            return true;
        }

        $metadata = $doc->getMetadata();
        foreach ($filters as $key => $value) {
            if (!isset($metadata[$key]) || $metadata[$key] != $value) {
                return false;
            }
        }

        return true;
    }

    private function load(): void
    {
        if (file_exists($this->path)) {
            $content = file_get_contents($this->path);
            if ($content !== false) {
                $data = Json::decode($content);
                if (is_array($data)) {
                    foreach ($data as $id => $entry) {
                        $this->store[$id] = [
                            'document' => Document::fromArray($entry['document'] ?? []),
                            'embedding' => $entry['embedding'] ?? [],
                        ];
                    }
                }
            }
        }
    }

    private function save(): void
    {
        $data = [];
        foreach ($this->store as $id => $entry) {
            $data[$id] = [
                'document' => $entry['document']->toArray(),
                'embedding' => $entry['embedding'],
            ];
        }

        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $tempFile = $this->path . '.tmp';
        $content = Json::encode($data);
        file_put_contents($tempFile, $content);
        rename($tempFile, $this->path);
    }
}