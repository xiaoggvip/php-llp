<?php

declare(strict_types=1);

namespace PhpLLP\Tests\Unit\Embeddings;

use PhpLLP\Embeddings\Document;
use PHPUnit\Framework\TestCase;

class DocumentTest extends TestCase
{
    public function testConstructorWithDefaults(): void
    {
        $doc = new Document();
        $this->assertNotEmpty($doc->getId());
        $this->assertEquals('', $doc->getContent());
        $this->assertEquals([], $doc->getEmbedding());
        $this->assertEquals([], $doc->getMetadata());
    }

    public function testConstructorWithData(): void
    {
        $doc = new Document([
            'id' => 'test-id',
            'content' => '测试内容',
            'embedding' => [0.1, 0.2, 0.3],
            'metadata' => ['category' => 'test'],
        ]);

        $this->assertEquals('test-id', $doc->getId());
        $this->assertEquals('测试内容', $doc->getContent());
        $this->assertEquals([0.1, 0.2, 0.3], $doc->getEmbedding());
        $this->assertEquals(['category' => 'test'], $doc->getMetadata());
    }

    public function testSetId(): void
    {
        $doc = new Document();
        $doc->setId('custom-id');
        $this->assertEquals('custom-id', $doc->getId());
    }

    public function testSetContent(): void
    {
        $doc = new Document();
        $doc->setContent('新内容');
        $this->assertEquals('新内容', $doc->getContent());
        $this->assertNotEmpty($doc->getHash());
    }

    public function testSetEmbedding(): void
    {
        $doc = new Document();
        $embedding = [0.5, 0.6, 0.7];
        $doc->setEmbedding($embedding);
        $this->assertEquals($embedding, $doc->getEmbedding());
    }

    public function testSetMetadata(): void
    {
        $doc = new Document();
        $meta = ['key' => 'value'];
        $doc->setMetadata($meta);
        $this->assertEquals($meta, $doc->getMetadata());
    }

    public function testAddMetadata(): void
    {
        $doc = new Document();
        $doc->addMetadata('color', 'blue');
        $this->assertEquals(['color' => 'blue'], $doc->getMetadata());
    }

    public function testToArray(): void
    {
        $doc = new Document([
            'id' => 'test',
            'content' => 'content',
            'embedding' => [1.0],
            'metadata' => ['key' => 'val'],
        ]);

        $array = $doc->toArray();
        $this->assertEquals('test', $array['id']);
        $this->assertEquals('content', $array['content']);
        $this->assertEquals([1.0], $array['embedding']);
        $this->assertArrayHasKey('hash', $array);
        $this->assertEquals(['key' => 'val'], $array['metadata']);
    }

    public function testFromArray(): void
    {
        $data = [
            'id' => 'from-array',
            'content' => 'from array',
            'embedding' => [0.1, 0.2],
            'metadata' => ['source' => 'test'],
        ];

        $doc = Document::fromArray($data);
        $this->assertEquals('from-array', $doc->getId());
        $this->assertEquals('from array', $doc->getContent());
    }

    public function testHashConsistency(): void
    {
        $doc = new Document(['content' => 'test content']);
        $hash1 = $doc->getHash();
        $doc->setContent('test content');
        $hash2 = $doc->getHash();
        $this->assertEquals($hash1, $hash2);
    }
}