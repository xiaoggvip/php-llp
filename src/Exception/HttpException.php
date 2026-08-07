<?php

declare(strict_types=1);

namespace PhpLLP\Exception;

class HttpException extends LLPException
{
    /** @var int */
    private $statusCode;

    /**
     * @param string $message
     * @param int $statusCode
     * @param \Throwable|null $previous
     */
    public function __construct(string $message = '', int $statusCode = 0, ?\Throwable $previous = null)
    {
        $this->statusCode = $statusCode;
        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}