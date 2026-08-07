<?php

declare(strict_types=1);

namespace PhpLLP\Tests\Unit\Embeddings;

use PhpLLP\Embeddings\Document;
use PhpLLP\Embeddings\Splitter\DocumentSplitter;
use PHPUnit\Framework\TestCase;

class DocumentSplitterTest extends TestCase
{
    public function testSplitShortText(): void
    {
        $splitter = new DocumentSplitter(500, 50);
        $text = "这是一段短文本。";
        $chunks = $splitter->splitText($text);
        $this->assertCount(1, $chunks);
        $this->assertInstanceOf(Document::class, $chunks[0]);
        $this->assertEquals($text, $chunks[0]->getContent());
    }

    public function testSplitLongText(): void
    {
        $splitter = new DocumentSplitter(50, 10);
        $text = str_repeat("这是一段很长的文本内容。", 20);
        $chunks = $splitter->splitText($text);
        $this->assertGreaterThan(1, count($chunks));

        foreach ($chunks as $chunk) {
            $this->assertInstanceOf(Document::class, $chunk);
            $this->assertNotEmpty($chunk->getContent());
        }
    }

    public function testSplitEmptyText(): void
    {
        $splitter = new DocumentSplitter(100, 20);
        $chunks = $splitter->splitText('');
        $this->assertCount(0, $chunks);
    }

    public function testSplitByParagraphs(): void
    {
        $splitter = new DocumentSplitter(1000, 100);
        $text = "第一段内容。\n\n第二段内容。\n\n第三段内容。";
        $chunks = $splitter->splitText($text);
        $this->assertGreaterThanOrEqual(1, count($chunks));
    }

    public function testSplitPreservesContent(): void
    {
        $splitter = new DocumentSplitter(100, 5);
        $text = "AABBCCDD EEFFGGHH IIJJKKLL MMNN";
        $chunks = $splitter->splitText($text);
        $this->assertGreaterThanOrEqual(1, count($chunks));
        $firstContent = $chunks[0]->getContent();
        $this->assertStringContainsString('AABBCCDD', $firstContent);
    }

    public function testSplitDocumentObject(): void
    {
        $splitter = new DocumentSplitter(50, 10);
        $doc = new Document([
            'content' => str_repeat("内容块。", 20),
            'metadata' => ['source' => 'test'],
        ]);
        $chunks = $splitter->split($doc);
        $this->assertGreaterThan(1, count($chunks));

        foreach ($chunks as $chunk) {
            $this->assertInstanceOf(Document::class, $chunk);
            $meta = $chunk->getMetadata();
            $this->assertEquals('test', $meta['source'] ?? null);
        }
    }
}