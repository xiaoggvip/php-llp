<?php

declare(strict_types=1);

namespace PhpLLP\Tools\Builtin;

use PhpLLP\Contracts\ToolInterface;

class HttpRequester implements ToolInterface
{
    /** @var int */
    private $timeout;

    /**
     * @param int $timeout
     */
    public function __construct(int $timeout = 30)
    {
        $this->timeout = $timeout;
    }

    public function getName(): string
    {
        return 'http_request';
    }

    public function getDescription(): string
    {
        return '发送HTTP请求到指定URL。支持GET、POST、PUT、DELETE方法。可调用外部API。';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'url' => [
                    'type' => 'string',
                    'description' => '请求的URL',
                ],
                'method' => [
                    'type' => 'string',
                    'description' => 'HTTP方法 (GET, POST, PUT, DELETE)',
                ],
                'headers' => [
                    'type' => 'object',
                    'description' => '请求头',
                ],
                'body' => [
                    'type' => 'string',
                    'description' => '请求体 (POST/PUT)',
                ],
            ],
            'required' => ['url'],
        ];
    }

    public function execute(array $parameters)
    {
        $url = $parameters['url'] ?? '';
        if (empty($url)) {
            return '错误：必须提供URL参数';
        }

        $method = strtoupper($parameters['method'] ?? 'GET');
        $headers = $parameters['headers'] ?? [];
        $body = $parameters['body'] ?? null;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CUSTOMREQUEST => $method,
        ]);

        if (!empty($headers)) {
            $formattedHeaders = [];
            foreach ($headers as $key => $value) {
                $formattedHeaders[] = $key . ': ' . $value;
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $formattedHeaders);
        }

        if ($body !== null && in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return "请求失败: {$error}";
        }

        return [
            'status_code' => $httpCode,
            'body' => $response,
        ];
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