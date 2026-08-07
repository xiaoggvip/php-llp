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

    /** @var HttpClient|null */
    private $httpClient;

    /** @var string */
    private $baseUrl;

    /** @var string */
    private $indexName;

    /** @var bool */
    private $initialized = false;

    /** @var \Redis|null */
    private $redis = null;

    /** @var bool */
    private $useRedisExtension = false;

    /** @var bool */
    private $rediSearchAvailable = false;

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
        $this->indexName = $config['index_name'] ?? 'phpLLP_idx_' . $collectionName;

        $this->useRedisExtension = extension_loaded('redis');

        if ($this->useRedisExtension) {
            $this->initRedisConnection();
            $this->checkRediSearchAvailable();
        } else {
            $this->httpClient = new HttpClient();
            $this->baseUrl = rtrim($config['base_url'] ?? 'http://localhost:6379', '/');
        }
    }

    /**
     * 初始化 PHP Redis 扩展连接
     */
    private function initRedisConnection(): void
    {
        $host = $this->config['host'] ?? '127.0.0.1';
        $port = $this->config['port'] ?? 6379;
        $password = $this->config['password'] ?? '';
        $database = $this->config['database'] ?? 0;

        $this->redis = new \Redis();
        $this->redis->connect($host, $port);

        if (!empty($password)) {
            $this->redis->auth($password);
        }

        if ($database > 0) {
            $this->redis->select($database);
        }
    }

    /**
     * 检测 Redis 服务器是否支持 RediSearch 模块
     */
    private function checkRediSearchAvailable(): void
    {
        if ($this->redis === null) {
            return;
        }

        try {
            $result = $this->redis->rawCommand('FT.INFO', '__probe__');
            if (is_array($result)) {
                $this->rediSearchAvailable = true;
                return;
            }
            $this->rediSearchAvailable = false;
        } catch (\Exception $e) {
            $this->rediSearchAvailable = false;
        }
    }

    /**
     * 获取 Redis key 前缀
     */
    private function keyPrefix(): string
    {
        return $this->indexName . ':';
    }

    /**
     * 获取文档 ID 集合的 key
     */
    private function idsKey(): string
    {
        return $this->indexName . ':__ids';
    }

    /**
     * 生成文档 key
     */
    private function docKey(string $id): string
    {
        return $this->keyPrefix() . $id;
    }

    /**
     * 扫描获取所有文档 ID
     * 使用 rawCommand 执行 SCAN，兼容 Redis 3.x 及以上版本
     *
     * @return string[]
     */
    private function scanAllDocIds(): array
    {
        if ($this->redis === null) {
            return [];
        }

        $ids = [];
        $cursor = 0;
        $pattern = $this->keyPrefix() . '*';

        do {
            $result = $this->redisRawCommand('SCAN', [
                $cursor,
                'MATCH', $pattern,
                'COUNT', 500,
            ]);

            if ($result === false || !is_array($result)) {
                break;
            }

            $cursor = (int)($result[0] ?? 0);
            $keys = $result[1] ?? [];

            if (!empty($keys) && is_array($keys)) {
                foreach ($keys as $key) {
                    $id = substr($key, strlen($this->keyPrefix()));
                    if ($id !== '__ids' && $id !== '') {
                        $ids[] = $id;
                    }
                }
            }
        } while ($cursor > 0);

        return $ids;
    }

    /**
     * 执行 Redis 原始命令
     * 兼容不同版本的 PHP Redis 扩展
     *
     * @param string $command
     * @param array<int, mixed> $args
     * @return mixed
     */
    private function redisRawCommand(string $command, array $args = [])
    {
        if ($this->redis === null) {
            throw new \RuntimeException('Redis connection not established');
        }

        if (method_exists($this->redis, 'rawCommand')) {
            return $this->redis->rawCommand($command, ...$args);
        }

        $commandLower = strtolower($command);

        switch ($commandLower) {
            case 'del':
                if (count($args) === 1) {
                    return $this->redis->del($args[0]);
                }
                $result = 0;
                foreach ($args as $key) {
                    $result += (int)$this->redis->del($key);
                }
                return $result;
            case 'hset':
                return call_user_func_array([$this->redis, 'hSet'], $args);
            case 'hgetall':
                return call_user_func_array([$this->redis, 'hGetAll'], $args);
            case 'ft.info':
            case 'ft.create':
            case 'ft.drop':
            case 'ft.search':
            case 'ft.aggregate':
                throw new \RuntimeException("RediSearch command {$command} not supported by this Redis extension version");
            default:
                if (method_exists($this->redis, $commandLower)) {
                    return call_user_func_array([$this->redis, $commandLower], $args);
                }
                throw new \RuntimeException("Command {$command} not supported by this Redis extension version");
        }
    }

    public function initialize(): void
    {
        if ($this->useRedisExtension && $this->rediSearchAvailable) {
            $this->initializeViaRediSearch();
        } elseif ($this->useRedisExtension) {
            $this->initializeViaHash();
        } else {
            $this->initializeViaHttp();
        }
    }

    /**
     * 通过 RediSearch 创建索引
     */
    private function initializeViaRediSearch(): void
    {
        $distance = 'COSINE';
        if ($this->distanceMetric->getName() === 'euclidean') {
            $distance = 'EUCLIDEAN';
        }

        $this->redisRawCommand('FT.CREATE', [
            $this->indexName,
            'ON', 'HASH',
            'PREFIX', '1',
            'SCHEMA',
            'content', 'TEXT',
            'metadata', 'TEXT',
            'hash', 'TEXT',
            'vector', 'VECTOR', 'FLAT', 'TYPE', 'FLOAT32', 'DIM', (string)$this->dimension,
            'DISTANCE_METRIC', $distance,
        ]);
    }

    /**
     * 通过 Hash 模式初始化（兼容 Redis 3.x）
     */
    private function initializeViaHash(): void
    {
    }

    /**
     * 通过 HTTP 创建索引
     */
    private function initializeViaHttp(): void
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
        $this->ensureInitialized();
        $id = $document->getId();

        if ($this->useRedisExtension && $this->rediSearchAvailable) {
            $this->addDocumentViaRediSearch($id, $document);
        } elseif ($this->useRedisExtension) {
            $this->addDocumentViaHash($id, $document);
        } else {
            $this->addDocumentViaHttp($id, $document);
        }

        return $id;
    }

    /**
     * 通过 RediSearch 添加文档
     */
    private function addDocumentViaRediSearch(string $id, Document $document): void
    {
        $this->redisRawCommand('HSET', [
            $this->docKey($id),
            'content', $document->getContent(),
            'metadata', Json::encode($document->getMetadata(), JSON_UNESCAPED_UNICODE),
            'hash', $document->getHash(),
            'vector', $this->vectorToBinary($document->getEmbedding()),
        ]);
    }

    /**
     * 通过 Hash 模式添加文档（兼容 Redis 3.x）
     */
    private function addDocumentViaHash(string $id, Document $document): void
    {
        if ($this->redis === null) {
            return;
        }

        $this->redis->hSet($this->docKey($id), 'content', $document->getContent());
        $this->redis->hSet($this->docKey($id), 'metadata', Json::encode($document->getMetadata(), JSON_UNESCAPED_UNICODE));
        $this->redis->hSet($this->docKey($id), 'hash', $document->getHash());
        $this->redis->hSet($this->docKey($id), 'vector', $this->vectorToBinary($document->getEmbedding()));

        $this->redis->sAdd($this->idsKey(), $id);
    }

    /**
     * 通过 HTTP 添加文档
     */
    private function addDocumentViaHttp(string $id, Document $document): void
    {
        $this->httpClient->post(
            $this->baseUrl . '/ft.add',
            ['Content-Type' => 'application/json'],
            [
                'indexName' => $this->indexName,
                'docId' => $id,
                'fields' => [
                    'content' => $document->getContent(),
                    'metadata' => Json::encode($document->getMetadata(), JSON_UNESCAPED_UNICODE),
                    'hash' => $document->getHash(),
                    'vector' => $this->vectorToBinary($document->getEmbedding()),
                ],
            ]
        );
    }

    public function addDocuments(array $documents): array
    {
        $this->ensureInitialized();
        $ids = [];
        foreach ($documents as $document) {
            $ids[] = $this->addDocument($document);
        }
        return $ids;
    }

    public function delete(string $id): bool
    {
        if ($this->useRedisExtension) {
            if ($this->rediSearchAvailable) {
                $this->redisRawCommand('DEL', [$this->docKey($id)]);
            } elseif ($this->redis !== null) {
                $this->redis->del($this->docKey($id));
                $this->redis->sRem($this->idsKey(), $id);
            }
        } else {
            $this->httpClient->post(
                $this->baseUrl . '/ft.del',
                ['Content-Type' => 'application/json'],
                [
                    'indexName' => $this->indexName,
                    'docId' => $id,
                ]
            );
        }
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
        if ($this->useRedisExtension) {
            if ($this->rediSearchAvailable) {
                return $this->getByIdViaRedis($id);
            }
            return $this->getByIdViaHash($id);
        }
        return $this->getByIdViaHttp($id);
    }

    /**
     * 通过 RediSearch/Hash 获取文档
     */
    private function getByIdViaRedis(string $id): ?Document
    {
        $result = $this->redisRawCommand('HGETALL', [$this->docKey($id)]);

        if (empty($result)) {
            return null;
        }

        $fields = [];
        for ($i = 0; $i < count($result); $i += 2) {
            $fields[$result[$i]] = $result[$i + 1];
        }

        return $this->fieldsToDocument($id, $fields);
    }

    /**
     * 通过 Hash 模式获取文档（兼容 Redis 3.x）
     */
    private function getByIdViaHash(string $id): ?Document
    {
        if ($this->redis === null) {
            return null;
        }

        $fields = $this->redis->hGetAll($this->docKey($id));

        if (empty($fields)) {
            return null;
        }

        return $this->fieldsToDocument($id, $fields);
    }

    /**
     * 通过 HTTP 获取文档
     */
    private function getByIdViaHttp(string $id): ?Document
    {
        $response = $this->httpClient->get(
            $this->baseUrl . '/ft.get?indexName=' . $this->indexName . '&docId=' . urlencode($id)
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
        if ($this->useRedisExtension) {
            if ($this->rediSearchAvailable) {
                return $this->listViaRediSearch($filters);
            }
            return $this->listViaHash($filters);
        }
        return $this->listViaHttp($filters);
    }

    /**
     * 通过 RediSearch 列出文档
     */
    private function listViaRediSearch(array $filters): array
    {
        $query = '*';
        if (!empty($filters)) {
            $conditions = [];
            foreach ($filters as $key => $value) {
                $conditions[] = "@metadata:\"{$value}\"";
            }
            $query = implode(' ', $conditions);
        }

        $result = $this->redisRawCommand('FT.SEARCH', [
            $this->indexName,
            $query,
            'LIMIT', '0', '10000',
        ]);

        return $this->parseFtSearchResult($result);
    }

    /**
     * 通过 Hash 模式列出文档（兼容 Redis 3.x）
     *
     * @param array<string, mixed> $filters
     * @return Document[]
     */
    private function listViaHash(array $filters): array
    {
        if ($this->redis === null) {
            return [];
        }

        $ids = $this->scanAllDocIds();
        $documents = [];

        foreach ($ids as $id) {
            $fields = $this->redis->hGetAll($this->docKey($id));
            if (empty($fields)) {
                continue;
            }

            if (!empty($filters)) {
                $metadata = json_decode($fields['metadata'] ?? '', true);
                if (!is_array($metadata)) {
                    $metadata = [];
                }
                $match = true;
                foreach ($filters as $key => $value) {
                    if (!isset($metadata[$key]) || $metadata[$key] != $value) {
                        $match = false;
                        break;
                    }
                }
                if (!$match) {
                    continue;
                }
            }

            $documents[] = $this->fieldsToDocument($id, $fields);
        }

        return $documents;
    }

    /**
     * 通过 HTTP 列出文档
     */
    private function listViaHttp(array $filters): array
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
        if ($this->useRedisExtension) {
            if ($this->rediSearchAvailable) {
                return $this->similaritySearchViaRediSearch($queryVector, $topK, $threshold, $filters);
            }
            return $this->similaritySearchViaHash($queryVector, $topK, $threshold, $filters);
        }
        return $this->similaritySearchViaHttp($queryVector, $topK, $threshold, $filters);
    }

    /**
     * 通过 RediSearch 进行相似度搜索
     */
    private function similaritySearchViaRediSearch(
        array $queryVector,
        int $topK,
        float $threshold,
        array $filters
    ): array {
        $query = '*=>[KNN ' . $topK . ' @vector $vec AS score]';
        $params = json_encode(['vec' => $queryVector]);

        $args = [
            $this->indexName,
            $query,
            'PARAMS', '2', $params,
            'SORTBY', 'score',
            'LIMIT', '0', (string)$topK,
            'DIALECT', '2',
        ];

        if ($threshold > 0) {
            $args[] = 'FILTER';
            $args[] = "score <= {$threshold}";
        }

        $result = $this->redisRawCommand('FT.SEARCH', $args);

        return $this->parseFtSearchResultWithScore($result);
    }

    /**
     * 通过 Hash 模式进行相似度搜索（兼容 Redis 3.x）
     * 在 PHP 端计算所有向量的相似度
     *
     * @param array<int, float> $queryVector
     * @param int $topK
     * @param float $threshold
     * @param array<string, mixed> $filters
     * @return array<int, array{document: Document, score: float}>
     */
    private function similaritySearchViaHash(
        array $queryVector,
        int $topK,
        float $threshold,
        array $filters
    ): array {
        if ($this->redis === null) {
            return [];
        }

        $ids = $this->scanAllDocIds();
        $candidates = [];

        foreach ($ids as $id) {
            $fields = $this->redis->hGetAll($this->docKey($id));
            if (empty($fields) || empty($fields['vector'])) {
                continue;
            }

            if (!empty($filters)) {
                $metadata = json_decode($fields['metadata'] ?? '', true);
                if (!is_array($metadata)) {
                    $metadata = [];
                }
                $match = true;
                foreach ($filters as $key => $value) {
                    if (!isset($metadata[$key]) || $metadata[$key] != $value) {
                        $match = false;
                        break;
                    }
                }
                if (!$match) {
                    continue;
                }
            }

            $storedVector = $this->binaryToVector($fields['vector']);
            if (empty($storedVector)) {
                continue;
            }

            $score = $this->distanceMetric->calculate($queryVector, $storedVector);

            if ($threshold > 0 && $score > $threshold) {
                continue;
            }

            $candidates[] = [
                'id' => $id,
                'fields' => $fields,
                'score' => $score,
            ];
        }

        usort($candidates, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        $topK = min($topK, count($candidates));
        $results = [];

        for ($i = 0; $i < $topK; $i++) {
            $results[] = [
                'document' => $this->fieldsToDocument($candidates[$i]['id'], $candidates[$i]['fields']),
                'score' => $candidates[$i]['score'],
            ];
        }

        return $results;
    }

    /**
     * 通过 HTTP 进行相似度搜索
     */
    private function similaritySearchViaHttp(
        array $queryVector,
        int $topK,
        float $threshold,
        array $filters
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

    /**
     * 解析 FT.SEARCH 结果（仅返回文档）
     *
     * @param mixed $result
     * @return Document[]
     */
    private function parseFtSearchResult($result): array
    {
        if (!is_array($result)) {
            return [];
        }

        $total = array_shift($result);
        $documents = [];

        while (count($result) > 0) {
            $id = array_shift($result);
            $fields = [];
            $fieldCount = array_shift($result);
            for ($i = 0; $i < $fieldCount; $i++) {
                $key = array_shift($result);
                $value = array_shift($result);
                $fields[$key] = $value;
            }
            $documents[] = $this->fieldsToDocument((string)$id, $fields);
        }

        return $documents;
    }

    /**
     * 解析 FT.SEARCH 结果（带分数）
     *
     * @param mixed $result
     * @return array<int, array{document: Document, score: float}>
     */
    private function parseFtSearchResultWithScore($result): array
    {
        if (!is_array($result)) {
            return [];
        }

        $total = array_shift($result);
        $searchResults = [];

        while (count($result) > 0) {
            $id = array_shift($result);
            $fields = [];
            $fieldCount = array_shift($result);
            $score = 0;

            for ($i = 0; $i < $fieldCount; $i++) {
                $key = array_shift($result);
                $value = array_shift($result);
                if ($key === 'score') {
                    $score = (float)$value;
                }
                $fields[$key] = $value;
            }

            $searchResults[] = [
                'document' => $this->fieldsToDocument((string)$id, $fields),
                'score' => $score,
            ];
        }

        return $searchResults;
    }

    public function count(): int
    {
        if ($this->useRedisExtension) {
            if ($this->rediSearchAvailable) {
                return $this->countViaRediSearch();
            }
            return $this->countViaHash();
        }
        return $this->countViaHttp();
    }

    /**
     * 通过 RediSearch 获取文档数量
     */
    private function countViaRediSearch(): int
    {
        try {
            $result = $this->redisRawCommand('FT.INFO', [$this->indexName]);
            if (is_array($result)) {
                $info = [];
                for ($i = 0; $i < count($result); $i += 2) {
                    $info[$result[$i]] = $result[$i + 1];
                }
                return (int)($info['num_docs'] ?? 0);
            }
        } catch (\Exception $e) {
        }

        return 0;
    }

    /**
     * 通过 Hash 模式获取文档数量（兼容 Redis 3.x）
     */
    private function countViaHash(): int
    {
        if ($this->redis === null) {
            return 0;
        }

        return count($this->scanAllDocIds());
    }

    /**
     * 通过 HTTP 获取文档数量
     */
    private function countViaHttp(): int
    {
        try {
            $response = $this->httpClient->get(
                $this->baseUrl . '/ft.info?indexName=' . $this->indexName
            );
            $data = Json::decode($response->getBody());
            return (int)($data['numDocs'] ?? 0);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * 确保索引已初始化，如果不存在则自动创建
     */
    private function ensureInitialized(): void
    {
        if ($this->initialized) {
            return;
        }

        if ($this->useRedisExtension) {
            if ($this->rediSearchAvailable) {
                $this->ensureInitializedRediSearch();
            } else {
                $this->ensureInitializedHash();
            }
        } else {
            $this->ensureInitializedHttp();
        }
    }

    /**
     * 通过 RediSearch 检查并初始化
     */
    private function ensureInitializedRediSearch(): void
    {
        try {
            $result = $this->redisRawCommand('FT.INFO', [$this->indexName]);
            if (is_array($result) && count($result) > 0) {
                $this->initialized = true;
                return;
            }
        } catch (\Exception $e) {
        }

        $this->initialize();
        $this->initialized = true;
    }

    /**
     * 通过 Hash 模式检查并初始化（兼容 Redis 3.x）
     */
    private function ensureInitializedHash(): void
    {
        $this->initialize();
        $this->initialized = true;
    }

    /**
     * 通过 HTTP 检查并初始化
     */
    private function ensureInitializedHttp(): void
    {
        try {
            $response = $this->httpClient->get(
                $this->baseUrl . '/ft.info?indexName=' . $this->indexName
            );
            $data = Json::decode($response->getBody());
            if (is_array($data) && isset($data['numDocs'])) {
                $this->initialized = true;
                return;
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
        if ($this->useRedisExtension) {
            if ($this->rediSearchAvailable) {
                try {
                    $this->redisRawCommand('FT.DROP', [$this->indexName]);
                } catch (\Exception $e) {
                }
            } elseif ($this->redis !== null) {
                $ids = $this->scanAllDocIds();
                foreach ($ids as $id) {
                    $this->redis->del($this->docKey($id));
                }
                $this->redis->del($this->idsKey());
            }
            $this->initialize();
            $this->initialized = true;
        } else {
            try {
                $this->httpClient->post(
                    $this->baseUrl . '/ft.drop',
                    ['Content-Type' => 'application/json'],
                    ['indexName' => $this->indexName]
                );
            } catch (\Exception $e) {
            }
            $this->initialize();
            $this->initialized = true;
        }
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

        $embedding = [];
        if (isset($fields['vector'])) {
            if (is_string($fields['vector'])) {
                $embedding = $this->binaryToVector($fields['vector']);
            } elseif (is_array($fields['vector'])) {
                $embedding = $fields['vector'];
            }
        }

        return new Document([
            'id' => $id,
            'content' => $fields['content'] ?? '',
            'embedding' => $embedding,
            'metadata' => $metadata,
            'hash' => $fields['hash'] ?? '',
        ]);
    }

    /**
     * 将 base64 编码的二进制向量转换为数组
     *
     * @param string $binary
     * @return array<int, float>
     */
    private function binaryToVector(string $binary): array
    {
        $decoded = base64_decode($binary);
        if ($decoded === false) {
            return [];
        }

        $vector = [];
        $length = strlen($decoded);
        for ($i = 0; $i < $length; $i += 4) {
            $value = unpack('f', substr($decoded, $i, 4));
            if ($value !== false && isset($value[1])) {
                $vector[] = $value[1];
            }
        }

        return $vector;
    }
}