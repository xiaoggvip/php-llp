<?php

declare(strict_types=1);

namespace PhpLLP\Embeddings\Distances;

class EuclideanDistance implements Distance
{
    public function calculate(array $a, array $b): float
    {
        $dimensions = count($a);
        if ($dimensions !== count($b)) {
            throw new \InvalidArgumentException('向量维度不匹配');
        }

        $sum = 0.0;
        for ($i = 0; $i < $dimensions; $i++) {
            $diff = ($a[$i] ?? 0) - ($b[$i] ?? 0);
            $sum += $diff * $diff;
        }

        return sqrt($sum);
    }

    public function getName(): string
    {
        return 'euclidean';
    }

    /**
     * Calculate squared euclidean distance (avoids sqrt for efficiency)
     *
     * @param array<int, float> $a
     * @param array<int, float> $b
     * @return float
     */
    public function squaredDistance(array $a, array $b): float
    {
        $dimensions = count($a);
        if ($dimensions !== count($b)) {
            throw new \InvalidArgumentException('向量维度不匹配');
        }

        $sum = 0.0;
        for ($i = 0; $i < $dimensions; $i++) {
            $diff = ($a[$i] ?? 0) - ($b[$i] ?? 0);
            $sum += $diff * $diff;
        }

        return $sum;
    }
}