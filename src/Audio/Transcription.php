<?php

declare(strict_types=1);

namespace PhpLLP\Audio;

class Transcription
{
    /** @var string */
    private $text;

    /** @var string|null */
    private $language;

    /** @var float|null */
    private $duration;

    /**
     * @param array{text: string, language?: string|null, duration?: float|null} $data
     */
    public function __construct(array $data = [])
    {
        $this->text = $data['text'] ?? '';
        $this->language = $data['language'] ?? null;
        $this->duration = $data['duration'] ?? null;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function getDuration(): ?float
    {
        return $this->duration;
    }

    /**
     * @return array{text: string, language: string|null, duration: float|null}
     */
    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'language' => $this->language,
            'duration' => $this->duration,
        ];
    }
}