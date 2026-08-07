<?php

declare(strict_types=1);

namespace PhpLLP\Tests\Unit\Support;

use PhpLLP\Support\Str;
use PHPUnit\Framework\TestCase;

class StrTest extends TestCase
{
    public function testContains(): void
    {
        $this->assertTrue(Str::contains('hello world', 'hello'));
        $this->assertTrue(Str::contains('hello world', 'world'));
        $this->assertFalse(Str::contains('hello world', 'foo'));
    }

    public function testStartsWith(): void
    {
        $this->assertTrue(Str::startsWith('hello world', 'hello'));
        $this->assertFalse(Str::startsWith('hello world', 'world'));
    }

    public function testEndsWith(): void
    {
        $this->assertTrue(Str::endsWith('hello world', 'world'));
        $this->assertFalse(Str::endsWith('hello world', 'hello'));
    }

    public function testLength(): void
    {
        $this->assertEquals(11, Str::length('hello world'));
        $this->assertEquals(0, Str::length(''));
    }

    public function testSubstr(): void
    {
        $this->assertEquals('world', Str::subStr('hello world', 6));
        $this->assertEquals('hello', Str::subStr('hello world', 0, 5));
    }

    public function testToLower(): void
    {
        $this->assertEquals('hello', Str::toLower('HELLO'));
        $this->assertEquals('hello', Str::toLower('Hello'));
    }

    public function testToUpper(): void
    {
        $this->assertEquals('HELLO', Str::toUpper('hello'));
        $this->assertEquals('HELLO', Str::toUpper('Hello'));
    }

    public function testTrim(): void
    {
        $this->assertEquals('hello', Str::trim('  hello  '));
        $this->assertEquals('hello', Str::trim('hello'));
    }

    public function testReplace(): void
    {
        $this->assertEquals('hello php', Str::replace('hello world', 'world', 'php'));
    }

    public function testSplit(): void
    {
        $this->assertEquals(['a', 'b', 'c'], Str::split('a,b,c', ','));
    }
}