<?php

declare(strict_types=1);

namespace PhpLLP\VectorStore;

use PhpLLP\Embeddings\Distances\CosineDistance;
use PhpLLP\Embeddings\Distances\Distance;

class VectorStoreFactory
{
    const TYPE_FILE_SYSTEM = 'filesystem';
    const TYPE_SQLITE = 'sqlite';
    const TYPE_POSTGRES = 'postgres';
    const TYPE_QDRANT = 'qdrant';
    const TYPE_REDIS = 'redis';
    const TYPE_ELASTICSEARCH = 'elasticsearch';
    const TYPE_MILVUS = 'milvus';
    const TYPE_CHROMA = 'chroma';
    const TYPE_ASTRA = 'astra';

    /**
     * Create a vector store instance
     *
     * @param string $type
     * @param array<string, mixed> $config
     * @return VectorStoreBase
     * @throws \InvalidArgumentException
     */
    public static function create(string $type, array $config = []): VectorStoreBase
    {
        $distanceMetric = $config['distance_metric'] ?? new CosineDistance();
        $collectionName = $config['collection'] ?? 'default';
        $dimension = $config['dimension'] ?? 1536;
		$type = strtolower($type);
        switch ($type) {
            case self::TYPE_FILE_SYSTEM:
                return new FileSystemVectorStore($collectionName, $distanceMetric, $dimension, $config['path'] ?? './vector_store');

            case self::TYPE_SQLITE:
                return new SQLiteVectorStore($collectionName, $distanceMetric, $dimension, $config['db_path'] ?? ':memory:');

            case self::TYPE_POSTGRES:
                return new PostgresVectorStore($collectionName, $distanceMetric, $dimension, $config);

            case self::TYPE_QDRANT:
                return new QdrantVectorStore($collectionName, $distanceMetric, $dimension, $config);

            case self::TYPE_REDIS:
                return new RedisVectorStore($collectionName, $distanceMetric, $dimension, $config);

            case self::TYPE_ELASTICSEARCH:
                return new ElasticsearchVectorStore($collectionName, $distanceMetric, $dimension, $config);

            case self::TYPE_MILVUS:
                return new MilvusVectorStore($collectionName, $distanceMetric, $dimension, $config);

            case self::TYPE_CHROMA:
                return new ChromaDBVectorStore($collectionName, $distanceMetric, $dimension, $config);

            case self::TYPE_ASTRA:
                return new AstraDBVectorStore($collectionName, $distanceMetric, $dimension, $config);

            default:
                throw new \InvalidArgumentException("不支持的向量存储类型: {$type}");
        }
    }

    /**
     * @return string[]
     */
    public static function availableTypes(): array
    {
        return [
            self::TYPE_FILE_SYSTEM,
            self::TYPE_SQLITE,
            self::TYPE_POSTGRES,
            self::TYPE_QDRANT,
            self::TYPE_REDIS,
            self::TYPE_ELASTICSEARCH,
            self::TYPE_MILVUS,
            self::TYPE_CHROMA,
            self::TYPE_ASTRA,
        ];
    }
}