<?php

declare(strict_types=1);

namespace PhpLLP\Image\Provider;

use PhpLLP\Contracts\ImageInterface;
use PhpLLP\Http\HttpClient;
use PhpLLP\Support\Json;

class OpenAIImage implements ImageInterface
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
        $this->model = $config['model'] ?? 'dall-e-3';
    }

    public function generate(string $prompt, array $options = []): array
    {
        $payload = [
            'model' => $options['model'] ?? $this->model,
            'prompt' => $prompt,
            'size' => $options['size'] ?? '1024x1024',
            'n' => $options['n'] ?? 1,
            'response_format' => $options['response_format'] ?? 'url',
        ];

        if (isset($options['style'])) {
            $payload['style'] = $options['style'];
        }

        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->apiKey,
        ];

        $response = $this->httpClient->post(
            $this->baseUrl . '/images/generations',
            $headers,
            $payload
        );

        $data = Json::decode($response->getBody());
        $images = $data['data'] ?? [];

        $result = [];
        foreach ($images as $image) {
            if (isset($image['url'])) {
                $result[] = ['url' => $image['url']];
            } elseif (isset($image['b64_json'])) {
                $result[] = ['base64' => $image['b64_json']];
            }
        }

        return count($result) === 1 ? $result[0] : $result;
    }
}