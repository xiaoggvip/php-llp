<?php

declare(strict_types=1);

namespace PhpLLP\Tests\Unit\VectorStore;

use PhpLLP\Embeddings\Document;
use PhpLLP\Embeddings\Distances\CosineDistance;
use PhpLLP\VectorStore\FileSystemVectorStore;
use PHPUnit\Framework\TestCase;

class FileSystemVectorStoreTest extends TestCase
{
    /** @var string */
    private $testPath;

    /** @var FileSystemVectorStore */
    private $store;

    protected function setUp(): void
    {
        $this->testPath = sys_get_temp_dir() . '/php_llp_test_' . uniqid();
        $this->store = new FileSystemVectorStore(
            'test_collection',
            new CosineDistance(),
            3,
            $this->testPath
        );
    }

    protected function tearDown(): void
    {
        $file = $this->testPath . '/test_collection.json';
        if (file_exists($file)) {
            unlink($file);
        }
        $dir = $this->testPath;
        if (is_dir($dir) && count(scandir($dir)) <= 2) {
            rmdir($dir);
        }
    }

    public function testAddDocument(): void
    {
        $doc = new Document([
            'content' => '测试文档',
            'embedding' => [1.0, 0.0, 0.0],
        ]);

        $id = $this->store->addDocument($doc);
        $this->assertNotEmpty($id);
        $this->assertEquals(1, $this->store->count());
    }

    public function testAddDocuments(): void
    {
        $docs = [
            new Document(['content' => '文档1', 'embedding' => [1.0, 0.0, 0.0]]),
            new Document(['content' => '文档2', 'embedding' => [0.0, 1.0, 0.0]]),
            new Document(['content' => '文档3', 'embedding' => [0.0, 0.0, 1.0]]),
        ];

        $ids = $this->store->addDocuments($docs);
        $this->assertCount(3, $ids);
        $this->assertEquals(3, $this->store->count());
    }

    public function testGetById(): void
    {
        $doc = new Document([
            'content' => '查找测试',
            'embedding' => [0.1, 0.2, 0.3],
        ]);

        $id = $this->store->addDocument($doc);
        $found = $this->store->getById($id);

        $this->assertNotNull($found);
        $this->assertEquals('查找测试', $found->getContent());
    }

    public function testGetByIdNotFound(): void
    {
        $result = $this->store->getById('non-existent');
        $this->assertNull($result);
    }

    public function testDelete(): void
    {
        $doc = new Document([
            'content' => '删除测试',
            'embedding' => [1.0, 0.0, 0.0],
        ]);

        $id = $this->store->addDocument($doc);
        $this->assertEquals(1, $this->store->count());

        $result = $this->store->delete($id);
        $this->assertTrue($result);
        $this->assertEquals(0, $this->store->count());
    }

    public function testDeleteNonExistent(): void
    {
        $result = $this->store->delete('non-existent');
        $this->assertFalse($result);
    }

    public function testSimilaritySearch(): void
    {
        $docs = [
            new Document(['content' => '苹果', 'embedding' => [0.9, 0.1, 0.0]]),
            new Document(['content' => '香蕉', 'embedding' => [0.8, 0.2, 0.0]]),
            new Document(['content' => 'PHP', 'embedding' => [0.0, 0.1, 0.9]]),
        ];

        $this->store->addDocuments($docs);

        $queryVector = [0.85, 0.15, 0.0];
        $results = $this->store->similaritySearch($queryVector, 2, 0.5);

        $this->assertCount(2, $results);
        $this->assertGreaterThan(0, $results[0]['score']);
    }

    public function testSimilaritySearchWithThreshold(): void
    {
        $docs = [
            new Document(['content' => '文档1', 'embedding' => [0.99, 0.01, 0.0]]),
            new Document(['content' => '文档2', 'embedding' => [0.5, 0.5, 0.0]]),
        ];

        $this->store->addDocuments($docs);

        $queryVector = [1.0, 0.0, 0.0];
        $results = $this->store->similaritySearch($queryVector, 5, 0.9);

        $this->assertLessThanOrEqual(2, count($results));
        foreach ($results as $result) {
            $this->assertGreaterThanOrEqual(0.9, $result['score']);
        }
    }

    public function testList(): void
    {
        $docs = [
            new Document(['content' => 'A', 'embedding' => [1.0, 0.0, 0.0], 'metadata' => ['cat' => 'fruit']]),
            new Document(['content' => 'B', 'embedding' => [0.0, 1.0, 0.0], 'metadata' => ['cat' => 'tech']]),
        ];

        $this->store->addDocuments($docs);

        $all = $this->store->list();
        $this->assertCount(2, $all);

        $filtered = $this->store->list(['cat' => 'fruit']);
        $this->assertCount(1, $filtered);
    }

    public function testClear(): void
    {
        $docs = [
            new Document(['content' => 'A', 'embedding' => [1.0, 0.0, 0.0]]),
            new Document(['content' => 'B', 'embedding' => [0.0, 1.0, 0.0]]),
        ];

        $this->store->addDocuments($docs);
        $this->assertEquals(2, $this->store->count());

        $this->store->clear();
        $this->assertEquals(0, $this->store->count());
    }

    public function testPersistence(): void
    {
        $doc = new Document([
            'content' => '持久化测试',
            'embedding' => [0.1, 0.2, 0.3],
        ]);

        $id = $this->store->addDocument($doc);

        $newStore = new FileSystemVectorStore(
            'test_collection',
            new CosineDistance(),
            3,
            $this->testPath
        );

        $this->assertEquals(1, $newStore->count());
        $found = $newStore->getById($id);
        $this->assertNotNull($found);
        $this->assertEquals('持久化测试', $found->getContent());
    }
}