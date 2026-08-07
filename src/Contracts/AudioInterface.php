<?php

declare(strict_types=1);

namespace PhpLLP\Contracts;

interface AudioInterface
{
    /**
     * Transcribe audio file to text
     *
     * @param string $filePath
     * @param array<string, mixed> $options
     * @return array{text: string, language: string|null, duration: float|null}
     */
    public function transcribe(string $filePath, array $options = []): array;
}