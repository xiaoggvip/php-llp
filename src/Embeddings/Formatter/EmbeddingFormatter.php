<?php

declare(strict_types=1);

namespace PhpLLP\Embeddings\Formatter;

use PhpLLP\Embeddings\Document;

class EmbeddingFormatter
{
    /** @var string */
    private $template;

    /**
     * @param string $template
     */
    public function __construct(string $template = '{content}')
    {
        $this->template = $template;
    }

    /**
     * Format a document's content using the template
     *
     * @param Document $document
     * @return string
     */
    public function format(Document $document): string
    {
        return str_replace('{content}', $document->getContent(), $this->template);
    }

    /**
     * Format a plain text
     *
     * @param string $text
     * @return string
     */
    public function formatText(string $text): string
    {
        return str_replace('{content}', $text, $this->template);
    }

    /**
     * Format multiple documents
     *
     * @param Document[] $documents
     * @return string[]
     */
    public function formatBatch(array $documents): array
    {
        $result = [];
        foreach ($documents as $document) {
            $result[] = $this->format($document);
        }
        return $result;
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

    public function getTemplate(): string
    {
        return $this->template;
    }
}