<?php

declare(strict_types=1);

namespace PhpLLP\Tests\Integration;

use PhpLLP\Embeddings\Document;
use PhpLLP\Embeddings\Distances\CosineDistance;
use PhpLLP\VectorStore\VectorStoreFactory;
use PHPUnit\Framework\TestCase;

class VectorStoreIntegrationTest extends TestCase
{
    public function testFilesystemVectorStoreWorkflow(): void
    {
        $path = sys_get_temp_dir() . '/php_llp_integration_' . uniqid();

        $store = VectorStoreFactory::create('filesystem', [
            'collection' => 'integration_test',
            'path' => $path,
            'dimension' => 3,
        ]);

        $this->assertEquals(0, $store->count());

        $documents = [
            new Document([
                'content' => '苹果是一种水果',
                'embedding' => [0.9, 0.1, 0.0],
                'metadata' => ['category' => 'fruit'],
            ]),
            new Document([
                'content' => '香蕉含有钾元素',
                'embedding' => [0.85, 0.15, 0.0],
                'metadata' => ['category' => 'fruit'],
            ]),
            new Document([
                'content' => 'PHP 是编程语言',
                'embedding' => [0.0, 0.1, 0.95],
                'metadata' => ['category' => 'tech'],
            ]),
            new Document([
                'content' => 'Python 适合数据分析',
                'embedding' => [0.05, 0.15, 0.9],
                'metadata' => ['category' => 'tech'],
            ]),
        ];

        $ids = $store->addDocuments($documents);
        $this->assertCount(4, $ids);
        $this->assertEquals(4, $store->count());

        $queryVector = [0.88, 0.12, 0.0];
        $results = $store->similaritySearch($queryVector, 2, 0.5);
        $this->assertCount(2, $results);

        $this->assertGreaterThan(
            $results[1]['score'],
            $results[0]['score']
        );

        $techResults = $store->similaritySearch($queryVector, 5, 0.0, ['category' => 'tech']);
        foreach ($techResults as $result) {
            $this->assertEquals('tech', $result['document']->getMetadata()['category']);
        }

        $store->clear();
        $this->assertEquals(0, $store->count());

        $file = $path . '/integration_test.json';
        if (file_exists($file)) {
            unlink($file);
        }
        if (is_dir($path)) {
            @rmdir($path);
        }
    }

    public function testAvailableStoreTypes(): void
    {
        $types = VectorStoreFactory::availableTypes();
        $this->assertContains('filesystem', $types);
        $this->assertContains('sqlite', $types);
        $this->assertContains('qdrant', $types);
    }

    public function testInvalidStoreType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        VectorStoreFactory::create('invalid_type');
    }

    public function testCosineDistanceConsistency(): void
    {
        $cosine = new CosineDistance();

        $a = [1.0, 0.0, 0.0];
        $b = [0.7, 0.7, 0.0];

        $score1 = $cosine->calculate($a, $b);
        $score2 = $cosine->calculate($b, $a);

        $this->assertEqualsWithDelta($score1, $score2, 0.0001);
    }
}