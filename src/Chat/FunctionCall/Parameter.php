<?php

declare(strict_types=1);

namespace PhpLLP\Chat\FunctionCall;

class Parameter
{
    /** @var string */
    private $name;

    /** @var string */
    private $type;

    /** @var string */
    private $description;

    /** @var array<string, mixed> */
    private $properties = [];

    /** @var string[] */
    private $required = [];

    /**
     * @param string $name
     * @param string $type
     * @param string $description
     * @param array<string, mixed> $properties
     * @param string[] $required
     */
    public function __construct(
        string $name,
        string $type = 'string',
        string $description = '',
        array $properties = [],
        array $required = []
    ) {
        $this->name = $name;
        $this->type = $type;
        $this->description = $description;
        $this->properties = $properties;
        $this->required = $required;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @return array<string, mixed>
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    /**
     * @return string[]
     */
    public function getRequired(): array
    {
        return $this->required;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [
            'type' => $this->type,
            'description' => $this->description,
        ];

        if (!empty($this->properties)) {
            $result['properties'] = $this->properties;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['name'] ?? '',
            $data['type'] ?? 'string',
            $data['description'] ?? '',
            $data['properties'] ?? [],
            $data['required'] ?? []
        );
    }
}