<?php

declare(strict_types=1);

namespace PhpLLP\Tools;

use PhpLLP\Contracts\ToolInterface;

class ToolManager
{
    /** @var array<string, ToolInterface> */
    private $tools = [];

    /**
     * @param ToolInterface $tool
     * @return self
     */
    public function register(ToolInterface $tool): self
    {
        $this->tools[$tool->getName()] = $tool;
        return $this;
    }

    /**
     * @param string $name
     * @return self
     */
    public function unregister(string $name): self
    {
        unset($this->tools[$name]);
        return $this;
    }

    /**
     * @param string $name
     * @return ToolInterface|null
     */
    public function get(string $name): ?ToolInterface
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * @return array<string, ToolInterface>
     */
    public function all(): array
    {
        return $this->tools;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getToolsSchema(): array
    {
        $result = [];
        foreach ($this->tools as $tool) {
            $result[] = $tool->toArray();
        }
        return $result;
    }

    /**
     * @param string $name
     * @param array<string, mixed> $parameters
     * @return ToolResult
     */
    public function execute(string $name, array $parameters = []): ToolResult
    {
        if (!isset($this->tools[$name])) {
            return ToolResult::failure("工具 '{$name}' 未注册");
        }

        $startTime = microtime(true);

        try {
            $tool = $this->tools[$name];
            $result = $tool->execute($parameters);
            $duration = microtime(true) - $startTime;

            return ToolResult::success($result, $duration);
        } catch (\Throwable $e) {
            $duration = microtime(true) - $startTime;
            return ToolResult::failure($e->getMessage(), $duration);
        }
    }

    /**
     * @param array<string, array<string, mixed>> $calls
     * @return array<string, ToolResult>
     */
    public function executeBatch(array $calls): array
    {
        $results = [];
        foreach ($calls as $name => $parameters) {
            $results[$name] = $this->execute($name, $parameters);
        }
        return $results;
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    public function clear(): self
    {
        $this->tools = [];
        return $this;
    }
}