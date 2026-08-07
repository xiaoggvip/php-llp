<?php

declare(strict_types=1);

namespace PhpLLP\Audio\Provider;

use PhpLLP\Contracts\AudioInterface;
use PhpLLP\Exception\LLPException;
use PhpLLP\Http\HttpClient;
use PhpLLP\Support\Json;

class WhisperAudio implements AudioInterface
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
        $this->model = $config['model'] ?? 'whisper-1';
    }

    public function transcribe(string $filePath, array $options = []): array
    {
        if (!file_exists($filePath)) {
            throw new LLPException("音频文件不存在: {$filePath}");
        }

        $fileContent = file_get_contents($filePath);
        if ($fileContent === false) {
            throw new LLPException("无法读取音频文件: {$filePath}");
        }

        $model = $options['model'] ?? $this->model;
        $language = $options['language'] ?? null;
        $responseFormat = $options['response_format'] ?? 'json';
        $temperature = $options['temperature'] ?? 0;

        $boundary = '----phpLLP' . uniqid();
        $fileName = basename($filePath);

        $body = "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"model\"\r\n\r\n";
        $body .= $model . "\r\n";

        if ($language !== null) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"language\"\r\n\r\n";
            $body .= $language . "\r\n";
        }

        if ($responseFormat !== 'json') {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"response_format\"\r\n\r\n";
            $body .= $responseFormat . "\r\n";
        }

        if ($temperature > 0) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"temperature\"\r\n\r\n";
            $body .= (string)$temperature . "\r\n";
        }

        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"file\"; filename=\"{$fileName}\"\r\n";
        $body .= "Content-Type: audio/mpeg\r\n\r\n";
        $body .= $fileContent . "\r\n";
        $body .= "--{$boundary}--\r\n";

        $headers = [
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
            'Authorization' => 'Bearer ' . $this->apiKey,
        ];

        $response = $this->httpClient->post(
            $this->baseUrl . '/audio/transcriptions',
            $headers,
            $body
        );

        $data = Json::decode($response->getBody());

        return [
            'text' => $data['text'] ?? '',
            'language' => $data['language'] ?? $language,
            'duration' => isset($data['duration']) ? (float)$data['duration'] : null,
        ];
    }
}