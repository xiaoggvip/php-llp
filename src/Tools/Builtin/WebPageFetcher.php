<?php

declare(strict_types=1);

namespace PhpLLP\Tools\Builtin;

use PhpLLP\Contracts\ToolInterface;

class WebPageFetcher implements ToolInterface
{
    /** @var int */
    private $timeout;

    /** @var int */
    private $maxLength;

    /**
     * @param int $timeout
     * @param int $maxLength
     */
    public function __construct(int $timeout = 15, int $maxLength = 10000)
    {
        $this->timeout = $timeout;
        $this->maxLength = $maxLength;
    }

    public function getName(): string
    {
        return 'web_page_fetcher';
    }

    public function getDescription(): string
    {
        return '获取指定URL的网页内容。可用于阅读文章、获取文档等。';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'url' => [
                    'type' => 'string',
                    'description' => '要获取的网页URL',
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

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; phpLLP Bot)',
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
        ]);

        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($content === false || $httpCode >= 400) {
            return "获取失败 (HTTP {$httpCode}): {$error}";
        }

        $content = $this->extractText($content);

        if (strlen($content) > $this->maxLength) {
            $content = substr($content, 0, $this->maxLength) . "\n... (内容已截断)";
        }

        return $content;
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

    /**
     * @param string $html
     * @return string
     */
    private function extractText(string $html): string
    {
        $html = preg_replace('/<script[^>]*>.*?<\/script>/s', '', $html);
        $html = preg_replace('/<style[^>]*>.*?<\/style>/s', '', $html);
        $html = preg_replace('/<head[^>]*>.*?<\/head>/s', '', $html);
        $html = preg_replace('/<[^>]+>/', ' ', $html);
        $html = html_entity_decode($html, ENT_QUOTES, 'UTF-8');
        $html = preg_replace('/\s+/', ' ', $html);
        return trim($html);
    }
}