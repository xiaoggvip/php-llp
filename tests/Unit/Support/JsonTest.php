<?php

declare(strict_types=1);

namespace PhpLLP\Tests\Unit\Support;

use PhpLLP\Support\Json;
use PhpLLP\Exception\LLPException;
use PHPUnit\Framework\TestCase;

class JsonTest extends TestCase
{
    public function testEncode(): void
    {
        $data = ['name' => 'test', 'value' => 42];
        $json = Json::encode($data);
        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertEquals($data, $decoded);
    }

    public function testEncodeWithUnicode(): void
    {
        $data = ['name' => '测试', 'value' => 2];
        $json = Json::encode($data);
        $this->assertIsString($json);
        $this->assertStringContainsString('测试', $json);
        $decoded = json_decode($json, true);
        $this->assertEquals($data, $decoded);
    }

    public function testEncodeThrowsOnInvalid(): void
    {
        $this->expectException(LLPException::class);
        Json::encode(fopen('php://memory', 'r'));
    }

    public function testDecode(): void
    {
        $json = '{"name":"test","value":42}';
        $data = Json::decode($json);
        $this->assertIsArray($data);
        $this->assertEquals('test', $data['name']);
        $this->assertEquals(42, $data['value']);
    }

    public function testDecodeEmptyArray(): void
    {
        $data = Json::decode('[]');
        $this->assertIsArray($data);
        $this->assertEmpty($data);
    }

    public function testDecodeEmptyStringThrows(): void
    {
        $this->expectException(LLPException::class);
        Json::decode('');
    }

    public function testDecodeInvalidJsonThrows(): void
    {
        $this->expectException(LLPException::class);
        Json::decode('invalid json');
    }

    public function testDecodeAssoc(): void
    {
        $json = '{"name":"test"}';
        $data = Json::decode($json, true);
        $this->assertIsArray($data);

        $objData = Json::decode($json, false);
        $this->assertIsObject($objData);
    }
}