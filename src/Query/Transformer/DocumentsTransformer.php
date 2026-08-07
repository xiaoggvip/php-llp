<?php

declare(strict_types=1);

namespace PhpLLP\Query\Transformer;

use PhpLLP\Contracts\ChatInterface;
use PhpLLP\Embeddings\Document;

class DocumentsTransformer
{
    /** @var ChatInterface|null */
    private $chatProvider;

    /** @var bool */
    private $enableReranking;

    /** @var int */
    private $maxDocuments;

    /** @var float */
    private $scoreThreshold;

    /**
     * @param ChatInterface|null $chatProvider
     * @param bool $enableReranking
     * @param int $maxDocuments
     * @param float $scoreThreshold
     */
    public function __construct(
        ?ChatInterface $chatProvider = null,
        bool $enableReranking = true,
        int $maxDocuments = 10,
        float $scoreThreshold = 0.0
    ) {
        $this->chatProvider = $chatProvider;
        $this->enableReranking = $enableReranking;
        $this->maxDocuments = $maxDocuments;
        $this->scoreThreshold = $scoreThreshold;
    }

    /**
     * Transform/re-rank search results
     *
     * @param array<int, array{document: Document, score: float}> $results
     * @return array<int, array{document: Document, score: float}>
     */
    public function transform(array $results): array
    {
        $results = $this->filterByScore($results);
        $results = $this->deduplicate($results);

        if ($this->enableReranking && $this->chatProvider !== null && count($results) > 1) {
            $results = $this->rerank($results);
        }

        return array_slice($results, 0, $this->maxDocuments);
    }

    /**
     * Filter results by minimum score
     *
     * @param array<int, array{document: Document, score: float}> $results
     * @return array<int, array{document: Document, score: float}>
     */
    private function filterByScore(array $results): array
    {
        if ($this->scoreThreshold <= 0) {
            return $results;
        }

        return array_values(array_filter($results, function ($result) {
            return $result['score'] >= $this->scoreThreshold;
        }));
    }

    /**
     * Remove duplicate documents
     *
     * @param array<int, array{document: Document, score: float}> $results
     * @return array<int, array{document: Document, score: float}>
     */
    private function deduplicate(array $results): array
    {
        $seen = [];
        $unique = [];

        foreach ($results as $result) {
            $id = $result['document']->getId();
            if (!isset($seen[$id])) {
                $seen[$id] = true;
                $unique[] = $result;
            }
        }

        return $unique;
    }

    /**
     * Re-rank documents using LLM
     *
     * @param array<int, array{document: Document, score: float}> $results
     * @return array<int, array{document: Document, score: float}>
     */
    private function rerank(array $results): array
    {
        $documents = [];
        foreach ($results as $index => $result) {
            $documents[] = "{$index}: {$result['document']->getContent()}";
        }

        $prompt = "请对以下文档进行相关性排序，返回按相关性从高到低的索引列表（用逗号分隔）：\n\n" . implode("\n", $documents) . "\n\n排序结果：";

        $response = $this->chatProvider->generateText($prompt);
        $indices = $this->parseRerankResponse($response);

        if (empty($indices)) {
            return $results;
        }

        $reranked = [];
        foreach ($indices as $index) {
            if (isset($results[$index])) {
                $reranked[] = $results[$index];
            }
        }

        return !empty($reranked) ? $reranked : $results;
    }

    /**
     * @param string $response
     * @return int[]
     */
    private function parseRerankResponse(string $response): array
    {
        preg_match_all('/\d+/', $response, $matches);
        return array_map('intval', $matches[0] ?? []);
    }

    /**
     * @param int $maxDocuments
     * @return self
     */
    public function setMaxDocuments(int $maxDocuments): self
    {
        $this->maxDocuments = $maxDocuments;
        return $this;
    }

    /**
     * @param float $scoreThreshold
     * @return self
     */
    public function setScoreThreshold(float $scoreThreshold): self
    {
        $this->scoreThreshold = $scoreThreshold;
        return $this;
    }

    /**
     * @param bool $enableReranking
     * @return self
     */
    public function setEnableReranking(bool $enableReranking): self
    {
        $this->enableReranking = $enableReranking;
        return $this;
    }
}