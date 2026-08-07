<?php

declare(strict_types=1);

namespace PhpLLP\Chat\Provider;

use PhpLLP\Chat\ChatRole;
use PhpLLP\Chat\Message;
use PhpLLP\Contracts\ChatInterface;
use PhpLLP\Http\HttpClient;
use PhpLLP\Support\Json;

class OllamaChat implements ChatInterface
{
    /** @var array<string, mixed> */
    private $config;

    /** @var HttpClient */
    private $httpClient;

    /** @var string */
    private $baseUrl;

    /** @var string */
    private $model;

    /** @var string */
    private $systemMessage = '';

    /** @var int */
    private $totalTokens = 0;

    /**
     * @param array<string, mixed> $config
     * @param HttpClient $httpClient
     */
    public function __construct(array $config, HttpClient $httpClient)
    {
        $this->config = $config;
        $this->httpClient = $httpClient;
        $this->baseUrl = rtrim($config['base_url'] ?? 'http://localhost:11434', '/');
        $this->model = $config['model'] ?? 'llama3.1';
    }

    public function generateText(string $prompt, array $options = []): string
    {
        return $this->generateChat([Message::user($prompt)->toArray()], $options);
    }

    public function generateChat(array $messages, array $options = []): string
    {
        $allMessages = $this->buildMessages($messages);
        $payload = $this->buildPayload($allMessages, $options);

        $response = $this->httpClient->post(
            $this->baseUrl . '/api/chat',
            $this->getDefaultHeaders(),
            $payload
        );

        $data = Json::decode($response->getBody());
        $this->totalTokens = $data['eval_count'] ?? 0;

        return $data['message']['content'] ?? '';
    }

    public function generateTextWithTools(string $prompt, array $tools, array $options = [])
    {
        return $this->generateChatWithTools([Message::user($prompt)->toArray()], $tools, $options);
    }

    public function generateStream(string $prompt, array $options = []): \Generator
    {
        return $this->generateChatStream([Message::user($prompt)->toArray()], $options);
    }

    public function generateChatStream(array $messages, array $options = []): \Generator
    {
        $allMessages = $this->buildMessages($messages);
        $payload = $this->buildPayload($allMessages, $options);
        $payload['stream'] = true;

        $stream = $this->httpClient->stream(
            'POST',
            $this->baseUrl . '/api/chat',
            $this->getDefaultHeaders(),
            $payload
        );

        foreach ($stream as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $data = Json::decode($line);
            if (!isset($data['message']['content'])) {
                continue;
            }

            $this->totalTokens = $data['eval_count'] ?? 0;
            yield $data['message']['content'];
        }
    }

    public function setSystemMessage(string $systemMessage): void
    {
        $this->systemMessage = $systemMessage;
    }

    public function setTemperature(float $temperature): void
    {
        $this->config['temperature'] = $temperature;
    }

    public function setMaxTokens(int $maxTokens): void
    {
        $this->config['max_tokens'] = $maxTokens;
    }

    public function getTotalTokens(): int
    {
        return $this->totalTokens;
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     * @return array<int, array<string, mixed>>
     */
    protected function buildMessages(array $messages): array
    {
        $allMessages = [];

        if ($this->systemMessage !== '') {
            $allMessages[] = Message::system($this->systemMessage)->toArray();
        }

        foreach ($messages as $msg) {
            $role = $msg['role'] ?? ChatRole::USER;
            if ($role === ChatRole::TOOL) {
                $role = ChatRole::USER;
            }
            $allMessages[] = [
                'role' => $role,
                'content' => $msg['content'] ?? '',
            ];
        }

        return $allMessages;
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    protected function buildPayload(array $messages, array $options = []): array
    {
        $payload = [
            'model' => $options['model'] ?? $this->model,
            'messages' => $messages,
            'stream' => false,
        ];

        $temperature = $options['temperature'] ?? ($this->config['temperature'] ?? 0.7);
        if ($temperature > 0) {
            $payload['options']['temperature'] = $temperature;
        }

        $maxTokens = $options['max_tokens'] ?? ($this->config['max_tokens'] ?? 1024);
        if ($maxTokens > 0) {
            $payload['options']['num_predict'] = $maxTokens;
        }

        return $payload;
    }

    /**
     * @return array<string, string>
     */
    protected function getDefaultHeaders(): array
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        $extraHeaders = $this->config['headers'] ?? [];
        foreach ($extraHeaders as $key => $value) {
            $headers[$key] = $value;
        }

        return $headers;
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     * @param array<int, array<string, mixed>> $tools
     * @param array<string, mixed> $options
     * @return mixed
     */
    protected function generateChatWithTools(array $messages, array $tools, array $options = [])
    {
        $allMessages = $this->buildMessages($messages);
        $payload = $this->buildPayload($allMessages, $options);

        $ollamaTools = [];
        foreach ($tools as $tool) {
            if (isset($tool['function'])) {
                $ollamaTools[] = [
                    'type' => 'function',
                    'function' => $tool['function'],
                ];
            } else {
                $ollamaTools[] = $tool;
            }
        }
        $payload['tools'] = $ollamaTools;

        $response = $this->httpClient->post(
            $this->baseUrl . '/api/chat',
            $this->getDefaultHeaders(),
            $payload
        );

        $data = Json::decode($response->getBody());
        $this->totalTokens = $data['eval_count'] ?? 0;

        $message = $data['message'] ?? [];

        if (isset($message['tool_calls']) && !empty($message['tool_calls'])) {
            return ['content' => $message['content'] ?? '', 'tool_calls' => $message['tool_calls']];
        }

        return $message['content'] ?? '';
    }
}