<?php

declare(strict_types=1);

namespace PhpLLP\Tools\Builtin;

use PhpLLP\Contracts\ToolInterface;
use PhpLLP\Contracts\ChatInterface;

class TextSummarizer implements ToolInterface
{
    /** @var ChatInterface */
    private $chatProvider;

    /**
     * @param ChatInterface $chatProvider
     */
    public function __construct(ChatInterface $chatProvider)
    {
        $this->chatProvider = $chatProvider;
    }

    public function getName(): string
    {
        return 'text_summarizer';
    }

    public function getDescription(): string
    {
        return '对给定文本进行摘要总结。使用LLM提取关键信息，生成简洁的摘要。';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'text' => [
                    'type' => 'string',
                    'description' => '要摘要的文本内容',
                ],
                'max_length' => [
                    'type' => 'integer',
                    'description' => '摘要最大长度（默认200字）',
                ],
            ],
            'required' => ['text'],
        ];
    }

    public function execute(array $parameters)
    {
        $text = $parameters['text'] ?? '';
        if (empty($text)) {
            return '错误：必须提供文本内容';
        }

        $maxLength = $parameters['max_length'] ?? 200;

        $prompt = "请对以下文本进行摘要，摘要不超过{$maxLength}字：\n\n" . $text;

        return $this->chatProvider->generateText($prompt);
    }

    public function toArray(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->getName(),
                'description' => $this->getDescription(),
                'parameters' => $this->getParameters(),
            ],
        ];
    }
}