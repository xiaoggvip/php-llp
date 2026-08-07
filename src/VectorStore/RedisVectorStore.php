<?php

declare(strict_types=1);

namespace PhpLLP\VectorStore;

use PhpLLP\Embeddings\Distances\Distance;
use PhpLLP\Embeddings\Document;
use PhpLLP\Http\HttpClient;
use PhpLLP\Support\Json;

class RedisVectorStore extends VectorStoreBase
{
    /** @var array<string, mixed> */
    private $config;

    /** @var HttpClient */
    private $httpClient;

    /** @var string */
    private $baseUrl;

    /** @var string */
    private $indexName;

    /**
     * @param string $collectionName
     * @param Distance|null $distanceMetric
     * @param int $dimension
     * @param array<string, mixed> $config
     */
    public function __construct(
        string $collectionName = 'default',
        Distance $distanceMetric = null,
        int $dimension = 1536,
        array $config = []
    ) {
        parent::__construct($collectionName, $distanceMetric, $dimension);
        $this->config = $config;
        $this->httpClient = new HttpClient();
        $this->baseUrl = rtrim($config['base_url'] ?? 'http://localhost:6379', '/');
        $this->indexName = $config['index_name'] ?? 'phpLLP_idx_' . $collectionName;
    }

    public function initialize(): void
    {
        $distance = 'COSINE';
        if ($this->distanceMetric->getName() === 'euclidean') {
            $distance = 'EUCLIDEAN';
        }

        $payload = [
            'indexName' => $this->indexName,
            'schema' => [
                'content' => ['type' => 'TEXT'],
                'metadata' => ['type' => 'TEXT'],
                'hash' => ['type' => 'TEXT'],
                'vector' => [
                    'type' => 'VECTOR',
                    'algorithm' => 'FLAT',
                    'distanceMetric' => $distance,
                    'dim' => $this->dimension,
                    'datatype' => 'FLOAT32',
                ],
            ],
        ];

        $this->httpClient->post(
            $this->baseUrl . '/ft.create',
            ['Content-Type' => 'application/json'],
            $payload
        );
    }

    public function addDocument(Document $document): string
    {
        $id = $document->getId();
        $embedding = $document->getEmbedding();

        $pipeline = "
            SET {$this->indexName}:{$id}
            \$field content\n{$document->getContent()}
            \$field metadata\n" . Json::encode($document->getMetadata()) . "
            \$field hash\n{$document->getHash()}
            \$field vector\n" . $this->vectorToBinary($embedding);

        $this->httpClient->post(
            $this->baseUrl . '/ft.add',
            ['Content-Type' => 'application/json'],
            [
                'indexName' => $this->indexName,
                'docId' => $id,
                'fields' => [
                    'content' => $document->getContent(),
                    'metadata' => Json::encode($document->getMetadata()),
                    'hash' => $document->getHash(),
                    'vector' => $embedding,
                ],
            ]
        );

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
        $this->httpClient->post(
            $this->baseUrl . '/ft.del',
            ['Content-Type' => 'application/json'],
            [
                'indexName' => $this->indexName,
                'docId' => $id,
            ]
        );
        return true;
    }

    public function deleteBatch(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        foreach ($ids as $id) {
            $this->delete($id);
        }

        return count($ids);
    }

    public function getById(string $id): ?Document
    {
        $response = $this->httpClient->get(
            $this->baseUrl . '/ft.get?indexName=' . $this->indexName . '&docId=' . $id
        );

        $data = Json::decode($response->getBody());
        $fields = $data['results'][0]['document'] ?? [];

        if (empty($fields)) {
            return null;
        }

        return $this->fieldsToDocument($id, $fields);
    }

    public function list(array $filters = []): array
    {
        $query = '*';
        if (!empty($filters)) {
            $conditions = [];
            foreach ($filters as $key => $value) {
                $conditions[] = "@metadata:\"{$value}\"";
            }
            $query = implode(' ', $conditions);
        }

        $response = $this->httpClient->get(
            $this->baseUrl . '/ft.search?indexName=' . $this->indexName . '&q=' . urlencode($query) . '&limit=10000'
        );

        $data = Json::decode($response->getBody());
        $results = $data['results'] ?? [];

        $documents = [];
        foreach ($results as $result) {
            $id = $result['id'] ?? '';
            $fields = $result['document'] ?? [];
            $documents[] = $this->fieldsToDocument($id, $fields);
        }

        return $documents;
    }

    public function similaritySearch(
        array $queryVector,
        int $topK = 5,
        float $threshold = 0.0,
        array $filters = []
    ): array {
        $query = '*=>[KNN ' . $topK . ' @vector $vec AS score]';

        $params = [
            'indexName' => $this->indexName,
            'q' => $query,
            'params' => json_encode(['vec' => $queryVector]),
            'sortBy' => 'score',
        ];

        if ($threshold > 0) {
            $params['filter'] = "score <= {$threshold}";
        }

        $url = $this->baseUrl . '/ft.search?' . http_build_query($params);
        $response = $this->httpClient->get($url);

        $data = Json::decode($response->getBody());
        $results = $data['results'] ?? [];

        $searchResults = [];
        foreach ($results as $result) {
            $id = $result['id'] ?? '';
            $fields = $result['document'] ?? [];
            $score = $result['score'] ?? 0;

            $searchResults[] = [
                'document' => $this->fieldsToDocument($id, $fields),
                'score' => (float)$score,
            ];
        }

        return $searchResults;
    }

    public function count(): int
    {
        $response = $this->httpClient->get(
            $this->baseUrl . '/ft.info?indexName=' . $this->indexName
        );

        $data = Json::decode($response->getBody());
        return (int)($data['numDocs'] ?? 0);
    }

    public function clear(): void
    {
        $this->httpClient->post(
            $this->baseUrl . '/ft.drop',
            ['Content-Type' => 'application/json'],
            ['indexName' => $this->indexName]
        );
        $this->initialize();
    }

    /**
     * @param array<int, float> $vector
     * @return string
     */
    private function vectorToBinary(array $vector): string
    {
        $binary = '';
        foreach ($vector as $value) {
            $binary .= pack('f', $value);
        }
        return base64_encode($binary);
    }

    /**
     * @param string $id
     * @param array<string, mixed> $fields
     * @return Document
     */
    private function fieldsToDocument(string $id, array $fields): Document
    {
        $metadata = [];
        if (isset($fields['metadata'])) {
            $decoded = json_decode($fields['metadata'], true);
            $metadata = is_array($decoded) ? $decoded : [];
        }

        return new Document([
            'id' => $id,
            'content' => $fields['content'] ?? '',
            'embedding' => isset($fields['vector']) && is_array($fields['vector']) ? $fields['vector'] : [],
            'metadata' => $metadata,
            'hash' => $fields['hash'] ?? '',
        ]);
    }
}