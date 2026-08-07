<?php

declare(strict_types=1);

namespace PhpLLP\Contracts;

interface ImageInterface
{
    /**
     * Generate image from prompt
     *
     * @param string $prompt
     * @param array<string, mixed> $options
     * @return array{url?: string, base64?: string}
     */
    public function generate(string $prompt, array $options = []): array;
}