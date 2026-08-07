<?php

declare(strict_types=1);

namespace PhpLLP\Tests\Unit;

use PhpLLP\LLP;
use PHPUnit\Framework\TestCase;

class LLPTest extends TestCase
{
    public function testConstructor(): void
    {
        $llp = new LLP([
            'api_key' => 'test-key',
            'provider' => 'openai',
            'model' => 'gpt-3.5-turbo',
        ]);
        $this->assertInstanceOf(LLP::class, $llp);
    }

    public function testDefaultConfig(): void
    {
        $llp = new LLP([
            'api_key' => 'test-key',
        ]);
        $this->assertInstanceOf(LLP::class, $llp);
    }

    public function testCreateWithOllamaConfig(): void
    {
        $llp = new LLP([
            'provider' => 'ollama',
            'base_url' => 'http://localhost:11434',
            'model' => 'llama3',
        ]);
        $this->assertInstanceOf(LLP::class, $llp);
    }

    public function testCreateVectorStore(): void
    {
        $llp = new LLP([
            'provider' => 'ollama',
            'vector_store' => 'filesystem',
            'path' => sys_get_temp_dir() . '/php-llp-test',
        ]);
        $this->assertInstanceOf(LLP::class, $llp);
    }

    public function testCreateWithCustomHttpClient(): void
    {
        $llp = new LLP([
            'api_key' => 'test-key',
            'provider' => 'openai',
            'timeout' => 30,
        ]);
        $this->assertInstanceOf(LLP::class, $llp);
    }

    public function testCreateWithAllConfigOptions(): void
    {
        $llp = new LLP([
            'api_key' => 'test-key',
            'provider' => 'mistral',
            'model' => 'mistral-tiny',
            'temperature' => 0.7,
            'max_tokens' => 1024,
            'timeout' => 60,
            'base_url' => 'https://api.mistral.ai/v1',
        ]);
        $this->assertInstanceOf(LLP::class, $llp);
    }

    public function testMultipleInstances(): void
    {
        $llp1 = new LLP(['api_key' => 'key1', 'provider' => 'openai']);
        $llp2 = new LLP(['api_key' => 'key2', 'provider' => 'ollama']);
        $this->assertInstanceOf(LLP::class, $llp1);
        $this->assertInstanceOf(LLP::class, $llp2);
    }
}