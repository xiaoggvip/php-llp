<?php

declare(strict_types=1);

namespace PhpLLP\Query\Transformer;

use PhpLLP\Contracts\ChatInterface;

class QueryTransformer
{
    /** @var ChatInterface|null */
    private $chatProvider;

    /** @var string */
    private $template;

    /** @var int */
    private $numVariants;

    /**
     * @param ChatInterface|null $chatProvider
     * @param string $template
     * @param int $numVariants
     */
    public function __construct(
        ?ChatInterface $chatProvider = null,
        string $template = '请为以下问题生成 {num} 个不同的查询变体，以提高搜索召回率：\n\n原始问题：{query}\n\n变体：',
        int $numVariants = 3
    ) {
        $this->chatProvider = $chatProvider;
        $this->template = $template;
        $this->numVariants = $numVariants;
    }

    /**
     * Transform a query into multiple variants
     *
     * @param string $query
     * @return string[]
     */
    public function transform(string $query): array
    {
        if ($this->chatProvider === null) {
            return [$query];
        }

        $prompt = str_replace(
            ['{num}', '{query}'],
            [(string)$this->numVariants, $query],
            $this->template
        );

        $response = $this->chatProvider->generateText($prompt);
        $variants = $this->parseVariants($response);

        if (empty($variants)) {
            return [$query];
        }

        if (!in_array($query, $variants, true)) {
            array_unshift($variants, $query);
        }

        return array_slice(array_unique($variants), 0, $this->numVariants);
    }

    /**
     * @param string $response
     * @return string[]
     */
    private function parseVariants(string $response): array
    {
        $variants = [];
        $lines = preg_split('/[\n\r]+/', $response);

        foreach ($lines as $line) {
            $line = trim($line);
            $line = preg_replace('/^[\d]+[\.\)、]\s*/', '', $line);
            $line = preg_replace('/^[-*]\s*/', '', $line);

            if (!empty($line) && mb_strlen($line) > 3) {
                $variants[] = $line;
            }
        }

        return $variants;
    }

    /**
     * @param int $numVariants
     * @return self
     */
    public function setNumVariants(int $numVariants): self
    {
        $this->numVariants = $numVariants;
        return $this;
    }

    /**
     * @param string $template
     * @return self
     */
    public function setTemplate(string $template): self
    {
        $this->template = $template;
        return $this;
    }
}