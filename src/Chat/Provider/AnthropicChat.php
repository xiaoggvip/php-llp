<?php

declare(strict_types=1);

namespace PhpLLP\Chat\Provider;

use PhpLLP\Chat\ChatRole;
use PhpLLP\Chat\Message;
use PhpLLP\Contracts\ChatInterface;
use PhpLLP\Http\HttpClient;
use PhpLLP\Support\Json;

class AnthropicChat implements ChatInterface
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
        $this->baseUrl = rtrim($config['base_url'] ?? 'https://api.anthropic.com', '/');
        $this->model = $config['model'] ?? 'claude-3-5-sonnet-20241022';
    }

    public function generateText(string $prompt, array $options = []): string
    {
        return $this->generateChat([Message::user($prompt)->toArray()], $options);
    }

    public function generateChat(array $messages, array $options = []): string
    {
        $payload = $this->buildPayload($messages, $options);

        $response = $this->httpClient->post(
            $this->baseUrl . '/v1/messages',
            $this->getDefaultHeaders(),
            $payload
        );

        $data = Json::decode($response->getBody());
        $this->totalTokens += ($data['usage']['input_tokens'] ?? 0) + ($data['usage']['output_tokens'] ?? 0);

        $content = $data['content'] ?? [];
        if (is_array($content) && isset($content[0]['text'])) {
            return $content[0]['text'];
        }

        return $data['content'] ?? '';
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
        $payload = $this->buildPayload($messages, $options);
        $payload['stream'] = true;

        $stream = $this->httpClient->stream(
            'POST',
            $this->baseUrl . '/v1/messages',
            $this->getDefaultHeaders(),
            $payload
        );

        $buffer = '';
        foreach ($stream as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, 'data: ') !== 0) {
                continue;
            }

            $dataStr = substr($line, 6);
            $data = Json::decode($dataStr);

            if (isset($data['type']) && $data['type'] === 'content_block_delta') {
                $delta = $data['delta']['text'] ?? '';
                if ($delta !== '') {
                    yield $delta;
                }
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
     * @param array<int, array<string, mixed>> $messages
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    protected function buildPayload(array $messages, array $options = []): array
    {
        $payload = [
            'model' => $options['model'] ?? $this->model,
            'messages' => $this->convertMessages($messages),
            'max_tokens' => $options['max_tokens'] ?? ($this->config['max_tokens'] ?? 1024),
        ];

        if ($this->systemMessage !== '') {
            $payload['system'] = $this->systemMessage;
        }

        $temperature = $options['temperature'] ?? ($this->config['temperature'] ?? 0.7);
        if ($temperature > 0) {
            $payload['temperature'] = $temperature;
        }

        return $payload;
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     * @return array<int, array<string, mixed>>
     */
    protected function convertMessages(array $messages): array
    {
        $result = [];
        foreach ($messages as $msg) {
            $role = $msg['role'] ?? ChatRole::USER;
            $content = $msg['content'] ?? '';

            if ($role === ChatRole::SYSTEM) {
                $this->systemMessage = $content;
                continue;
            }

            $result[] = [
                'role' => $role === ChatRole::TOOL ? ChatRole::USER : $role,
                'content' => $content,
            ];
        }
        return $result;
    }

    /**
     * @return array<string, string>
     */
    protected function getDefaultHeaders(): array
    {
        $headers = [
            'Content-Type' => 'application/json',
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2024-02-29',
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
    public function generateChatWithTools(array $messages, array $tools, array $options = [])
    {
        $payload = $this->buildPayload($messages, $options);
        $payload['tools'] = $tools;

        $response = $this->httpClient->post(
            $this->baseUrl . '/v1/messages',
            $this->getDefaultHeaders(),
            $payload
        );

        $data = Json::decode($response->getBody());
        $this->totalTokens += ($data['usage']['input_tokens'] ?? 0) + ($data['usage']['output_tokens'] ?? 0);

        $content = $data['content'] ?? [];
        if (is_array($content)) {
            $text = '';
            $toolUses = [];
            foreach ($content as $block) {
                if ($block['type'] === 'text') {
                    $text .= $block['text'];
                } elseif ($block['type'] === 'tool_use') {
                    $toolUses[] = $block;
                }
            }

            if (!empty($toolUses)) {
                return ['content' => $text, 'tool_calls' => $toolUses];
            }
            return $text;
        }

        return $data['content'] ?? '';
    }
}