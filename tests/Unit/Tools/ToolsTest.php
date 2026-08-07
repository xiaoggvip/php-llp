<?php

declare(strict_types=1);

namespace PhpLLP\Tests\Unit\Tools;

use PhpLLP\Tools\Builtin\Calculator;
use PhpLLP\Tools\ToolManager;
use PHPUnit\Framework\TestCase;

class ToolsTest extends TestCase
{
    public function testCalculatorAdd(): void
    {
        $calc = new Calculator();
        $result = $calc->execute(['expression' => '2 + 3']);
        $this->assertEquals('5', $result);
    }

    public function testCalculatorMultiply(): void
    {
        $calc = new Calculator();
        $result = $calc->execute(['expression' => '4 * 5']);
        $this->assertEquals('20', $result);
    }

    public function testCalculatorComplex(): void
    {
        $calc = new Calculator();
        $result = $calc->execute(['expression' => '(10 + 5) * 3']);
        $this->assertEquals('45', $result);
    }

    public function testCalculatorGetName(): void
    {
        $calc = new Calculator();
        $this->assertEquals('calculator', $calc->getName());
    }

    public function testCalculatorGetDescription(): void
    {
        $calc = new Calculator();
        $this->assertNotEmpty($calc->getDescription());
    }

    public function testCalculatorGetParameters(): void
    {
        $calc = new Calculator();
        $params = $calc->getParameters();
        $this->assertArrayHasKey('type', $params);
        $this->assertArrayHasKey('properties', $params);
        $this->assertArrayHasKey('expression', $params['properties']);
    }

    public function testCalculatorTomathError(): void
    {
        $calc = new Calculator();
        $result = $calc->execute(['expression' => '']);
        $this->assertStringContainsString('错误', $result);
    }

    public function testToolManagerRegister(): void
    {
        $manager = new ToolManager();
        $calc = new Calculator();
        $manager->register($calc);

        $tools = $manager->all();
        $this->assertCount(1, $tools);
        $this->assertArrayHasKey('calculator', $tools);
    }

    public function testToolManagerGetByName(): void
    {
        $manager = new ToolManager();
        $calc = new Calculator();
        $manager->register($calc);

        $found = $manager->get('calculator');
        $this->assertNotNull($found);
        $this->assertSame($calc, $found);
    }

    public function testToolManagerUnregister(): void
    {
        $manager = new ToolManager();
        $calc = new Calculator();
        $manager->register($calc);
        $manager->unregister('calculator');

        $this->assertCount(0, $manager->all());
    }

    public function testToolManagerMultipleTools(): void
    {
        $manager = new ToolManager();
        $manager->register(new Calculator());

        $this->assertCount(1, $manager->all());
        $this->assertTrue($manager->has('calculator'));
    }

    public function testToolToArray(): void
    {
        $calc = new Calculator();
        $array = $calc->toArray();
        $this->assertArrayHasKey('type', $array);
        $this->assertArrayHasKey('function', $array);
        $this->assertEquals('calculator', $array['function']['name']);
    }

    public function testToolManagerExecute(): void
    {
        $manager = new ToolManager();
        $manager->register(new Calculator());

        $result = $manager->execute('calculator', ['expression' => '10 / 2']);
        $this->assertTrue($result->isSuccess());
    }

    public function testToolManagerGetToolsSchema(): void
    {
        $manager = new ToolManager();
        $manager->register(new Calculator());

        $schema = $manager->getToolsSchema();
        $this->assertCount(1, $schema);
        $this->assertArrayHasKey('function', $schema[0]);
    }
}