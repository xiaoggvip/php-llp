<?php

declare(strict_types=1);

namespace PhpLLP\VectorStore;

use PhpLLP\Embeddings\Distances\Distance;
use PhpLLP\Embeddings\Document;
use PhpLLP\Http\HttpClient;
use PhpLLP\Http\HttpResponse;
use PhpLLP\Support\Json;

class AstraDBVectorStore extends VectorStoreBase
{
    /** @var array<string, mixed> */
    private $config;

    /** @var HttpClient */
    private $httpClient;

    /** @var string */
    private $baseUrl;

    /** @var string */
    private $apiKey;

    /** @var string */
    private $namespace;

    /** @var bool */
    private $initialized = false;

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
        $this->baseUrl = rtrim($config['base_url'] ?? 'https://$REGION.api.astra.datastax.com', '/');
        $this->apiKey = $config['api_key'] ?? '';
        $this->namespace = $config['namespace'] ?? '';
    }

    public function initialize(): void
    {
        $distance = 'cosine';
        if ($this->distanceMetric->getName() === 'euclidean') {
            $distance = 'euclidean';
        }

        $payload = [
            'name' => $this->collectionName,
            'vector' => [
                'dimension' => $this->dimension,
                'metric' => $distance,
            ],
        ];

        $url = $this->getCollectionUrl();
        $response = $this->httpClient->put(
            $url,
            $this->getHeaders(),
            $payload
        );
        $this->checkHttpResponse($response, 'initialize');
    }

    public function addDocument(Document $document): string
    {
        $this->ensureInitialized();
        $id = $document->getId();

        $payload = [
            'document_id' => $id,
            'content' => $document->getContent(),
            'metadata' => $document->getMetadata(),
            '$vector' => $document->getEmbedding(),
        ];

        $response = $this->httpClient->put(
            $this->getCollectionUrl() . '/' . $id,
            $this->getHeaders(),
            $payload
        );
        $this->checkHttpResponse($response, 'addDocument');

        return $id;
    }

    public function addDocuments(array $documents): array
    {
        $this->ensureInitialized();
        $ids = [];
        $docs = [];

        foreach ($documents as $document) {
            $id = $document->getId();
            $ids[] = $id;
            $docs[] = [
                'document_id' => $id,
                'content' => $document->getContent(),
                'metadata' => $document->getMetadata(),
                '$vector' => $document->getEmbedding(),
            ];
        }

        $response = $this->httpClient->post(
            $this->getCollectionUrl() . '/bulk',
            $this->getHeaders(),
            $docs
        );
        $this->checkHttpResponse($response, 'addDocuments');

        return $ids;
    }

    public function delete(string $id): bool
    {
        $response = $this->httpClient->delete(
            $this->getCollectionUrl() . '/' . $id,
            $this->getHeaders()
        );
        $this->checkHttpResponse($response, 'delete');
        return true;
    }

    public function deleteBatch(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        $response = $this->httpClient->post(
            $this->getCollectionUrl() . '/delete',
            $this->getHeaders(),
            ['document_ids' => $ids]
        );
        $this->checkHttpResponse($response, 'deleteBatch');

        return count($ids);
    }

    public function getById(string $id): ?Document
    {
        $response = $this->httpClient->get(
            $this->getCollectionUrl() . '/' . $id,
            $this->getHeaders()
        );
        $this->checkHttpResponse($response, 'getById');
        $data = Json::decode($response->getBody());
        if (empty($data)) {
            return null;
        }

        return $this->rowToDocument($data);
    }

    public function list(array $filters = []): array
    {
        $payload = [
            'find' => [],
        ];

        if (!empty($filters)) {
            $payload['find'] = $filters;
        }

        $response = $this->httpClient->post(
            $this->getCollectionUrl() . '/find',
            $this->getHeaders(),
            $payload
        );
        $this->checkHttpResponse($response, 'list');
        $data = Json::decode($response->getBody());
        $docs = $data['data'] ?? [];

        $documents = [];
        foreach ($docs as $doc) {
            $documents[] = $this->rowToDocument($doc);
        }

        return $documents;
    }

    public function similaritySearch(
        array $queryVector,
        int $topK = 5,
        float $threshold = 0.0,
        array $filters = []
    ): array {
        $payload = [
            'sort' => ['$vector' => $queryVector],
            'limit' => $topK,
            'includeVector' => false,
        ];

        if (!empty($filters)) {
            $payload['find'] = $filters;
        }

        $response = $this->httpClient->post(
            $this->getCollectionUrl() . '/find',
            $this->getHeaders(),
            $payload
        );
        $this->checkHttpResponse($response, 'similaritySearch');
        $data = Json::decode($response->getBody());
        $docs = $data['data'] ?? [];

        $results = [];
        foreach ($docs as $doc) {
            $results[] = [
                'document' => $this->rowToDocument($doc),
                'score' => $doc['$similarity'] ?? 0,
            ];
        }

        return $results;
    }

    public function count(): int
    {
        $response = $this->httpClient->get(
            $this->getCollectionUrl(),
            $this->getHeaders()
        );
        $this->checkHttpResponse($response, 'count');
        $data = Json::decode($response->getBody());
        return (int)($data['count'] ?? 0);
    }

    /**
     * 确保集合已初始化，如果不存在则自动创建
     */
    private function ensureInitialized(): void
    {
        if ($this->initialized) {
            return;
        }

        try {
            $response = $this->httpClient->get(
                $this->getCollectionUrl(),
                $this->getHeaders()
            );
            if ($response->isSuccess()) {
                $data = Json::decode($response->getBody());
                if (!empty($data)) {
                    $this->initialized = true;
                    return;
                }
            }
        } catch (\Exception $e) {
        }

        $this->initialize();
        $this->initialized = true;
    }

    public function clear(): void
    {
        $response = $this->httpClient->delete(
            $this->getCollectionUrl(),
            $this->getHeaders()
        );
        $this->checkHttpResponse($response, 'clear');
        $this->initialized = false;
    }

    /**
     * @return string
     */
    private function getCollectionUrl(): string
    {
        $url = $this->baseUrl . '/api/v2/namespaces/' . $this->namespace . '/collections/' . $this->collectionName;
        return $url;
    }

    /**
     * @return array<string, string>
     */
    private function getHeaders(): array
    {
        $headers = ['Content-Type' => 'application/json'];
        if ($this->apiKey) {
            $headers['Authorization'] = 'Bearer ' . $this->apiKey;
        }
        return $headers;
    }

    /**
     * @param array<string, mixed> $doc
     * @return Document
     */
    private function rowToDocument(array $doc): Document
    {
        return new Document([
            'id' => $doc['document_id'] ?? $doc['id'] ?? '',
            'content' => $doc['content'] ?? '',
            'embedding' => $doc['$vector'] ?? [],
            'metadata' => $doc['metadata'] ?? [],
        ]);
    }
}