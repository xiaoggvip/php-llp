<?php

declare(strict_types=1);

namespace PhpLLP\Http;

use PhpLLP\Contracts\HttpClientInterface;
use PhpLLP\Support\Json;

class HttpClient implements HttpClientInterface
{
    /** @var int */
    private $timeout;

    /** @var string|null */
    private $proxy;

    /** @var array<string, string> */
    private $defaultHeaders;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $this->timeout = $config['timeout'] ?? 30;
        $this->proxy = $config['proxy'] ?? null;
        $this->defaultHeaders = $config['headers'] ?? [];
    }

    public function get(string $url, array $headers = []): HttpResponse
    {
        return $this->request('GET', $url, $headers);
    }

    public function post(string $url, array $headers = [], $body = null): HttpResponse
    {
        return $this->request('POST', $url, $headers, $body);
    }

    public function put(string $url, array $headers = [], $body = null): HttpResponse
    {
        return $this->request('PUT', $url, $headers, $body);
    }

    public function delete(string $url, array $headers = [], $body = null): HttpResponse
    {
        return $this->request('DELETE', $url, $headers, $body);
    }

    public function request(string $method, string $url, array $headers = [], $body = null): HttpResponse
    {
        $ch = curl_init();

        $mergedHeaders = array_merge($this->defaultHeaders, $headers);
        $formattedHeaders = [];
        foreach ($mergedHeaders as $key => $value) {
            $formattedHeaders[] = $key . ': ' . $value;
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => $formattedHeaders,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($body) ? Json::encode($body) : $body);
        }

        if ($this->proxy !== null) {
            curl_setopt($ch, CURLOPT_PROXY, $this->proxy);
        }

        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        if ($responseBody === false) {
            curl_close($ch);
            return new HttpResponse($httpCode, $error, '[]');
        }

        $headersSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $responseHeaders = substr($responseBody, 0, $headersSize);
        $bodyContent = substr($responseBody, $headersSize);

        curl_close($ch);

        return new HttpResponse($httpCode, $bodyContent, $responseHeaders);
    }

    public function stream(string $method, string $url, array $headers = [], $body = null): \Generator
    {
        return $this->requestStream($method, $url, $headers, $body);
    }

    /**
     * @param string $method
     * @param string $url
     * @param array<string, string> $headers
     * @param mixed $body
     * @return \Generator
     */
    private function requestStream(string $method, string $url, array $headers = [], $body = null): \Generator
    {
        $ch = curl_init();

        $mergedHeaders = array_merge($this->defaultHeaders, $headers);
        $formattedHeaders = [];
        foreach ($mergedHeaders as $key => $value) {
            $formattedHeaders[] = $key . ': ' . $value;
        }

        $lines = [];
        $lineBuffer = '';

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_HTTPHEADER => $formattedHeaders,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_WRITEFUNCTION => function ($ch, $chunk) use (&$lines, &$lineBuffer) {
                $lineBuffer .= $chunk;
                $parts = explode("\n", $lineBuffer);
                $lineBuffer = array_pop($parts);

                foreach ($parts as $part) {
                    $part = rtrim($part, "\r");
                    if ($part !== '') {
                        $lines[] = $part;
                    }
                }

                return strlen($chunk);
            },
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($body) ? Json::encode($body) : $body);
        }

        if ($this->proxy !== null) {
            curl_setopt($ch, CURLOPT_PROXY, $this->proxy);
        }

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($lineBuffer !== '') {
            $line = rtrim($lineBuffer, "\r");
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new \RuntimeException(
                sprintf('HTTP流式请求失败: %s %s - 状态码: %d, 错误: %s', $method, $url, $httpCode, $error),
                $httpCode
            );
        }

        foreach ($lines as $line) {
            yield $line;
        }
    }
}