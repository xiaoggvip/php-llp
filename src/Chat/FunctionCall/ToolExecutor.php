<?php

declare(strict_types=1);

namespace PhpLLP\Chat\FunctionCall;

use PhpLLP\Contracts\ChatInterface;
use PhpLLP\Support\Json;

class ToolExecutor
{
    /** @var ChatInterface */
    private $chatProvider;

    /** @var FunctionInfo[] */
    private $functions = [];

    /** @var int */
    private $maxIterations = 10;

    /**
     * @param ChatInterface $chatProvider
     * @param FunctionInfo[] $functions
     */
    public function __construct(ChatInterface $chatProvider, array $functions = [])
    {
        $this->chatProvider = $chatProvider;
        foreach ($functions as $function) {
            $this->addFunction($function);
        }
    }

    public function addFunction(FunctionInfo $function): self
    {
        $this->functions[$function->getName()] = $function;
        return $this;
    }

    /**
     * @return FunctionInfo[]
     */
    public function getFunctions(): array
    {
        return $this->functions;
    }

    public function setMaxIterations(int $maxIterations): self
    {
        $this->maxIterations = $maxIterations;
        return $this;
    }

    /**
     * @param string $prompt
     * @param array<string, mixed> $options
     * @return string
     */
    public function execute(string $prompt, array $options = []): string
    {
        $messages = [
            ['role' => 'user', 'content' => $prompt],
        ];

        $toolSchemas = [];
        foreach ($this->functions as $function) {
            $toolSchemas[] = $function->toToolFormat();
        }

        $iteration = 0;
        while ($iteration < $this->maxIterations) {
            $iteration++;

            $result = $this->chatProvider->generateTextWithTools(
                $prompt,
                $toolSchemas,
                $options
            );

            if (is_string($result)) {
                return $result;
            }

            if (is_array($result) && isset($result['tool_calls'])) {
                $toolCalls = $result['tool_calls'];

                foreach ($toolCalls as $toolCallData) {
                    $toolCall = ToolCall::fromArray($toolCallData);
                    $functionName = $toolCall->getFunctionName();

                    if (!isset($this->functions[$functionName])) {
                        $messages[] = [
                            'role' => 'tool',
                            'tool_call_id' => $toolCall->getId(),
                            'content' => "Error: Function '{$functionName}' not found",
                        ];
                        continue;
                    }

                    $function = $this->functions[$functionName];
                    $output = $function->call($toolCall->getArguments());

                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $toolCall->getId(),
                        'content' => is_array($output) ? Json::encode($output) : (string)$output,
                    ];
                }

                $messages[] = [
                    'role' => 'assistant',
                    'content' => $result['content'] ?? '',
                ];

                $allMessages = $messages;
                $response = $this->chatProvider->generateChat($allMessages, $options);

                if (!empty($response)) {
                    return $response;
                }
            }

            if (!is_array($result) || !isset($result['tool_calls'])) {
                break;
            }
        }

        return '';
    }
}