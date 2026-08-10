<?php

declare(strict_types=1);

namespace PhpLLP\Chat\FunctionCall;

use PhpLLP\Contracts\ToolInterface;

class FunctionInfo
{
    /** @var string */
    private $name;

    /** @var string */
    private $description;

    /** @var Parameter[] */
    private $parameters = [];

    /** @var callable|null */
    private $handler;

    /**
     * @param string $name
     * @param string $description
     * @param Parameter[] $parameters
     * @param callable|null $handler
     */
    public function __construct(
        string $name,
        string $description = '',
        array $parameters = [],
        ?callable $handler = null
    ) {
        $this->name = $name;
        $this->description = $description;
        $this->parameters = $parameters;
        $this->handler = $handler;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @return Parameter[]
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function setHandler(callable $handler): self
    {
        $this->handler = $handler;
        return $this;
    }

    /**
     * @param array<string, mixed> $args
     * @return mixed
     */
    public function call(array $args = [])
    {
        if ($this->handler === null) {
            throw new \RuntimeException("Function '{$this->name}' has no handler defined");
        }

        return call_user_func($this->handler, $args);
    }

    /**
     * @return array<string, mixed>
     */
    public function toToolFormat(): array
    {
        $parameters = [
            'type' => 'object',
            'properties' => [],
        ];

        $required = [];
        foreach ($this->parameters as $param) {
            $parameters['properties'][$param->getName()] = $param->toArray();
            if (in_array($param->getName(), $param->getRequired(), true)) {
                $required[] = $param->getName();
            }
        }

        if (!empty($required)) {
            $parameters['required'] = $required;
        }

        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name,
                'description' => $this->description,
                'parameters' => $parameters,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->toToolFormat();
    }

    /**
     * Create from ToolInterface
     *
     * @param ToolInterface $tool
     * @return self
     */
    public static function fromTool(ToolInterface $tool): self
    {
        $parameters = $tool->getParameters();
        $toolParameters = [];
        $requiredFields = $parameters['required'] ?? [];

        if (isset($parameters['properties'])) {
            foreach ($parameters['properties'] as $name => $config) {
                $isRequired = in_array($name, $requiredFields, true);
                $toolParameters[] = new Parameter(
                    $name,
                    $config['type'] ?? 'string',
                    $config['description'] ?? '',
                    [],
                    $isRequired ? [$name] : []
                );
            }
        }

        return new self(
            $tool->getName(),
            $tool->getDescription(),
            $toolParameters,
            function (array $args) use ($tool) {
                return $tool->execute($args);
            }
        );
    }
}