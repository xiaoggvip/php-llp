<?php

declare(strict_types=1);

namespace PhpLLP\Embeddings;

class DocumentUtils
{
    /**
     * @param Document $a
     * @param Document $b
     * @return bool
     */
    public static function equals(Document $a, Document $b): bool
    {
        return $a->getId() === $b->getId()
            && $a->getHash() === $b->getHash();
    }

    /**
     * @param Document $doc
     * @return bool
     */
    public static function hasEmbedding(Document $doc): bool
    {
        return !empty($doc->getEmbedding());
    }

    /**
     * @param Document[] $documents
     * @return array<int, float>
     */
    public static function averageEmbedding(array $documents): array
    {
        if (empty($documents)) {
            return [];
        }

        $embeddings = [];
        foreach ($documents as $doc) {
            if (self::hasEmbedding($doc)) {
                $embeddings[] = $doc->getEmbedding();
            }
        }

        if (empty($embeddings)) {
            return [];
        }

        $dimensions = count($embeddings[0]);
        $result = array_fill(0, $dimensions, 0.0);

        foreach ($embeddings as $embedding) {
            for ($i = 0; $i < $dimensions; $i++) {
                $result[$i] += $embedding[$i] ?? 0;
            }
        }

        $count = count($embeddings);
        for ($i = 0; $i < $dimensions; $i++) {
            $result[$i] /= $count;
        }

        return $result;
    }

    /**
     * @param array<int, float> $embedding
     * @return array<int, float>
     */
    public static function normalizeEmbedding(array $embedding): array
    {
        $norm = 0.0;
        foreach ($embedding as $val) {
            $norm += $val * $val;
        }
        $norm = sqrt($norm);

        if ($norm == 0) {
            return $embedding;
        }

        $result = [];
        foreach ($embedding as $val) {
            $result[] = $val / $norm;
        }

        return $result;
    }
}