<?php

declare(strict_types=1);

namespace PhpLLP\Embeddings\Distances;

class CosineDistance implements Distance
{
    public function calculate(array $a, array $b): float
    {
        $dimensions = count($a);
        if ($dimensions !== count($b)) {
            throw new \InvalidArgumentException('向量维度不匹配');
        }

        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < $dimensions; $i++) {
            $dotProduct += ($a[$i] ?? 0) * ($b[$i] ?? 0);
            $normA += ($a[$i] ?? 0) * ($a[$i] ?? 0);
            $normB += ($b[$i] ?? 0) * ($b[$i] ?? 0);
        }

        $normA = sqrt($normA);
        $normB = sqrt($normB);

        if ($normA == 0 || $normB == 0) {
            return 0.0;
        }

        return $dotProduct / ($normA * $normB);
    }

    public function getName(): string
    {
        return 'cosine';
    }

    /**
     * Calculate cosine similarity (1 - distance)
     *
     * @param array<int, float> $a
     * @param array<int, float> $b
     * @return float
     */
    public function similarity(array $a, array $b): float
    {
        return $this->calculate($a, $b);
    }
}