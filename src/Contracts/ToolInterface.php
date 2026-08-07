<?php

declare(strict_types=1);

namespace PhpLLP\Contracts;

interface ToolInterface
{
    /**
     * Get the tool name
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get the tool description
     *
     * @return string
     */
    public function getDescription(): string;

    /**
     * Get the tool parameters schema
     *
     * @return array<string, mixed>
     */
    public function getParameters(): array;

    /**
     * Execute the tool
     *
     * @param array<string, mixed> $parameters
     * @return mixed
     */
    public function execute(array $parameters);

    /**
     * Convert tool to array format for API
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}