<?php

declare(strict_types=1);

namespace PhpLLP\Query;

use PhpLLP\Contracts\ChatInterface;
use PhpLLP\Contracts\EmbeddingInterface;
use PhpLLP\Embeddings\Document;
use PhpLLP\Query\Transformer\DocumentsTransformer;
use PhpLLP\Query\Transformer\QueryTransformer;
use PhpLLP\VectorStore\VectorStoreBase;

class QuestionAnswering
{
    /** @var ChatInterface */
    private $chatProvider;

    /** @var EmbeddingInterface */
    private $embeddingProvider;

    /** @var VectorStoreBase */
    private $vectorStore;

    /** @var int */
    private $topK;

    /** @var float */
    private $threshold;

    /** @var string */
    private $systemPrompt;

    /** @var QueryTransformer|null */
    private $queryTransformer;

    /** @var DocumentsTransformer|null */
    private $documentsTransformer;

    /**
     * @param ChatInterface $chatProvider
     * @param EmbeddingInterface $embeddingProvider
     * @param VectorStoreBase $vectorStore
     * @param int $topK
     * @param float $threshold
     */
    public function __construct(
        ChatInterface $chatProvider,
        EmbeddingInterface $embeddingProvider,
        VectorStoreBase $vectorStore,
        int $topK = 5,
        float $threshold = 0.0
    ) {
        $this->chatProvider = $chatProvider;
        $this->embeddingProvider = $embeddingProvider;
        $this->vectorStore = $vectorStore;
        $this->topK = $topK;
        $this->threshold = $threshold;
        $this->systemPrompt = '你是一个有用的问答助手。请基于以下上下文来回答问题。如果上下文中没有相关信息，请说"我无法回答这个问题"。';
    }

    /**
     * Answer a question based on the vector store
     *
     * @param string $question
     * @param array<string, mixed> $options
     * @return array{answer: string, sources: array<int, array{document: Document, score: float}>}
     */
    public function answer(string $question, array $options = []): array
    {
        $searchResults = $this->retrieveDocuments($question);

        if (empty($searchResults)) {
            return [
                'answer' => '我无法回答这个问题，因为没有找到相关的上下文信息。',
                'sources' => [],
            ];
        }

        $context = $this->buildContext($searchResults);
        $prompt = $this->buildPrompt($question, $context);

        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt],
            ['role' => 'user', 'content' => $prompt],
        ];

        $answer = $this->chatProvider->generateChat($messages, $options);

        return [
            'answer' => $answer,
            'sources' => $searchResults,
        ];
    }

    /**
     * @param string $question
     * @return array<int, array{document: Document, score: float}>
     */
    private function retrieveDocuments(string $question): array
    {
        $queryVariants = [$question];

        if ($this->queryTransformer !== null) {
            $queryVariants = $this->queryTransformer->transform($question);
        }

        $allResults = [];
        $seenIds = [];

        foreach ($queryVariants as $query) {
            $queryEmbedding = $this->embeddingProvider->embed($query);
            $results = $this->vectorStore->similaritySearch(
                $queryEmbedding,
                $this->topK,
                $this->threshold
            );

            foreach ($results as $result) {
                $id = $result['document']->getId();
                if (!isset($seenIds[$id])) {
                    $allResults[] = $result;
                    $seenIds[$id] = true;
                }
            }
        }

        if ($this->documentsTransformer !== null) {
            $allResults = $this->documentsTransformer->transform($allResults);
        }

        usort($allResults, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        return array_slice($allResults, 0, $this->topK);
    }

    /**
     * @param array<int, array{document: Document, score: float}> $results
     * @return string
     */
    private function buildContext(array $results): string
    {
        $context = '';
        foreach ($results as $index => $result) {
            $context .= "[{$index}]: {$result['document']->getContent()}\n";
        }
        return trim($context);
    }

    /**
     * @param string $question
     * @param string $context
     * @return string
     */
    private function buildPrompt(string $question, string $context): string
    {
        return "上下文：\n{$context}\n\n问题：{$question}\n\n请基于上下文回答问题。";
    }

    /**
     * @param string $systemPrompt
     * @return self
     */
    public function setSystemPrompt(string $systemPrompt): self
    {
        $this->systemPrompt = $systemPrompt;
        return $this;
    }

    /**
     * @param int $topK
     * @return self
     */
    public function setTopK(int $topK): self
    {
        $this->topK = $topK;
        return $this;
    }

    /**
     * @param float $threshold
     * @return self
     */
    public function setThreshold(float $threshold): self
    {
        $this->threshold = $threshold;
        return $this;
    }

    /**
     * @param QueryTransformer $transformer
     * @return self
     */
    public function setQueryTransformer(QueryTransformer $transformer): self
    {
        $this->queryTransformer = $transformer;
        return $this;
    }

    /**
     * @param DocumentsTransformer $transformer
     * @return self
     */
    public function setDocumentsTransformer(DocumentsTransformer $transformer): self
    {
        $this->documentsTransformer = $transformer;
        return $this;
    }
}