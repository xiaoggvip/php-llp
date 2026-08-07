<?php

declare(strict_types=1);

namespace PhpLLP\Chat\Provider;

use PhpLLP\Chat\ChatRole;
use PhpLLP\Chat\Message;
use PhpLLP\Contracts\ChatInterface;
use PhpLLP\Http\HttpClient;
use PhpLLP\Support\Json;

class OpenAIChat implements ChatInterface
{
    /** @var array<string, mixed> */
    private $config;

    /** @var HttpClient */
    private $httpClient;

    /** @var string */
    private $apiKey;

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
        $this->apiKey = $config['api_key'] ?? '';
        $this->baseUrl = rtrim($config['base_url'] ?? 'https://api.openai.com/v1', '/');
        $this->model = $config['model'] ?? 'gpt-4o';
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
            $this->baseUrl . '/chat/completions',
            $this->getDefaultHeaders(),
            $payload
        );

        $data = Json::decode($response->getBody());
        $this->totalTokens += $data['usage']['total_tokens'] ?? 0;

        return $data['choices'][0]['message']['content'] ?? '';
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
            $this->baseUrl . '/chat/completions',
            $this->getDefaultHeaders(),
            $payload
        );

        foreach ($stream as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, 'data: ') !== 0) {
                continue;
            }

            $dataStr = substr($line, 6);
            if ($dataStr === '[DONE]') {
                break;
            }

            $data = Json::decode($dataStr);
            $delta = $data['choices'][0]['delta']['content'] ?? '';
            if ($delta !== '') {
                yield $delta;
            }
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
     * @param array<int, array{role: string, content: string}> $messages
     * @return array<int, array<string, mixed>>
     */
    protected function buildMessages(array $messages): array
    {
        $allMessages = [];

        if ($this->systemMessage !== '') {
            $allMessages[] = Message::system($this->systemMessage)->toArray();
        }

        foreach ($messages as $msg) {
            $allMessages[] = [
                'role' => $msg['role'] ?? ChatRole::USER,
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
        return [
            'model' => $options['model'] ?? $this->model,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? ($this->config['temperature'] ?? 0.7),
            'max_tokens' => $options['max_tokens'] ?? ($this->config['max_tokens'] ?? 1024),
        ];
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    protected function getDefaultHeaders(): array
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->apiKey,
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
        $payload['tools'] = $tools;

        $response = $this->httpClient->post(
            $this->baseUrl . '/chat/completions',
            $this->getDefaultHeaders(),
            $payload
        );

        $data = Json::decode($response->getBody());
        $this->totalTokens += $data['usage']['total_tokens'] ?? 0;

        $choice = $data['choices'][0] ?? [];
        $message = $choice['message'] ?? [];

        if (isset($message['tool_calls']) && !empty($message['tool_calls'])) {
            return [
                'content' => $message['content'] ?? '',
                'tool_calls' => $message['tool_calls'],
            ];
        }

        return $message['content'] ?? '';
    }
}