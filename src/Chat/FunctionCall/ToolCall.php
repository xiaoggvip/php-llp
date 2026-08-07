<?php

declare(strict_types=1);

namespace PhpLLP\Chat\FunctionCall;

class ToolCall
{
    /** @var string */
    private $id;

    /** @var string */
    private $functionName;

    /** @var array<string, mixed> */
    private $arguments;

    /** @var mixed */
    private $result;

    /** @var bool */
    private $executed = false;

    /**
     * @param string $id
     * @param string $functionName
     * @param array<string, mixed> $arguments
     */
    public function __construct(string $id, string $functionName, array $arguments = [])
    {
        $this->id = $id;
        $this->functionName = $functionName;
        $this->arguments = $arguments;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getFunctionName(): string
    {
        return $this->functionName;
    }

    /**
     * @return array<string, mixed>
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * @return mixed
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * @param mixed $result
     * @return self
     */
    public function setResult($result): self
    {
        $this->result = $result;
        $this->executed = true;
        return $this;
    }

    public function isExecuted(): bool
    {
        return $this->executed;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'function' => [
                'name' => $this->functionName,
                'arguments' => json_encode($this->arguments),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $function = $data['function'] ?? [];
        $arguments = [];
        if (isset($function['arguments'])) {
            $decoded = json_decode($function['arguments'], true);
            $arguments = is_array($decoded) ? $decoded : [];
        }

        return new self(
            $data['id'] ?? uniqid('call_', true),
            $function['name'] ?? '',
            $arguments
        );
    }
}