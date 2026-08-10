<?php

declare(strict_types=1);

namespace PhpLLP\Contracts;

interface ChatInterface
{
    /**
     * Generate text from a single prompt
     *
     * @param string $prompt
     * @param array<string, mixed> $options
     * @return string
     */
    public function generateText(string $prompt, array $options = []): string;

    /**
     * Generate chat completion from messages
     *
     * @param array<int, array{role: string, content: string}> $messages
     * @param array<string, mixed> $options
     * @return string
     */
    public function generateChat(array $messages, array $options = []): string;

    /**
     * Generate text with tool/function calling
     *
     * @param string $prompt
     * @param array<int, array<string, mixed>> $tools
     * @param array<string, mixed> $options
     * @return mixed
     */
    public function generateTextWithTools(string $prompt, array $tools, array $options = []);

    /**
     * Generate chat with tools from messages
     *
     * @param array<int, array<string, mixed>> $messages
     * @param array<int, array<string, mixed>> $tools
     * @param array<string, mixed> $options
     * @return mixed
     */
    public function generateChatWithTools(array $messages, array $tools, array $options = []);

    /**
     * Stream chat completion
     *
     * @param string $prompt
     * @param array<string, mixed> $options
     * @return \Generator
     */
    public function generateStream(string $prompt, array $options = []): \Generator;
}