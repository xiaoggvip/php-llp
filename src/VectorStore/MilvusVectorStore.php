<?php

declare(strict_types=1);

namespace PhpLLP\VectorStore;

use PhpLLP\Embeddings\Distances\Distance;
use PhpLLP\Embeddings\Document;
use PhpLLP\Http\HttpClient;
use PhpLLP\Http\HttpResponse;
use PhpLLP\Support\Json;

class MilvusVectorStore extends VectorStoreBase
{
    /** @var array<string, mixed> */
    private $config;

    /** @var HttpClient */
    private $httpClient;

    /** @var string */
    private $baseUrl;

    /** @var string */
    private $apiKey;

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
        $this->baseUrl = rtrim($config['base_url'] ?? 'http://localhost:19530', '/');
        $this->apiKey = $config['api_key'] ?? '';
    }

    public function initialize(): void
    {
        $distance = 'COSINE';
        if ($this->distanceMetric->getName() === 'euclidean') {
            $distance = 'L2';
        }

        $payload = [
            'collectionName' => $this->collectionName,
            'schema' => [
                'autoId' => false,
                'enabledDynamicField' => false,
                'fields' => [
                    [
                        'fieldName' => 'id',
                        'dataType' => 'VarChar',
                        'isPrimary' => true,
                        'elementTypeParams' => [
                            'max_length' => 256,
                        ],
                    ],
                    [
                        'fieldName' => 'content',
                        'dataType' => 'VarChar',
                        'elementTypeParams' => [
                            'max_length' => 65535,
                        ],
                    ],
                    [
                        'fieldName' => 'embedding',
                        'dataType' => 'FloatVector',
                        'elementTypeParams' => [
                            'dim' => (string)$this->dimension,
                        ],
                    ],
                    [
                        'fieldName' => 'metadata',
                        'dataType' => 'VarChar',
                        'elementTypeParams' => [
                            'max_length' => 65535,
                        ],
                    ],
                    [
                        'fieldName' => 'hash',
                        'dataType' => 'VarChar',
                        'elementTypeParams' => [
                            'max_length' => 64,
                        ],
                    ],
                ],
            ],
            'indexParams' => [
                [
                    'fieldName' => 'embedding',
                    'metricType' => $distance,
                    'indexName' => 'embedding_index',
                    'indexType' => 'AUTOINDEX',
                ],
                [
                    'fieldName' => 'id',
                    'indexName' => 'id_index',
                    'indexType' => 'AUTOINDEX',
                ],
            ],
        ];

        $response = $this->httpClient->post(
            $this->baseUrl . '/v2/vectordb/collections/create',
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
            'collectionName' => $this->collectionName,
            'data' => [
                [
                    'id' => $id,
                    'content' => $document->getContent(),
                    'embedding' => $document->getEmbedding(),
                    'metadata' => json_encode($document->getMetadata(), JSON_UNESCAPED_UNICODE),
                    'hash' => $document->getHash(),
                ],
            ],
        ];

        $response = $this->httpClient->post(
            $this->baseUrl . '/v2/vectordb/entities/insert',
            $this->getHeaders(),
            $payload
        );
        $this->checkHttpResponse($response, 'addDocument');

        return $id;
    }

    public function addDocuments(array $documents): array
    {
        $this->ensureInitialized();
        $data = [];
        $ids = [];

        foreach ($documents as $document) {
            $id = $document->getId();
            $ids[] = $id;
            $data[] = [
                'id' => $id,
                'content' => $document->getContent(),
                'embedding' => $document->getEmbedding(),
                'metadata' => json_encode($document->getMetadata(), JSON_UNESCAPED_UNICODE),
                'hash' => $document->getHash(),
            ];
        }

        $response = $this->httpClient->post(
            $this->baseUrl . '/v2/vectordb/entities/insert',
            $this->getHeaders(),
            [
                'collectionName' => $this->collectionName,
                'data' => $data,
            ]
        );
        $this->checkHttpResponse($response, 'addDocuments');

        return $ids;
    }

    public function delete(string $id): bool
    {
        $response = $this->httpClient->post(
            $this->baseUrl . '/v2/vectordb/entities/delete',
            $this->getHeaders(),
            [
                'collectionName' => $this->collectionName,
                'filter' => 'id == "' . $id . '"',
            ]
        );
        $this->checkHttpResponse($response, 'delete');
        return true;
    }

    public function deleteBatch(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        $expr = 'id in [' . implode(',', array_map(function ($id) { return '"' . $id . '"'; }, $ids)) . ']';

        $response = $this->httpClient->post(
            $this->baseUrl . '/v2/vectordb/entities/delete',
            $this->getHeaders(),
            [
                'collectionName' => $this->collectionName,
                'filter' => $expr,
            ]
        );
        $this->checkHttpResponse($response, 'deleteBatch');

        return count($ids);
    }

    public function getById(string $id): ?Document
    {
        $response = $this->httpClient->post(
            $this->baseUrl . '/v2/vectordb/entities/query',
            $this->getHeaders(),
            [
                'collectionName' => $this->collectionName,
                'filter' => 'id == "' . $id . '"',
                'outputFields' => ['content', 'metadata', 'hash'],
            ]
        );
        $this->checkHttpResponse($response, 'getById');
        $data = Json::decode($response->getBody());
        $results = $data['data'] ?? [];

        if (empty($results)) {
            return null;
        }

        return $this->rowToDocument($results[0]);
    }

    public function list(array $filters = []): array
    {
        $payload = [
            'collectionName' => $this->collectionName,
            'limit' => 10000,
            'outputFields' => ['content', 'metadata', 'hash'],
        ];

        if (!empty($filters)) {
            $conditions = [];
            foreach ($filters as $key => $value) {
                $conditions[] = 'metadata LIKE "%' . $value . '%"';
            }
            $payload['filter'] = implode(' and ', $conditions);
        }

        $response = $this->httpClient->post(
            $this->baseUrl . '/v2/vectordb/entities/query',
            $this->getHeaders(),
            $payload
        );
        $this->checkHttpResponse($response, 'list');
        $data = Json::decode($response->getBody());
        $results = $data['data'] ?? [];

        $documents = [];
        foreach ($results as $row) {
            $documents[] = $this->rowToDocument($row);
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
            'collectionName' => $this->collectionName,
            'data' => [$queryVector],
            'annsField' => 'embedding',
            'limit' => $topK,
            'outputFields' => ['content', 'metadata', 'hash'],
        ];

        if (!empty($filters)) {
            $conditions = [];
            foreach ($filters as $key => $value) {
                $conditions[] = 'metadata LIKE "%' . $value . '%"';
            }
            $payload['filter'] = implode(' and ', $conditions);
        }

        if ($threshold > 0) {
            $payload['scoreThreshold'] = $threshold;
        }

        $response = $this->httpClient->post(
            $this->baseUrl . '/v2/vectordb/entities/search',
            $this->getHeaders(),
            $payload
        );
        $this->checkHttpResponse($response, 'similaritySearch');
        $data = Json::decode($response->getBody());
        $results = $data['data'] ?? [];

        $searchResults = [];
        foreach ($results as $row) {
            $searchResults[] = [
                'document' => $this->rowToDocument($row),
                'score' => $row['distance'] ?? 0,
            ];
        }

        return $searchResults;
    }

    public function count(): int
    {
        try {
            $response = $this->httpClient->post(
                $this->baseUrl . '/v2/vectordb/collections/describe',
                $this->getHeaders(),
                ['collectionName' => $this->collectionName]
            );

            if ($response->isSuccess()) {
                $body = $response->getBody();
                if (!empty($body)) {
                    $data = Json::decode($body);
                    if (is_array($data) && isset($data['entityCount'])) {
                        return (int)$data['entityCount'];
                    }
                }
            }
        } catch (\Exception $e) {
        }

        return 0;
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
            $response = $this->httpClient->post(
                $this->baseUrl . '/v2/vectordb/collections/describe',
                $this->getHeaders(),
                ['collectionName' => $this->collectionName]
            );

            if ($response->isSuccess()) {
                $body = $response->getBody();
                if (!empty($body)) {
                    $data = Json::decode($body);
                    if (is_array($data) && (
                        isset($data['entity']) ||
                        isset($data['collectionName']) ||
                        !empty($data['data'])
                    )) {
                        $this->initialized = true;
                        return;
                    }
                }
            }
        } catch (\Exception $e) {
        }

        try {
            $this->initialize();
        } catch (\Exception $e) {
        }
        $this->initialized = true;
    }

    public function clear(): void
    {
        $response = $this->httpClient->post(
            $this->baseUrl . '/v2/vectordb/collections/drop',
            $this->getHeaders(),
            ['collectionName' => $this->collectionName]
        );
        $this->checkHttpResponse($response, 'clear');
        $this->initialized = false;
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
     * @param array<string, mixed> $row
     * @return Document
     */
    private function rowToDocument(array $row): Document
    {
        $metadata = [];
        if (isset($row['metadata'])) {
            if (is_array($row['metadata'])) {
                $metadata = $row['metadata'];
            } else {
                $decoded = json_decode($row['metadata'], true);
                $metadata = is_array($decoded) ? $decoded : [];
            }
        }

        return new Document([
            'id' => $row['id'] ?? '',
            'content' => $row['content'] ?? '',
            'embedding' => isset($row['embedding']) && is_array($row['embedding']) ? $row['embedding'] : [],
            'metadata' => $metadata,
            'hash' => $row['hash'] ?? '',
        ]);
    }
}