<?php

declare(strict_types=1);

namespace PhpLLP\Tests\Unit\Support;

use PhpLLP\Support\PhpVersion;
use PHPUnit\Framework\TestCase;

class PhpVersionTest extends TestCase
{
    public function testGetMajor(): void
    {
        $major = PhpVersion::getMajor();
        $this->assertIsInt($major);
        $this->assertGreaterThanOrEqual(7, $major);
    }

    public function testGetMinor(): void
    {
        $minor = PhpVersion::getMinor();
        $this->assertIsInt($minor);
    }

    public function testIs74(): void
    {
        $is74 = PHP_VERSION_ID >= 70400 && PHP_VERSION_ID < 80000;
        $this->assertEquals($is74, PhpVersion::is74());
    }

    public function testIs80Plus(): void
    {
        $is80 = PHP_VERSION_ID >= 80000;
        $this->assertEquals($is80, PhpVersion::is80Plus());
    }

    public function testStrContains(): void
    {
        $this->assertTrue(PhpVersion::strContains('hello world', 'world'));
        $this->assertFalse(PhpVersion::strContains('hello world', 'xyz'));
        $this->assertTrue(PhpVersion::strContains('hello', ''));
    }

    public function testStrStartsWith(): void
    {
        $this->assertTrue(PhpVersion::strStartsWith('hello world', 'hello'));
        $this->assertFalse(PhpVersion::strStartsWith('hello world', 'world'));
    }

    public function testStrEndsWith(): void
    {
        $this->assertTrue(PhpVersion::strEndsWith('hello world', 'world'));
        $this->assertFalse(PhpVersion::strEndsWith('hello world', 'hello'));
    }

    public function testCompare(): void
    {
        $this->assertTrue(PhpVersion::compare('7.4', '>='));
        $this->assertTrue(PhpVersion::compare('7.0', '>='));
    }

    public function testSupports(): void
    {
        $this->assertIsBool(PhpVersion::supports('str_contains'));
        $this->assertIsBool(PhpVersion::supports('enum'));
        $this->assertFalse(PhpVersion::supports('nonexistent_feature'));
    }
}