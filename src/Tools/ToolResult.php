<?php

declare(strict_types=1);

namespace PhpLLP\Tools;

class ToolResult
{
    /** @var bool */
    private $success;

    /** @var mixed */
    private $data;

    /** @var string */
    private $error;

    /** @var float */
    private $duration;

    /**
     * @param mixed $data
     * @param bool $success
     * @param string $error
     * @param float $duration
     */
    public function __construct($data = null, bool $success = true, string $error = '', float $duration = 0)
    {
        $this->data = $data;
        $this->success = $success;
        $this->error = $error;
        $this->duration = $duration;
    }

    /**
     * @param mixed $data
     * @param float $duration
     * @return self
     */
    public static function success($data, float $duration = 0): self
    {
        return new self($data, true, '', $duration);
    }

    /**
     * @param string $error
     * @param float $duration
     * @return self
     */
    public static function failure(string $error, float $duration = 0): self
    {
        return new self(null, false, $error, $duration);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    public function getError(): string
    {
        return $this->error;
    }

    public function getDuration(): float
    {
        return $this->duration;
    }

    public function __toString(): string
    {
        if (!$this->success) {
            return "Error: {$this->error}";
        }

        if (is_string($this->data)) {
            return $this->data;
        }

        if (is_array($this->data) || is_object($this->data)) {
            return json_encode($this->data, JSON_UNESCAPED_UNICODE);
        }

        return (string)$this->data;
    }

    /**
     * @return array{success: bool, data: mixed, error: string, duration: float}
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'data' => $this->data,
            'error' => $this->error,
            'duration' => $this->duration,
        ];
    }
}