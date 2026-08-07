<?php

declare(strict_types=1);

namespace PhpLLP\Embeddings\Distances;

interface Distance
{
    /**
     * @param array<int, float> $a
     * @param array<int, float> $b
     * @return float
     */
    public function calculate(array $a, array $b): float;

    /**
     * @return string
     */
    public function getName(): string;
}