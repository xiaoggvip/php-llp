<?php

declare(strict_types=1);

namespace PhpLLP\Contracts;

use PhpLLP\Http\HttpResponse;

interface HttpClientInterface
{
    /**
     * @param string $url
     * @param array<string, string> $headers
     * @return HttpResponse
     */
    public function get(string $url, array $headers = []): HttpResponse;

    /**
     * @param string $url
     * @param array<string, string> $headers
     * @param mixed $body
     * @return HttpResponse
     */
    public function post(string $url, array $headers = [], $body = null): HttpResponse;

    /**
     * @param string $url
     * @param array<string, string> $headers
     * @param mixed $body
     * @return HttpResponse
     */
    public function put(string $url, array $headers = [], $body = null): HttpResponse;

    /**
     * @param string $url
     * @param array<string, string> $headers
     * @param mixed $body
     * @return HttpResponse
     */
    public function delete(string $url, array $headers = [], $body = null): HttpResponse;

    /**
     * @param string $method
     * @param string $url
     * @param array<string, string> $headers
     * @param mixed $body
     * @return HttpResponse
     */
    public function request(string $method, string $url, array $headers = [], $body = null): HttpResponse;

    /**
     * @param string $method
     * @param string $url
     * @param array<string, string> $headers
     * @param mixed $body
     * @return \Generator
     */
    public function stream(string $method, string $url, array $headers = [], $body = null): \Generator;
}