<?php

declare(strict_types=1);

namespace PhpLLP\Tests\Unit\Support;

use PhpLLP\Support\Arr;
use PHPUnit\Framework\TestCase;

class ArrTest extends TestCase
{
    public function testGet(): void
    {
        $array = ['a' => ['b' => ['c' => 'value']]];
        $this->assertEquals('value', Arr::get($array, 'a.b.c'));
        $this->assertEquals('default', Arr::get($array, 'a.b.d', 'default'));
        $this->assertEquals('default', Arr::get($array, 'x.y.z', 'default'));
    }

    public function testSet(): void
    {
        $array = [];
        $result = Arr::set($array, 'a.b.c', 'value');
        $this->assertEquals('value', $result['a']['b']['c']);
    }

    public function testHas(): void
    {
        $array = ['a' => ['b' => 'value']];
        $this->assertTrue(Arr::has($array, 'a.b'));
        $this->assertFalse(Arr::has($array, 'a.c'));
        $this->assertFalse(Arr::has($array, 'x.y'));
    }

    public function testForget(): void
    {
        $array = ['a' => ['b' => 1, 'c' => 2]];
        $result = Arr::forget($array, 'a.b');
        $this->assertFalse(isset($result['a']['b']));
        $this->assertEquals(2, $result['a']['c']);
    }

    public function testFlatten(): void
    {
        $result = Arr::flatten(['a' => ['b' => 1, 'c' => 2], 'd' => 3]);
        $this->assertEquals([1, 2, 3], $result);
    }

    public function testContains(): void
    {
        $this->assertTrue(Arr::contains([1, 2, 3], 2));
        $this->assertFalse(Arr::contains([1, 2, 3], 4));
        $this->assertTrue(Arr::contains(['a', 'b'], 'a'));
    }

    public function testPluck(): void
    {
        $array = [
            ['name' => 'Alice', 'age' => 30],
            ['name' => 'Bob', 'age' => 25],
        ];
        $result = Arr::pluck($array, 'name');
        $this->assertEquals(['Alice', 'Bob'], $result);
    }

    public function testIsEmpty(): void
    {
        $this->assertTrue(Arr::isEmpty([]));
        $this->assertFalse(Arr::isEmpty([1]));
    }

    public function testCount(): void
    {
        $this->assertEquals(0, Arr::count([]));
        $this->assertEquals(3, Arr::count([1, 2, 3]));
    }

    public function testMerge(): void
    {
        $result = Arr::merge(['a' => 1], ['b' => 2, 'a' => 3]);
        $this->assertEquals(['a' => 3, 'b' => 2], $result);
    }

    public function testMap(): void
    {
        $result = Arr::map([1, 2, 3], function ($v) { return $v * 2; });
        $this->assertEquals([2, 4, 6], $result);
    }

    public function testFilter(): void
    {
        $result = Arr::filter([1, 2, 3, 4], function ($v) { return $v > 2; });
        $this->assertEquals([3, 4], array_values($result));
    }

    public function testReduce(): void
    {
        $result = Arr::reduce([1, 2, 3], function ($carry, $item) { return $carry + $item; }, 0);
        $this->assertEquals(6, $result);
    }
}