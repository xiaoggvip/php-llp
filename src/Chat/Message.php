<?php

declare(strict_types=1);

namespace PhpLLP\Chat;

class Message
{
    /** @var string */
    private $role;

    /** @var string */
    private $content;

    /** @var string|null */
    private $toolCallId;

    /** @var array<int, array<string, mixed>> */
    private $toolCalls = [];

    /**
     * @param string $role
     * @param string $content
     * @param string|null $toolCallId
     * @param array<int, array<string, mixed>> $toolCalls
     */
    public function __construct(
        string $role,
        string $content,
        ?string $toolCallId = null,
        array $toolCalls = []
    ) {
        $this->role = ChatRole::normalize($role);
        $this->content = $content;
        $this->toolCallId = $toolCallId;
        $this->toolCalls = $toolCalls;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getToolCallId(): ?string
    {
        return $this->toolCallId;
    }

    public function getToolCalls(): array
    {
        return $this->toolCalls;
    }

    /**
     * @param array<int, array<string, mixed>> $toolCalls
     * @return self
     */
    public function setToolCalls(array $toolCalls): self
    {
        $this->toolCalls = $toolCalls;
        return $this;
    }

    /**
     * @return array{role: string, content: string, tool_call_id?: string, tool_calls?: array}
     */
    public function toArray(): array
    {
        $result = [
            'role' => $this->role,
            'content' => $this->content,
        ];

        if ($this->toolCallId !== null) {
            $result['tool_call_id'] = $this->toolCallId;
        }

        if (!empty($this->toolCalls)) {
            $result['tool_calls'] = $this->toolCalls;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['role'] ?? ChatRole::USER,
            $data['content'] ?? '',
            $data['tool_call_id'] ?? null,
            $data['tool_calls'] ?? []
        );
    }

    public static function user(string $content): self
    {
        return new self(ChatRole::USER, $content);
    }

    public static function system(string $content): self
    {
        return new self(ChatRole::SYSTEM, $content);
    }

    public static function assistant(string $content): self
    {
        return new self(ChatRole::ASSISTANT, $content);
    }

    /**
     * @param string $toolCallId
     * @param string $content
     * @return self
     */
    public static function tool(string $toolCallId, string $content): self
    {
        return new self(ChatRole::TOOL, $content, $toolCallId);
    }
}