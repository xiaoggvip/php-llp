<?php

declare(strict_types=1);

namespace PhpLLP\Http;

class HttpResponse
{
    /** @var int */
    private $statusCode;

    /** @var string */
    private $body;

    /** @var array<string, string> */
    private $headers;

    /**
     * @param int $statusCode
     * @param string $body
     * @param string $rawHeaders
     */
    public function __construct(int $statusCode, string $body = '', string $rawHeaders = '')
    {
        $this->statusCode = $statusCode;
        $this->body = $body;
        $this->headers = $this->parseHeaders($rawHeaders);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $name): ?string
    {
        $normalized = strtolower($name);
        foreach ($this->headers as $key => $value) {
            if (strtolower($key) === $normalized) {
                return $value;
            }
        }
        return null;
    }

    /**
     * @return mixed
     */
    public function getJson()
    {
        return json_decode($this->body, true);
    }

    public function isSuccess(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    public function isRedirect(): bool
    {
        return $this->statusCode >= 300 && $this->statusCode < 400;
    }

    public function isClientError(): bool
    {
        return $this->statusCode >= 400 && $this->statusCode < 500;
    }

    public function isServerError(): bool
    {
        return $this->statusCode >= 500;
    }

    /**
     * @param string $rawHeaders
     * @return array<string, string>
     */
    private function parseHeaders(string $rawHeaders): array
    {
        $headers = [];
        $lines = explode("\r\n", $rawHeaders);

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, ':') === false) {
                continue;
            }

            list($key, $value) = explode(':', $line, 2);
            $headers[trim($key)] = trim($value);
        }

        return $headers;
    }
}