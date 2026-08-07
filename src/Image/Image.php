<?php

declare(strict_types=1);

namespace PhpLLP\Image;

class Image
{
    /** @var string */
    private $url;

    /** @var string */
    private $base64;

    /** @var string */
    private $mimeType;

    /** @var int */
    private $width;

    /** @var int */
    private $height;

    /** @var string */
    private $prompt;

    /**
     * @param array{url?: string, base64?: string, mime_type?: string, width?: int, height?: int, prompt?: string} $data
     */
    public function __construct(array $data = [])
    {
        $this->url = $data['url'] ?? '';
        $this->base64 = $data['base64'] ?? '';
        $this->mimeType = $data['mime_type'] ?? 'image/png';
        $this->width = $data['width'] ?? 0;
        $this->height = $data['height'] ?? 0;
        $this->prompt = $data['prompt'] ?? '';
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getBase64(): string
    {
        return $this->base64;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function getPrompt(): string
    {
        return $this->prompt;
    }

    /**
     * @return array{url?: string, base64?: string}
     */
    public function toArray(): array
    {
        $result = [];
        if ($this->url !== '') {
            $result['url'] = $this->url;
        }
        if ($this->base64 !== '') {
            $result['base64'] = $this->base64;
        }
        return $result;
    }
}