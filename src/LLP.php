<?php

declare(strict_types=1);

namespace PhpLLP;

use PhpLLP\Contracts\ChatInterface;
use PhpLLP\Contracts\ImageInterface;
use PhpLLP\Contracts\AudioInterface;
use PhpLLP\Contracts\EmbeddingInterface;
use PhpLLP\VectorStore\VectorStoreFactory;
use PhpLLP\VectorStore\VectorStoreBase;
use PhpLLP\Contracts\ToolInterface;
use PhpLLP\Http\HttpClient;
use PhpLLP\Exception\ConfigException;

class LLP
{
    /** @var array<string, mixed> */
    private $config;

    /** @var HttpClient */
    private $httpClient;

    /** @var array<string, ChatInterface> */
    private $chatProviders = [];

    /** @var array<string, EmbeddingInterface> */
    private $embeddingProviders = [];

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->httpClient = new HttpClient($this->config);
    }

    /**
     * @return array<string, mixed>
     */
    private function getDefaultConfig(): array
    {
        return [
            'provider' => 'openai',
            'model' => 'gpt-4o',
            'api_key' => '',
            'base_url' => '',
            'temperature' => 0.7,
            'max_tokens' => 1024,
            'stream' => false,
            'timeout' => 30,
            'proxy' => null,
            'headers' => [],
        ];
    }

    /**
     * @param array<string, mixed> $params
     * @return mixed
     */
    public function run(array $params)
    {
        $task = $params['task'] ?? '';

        switch ($task) {
            case 'chat':
                return $this->handleChat($params);
            case 'image':
                return $this->handleImage($params);
            case 'audio':
                return $this->handleAudio($params);
            case 'embed':
                return $this->handleEmbedding($params);
            case 'qa':
                return $this->handleQuestionAnswering($params);
            default:
                throw new ConfigException("未知的任务类型: {$task}");
        }
    }

    public function chat(string $prompt, array $options = []): string
    {
        $toolManager = $this->getToolManager();
        $tools = $toolManager->all();

        if (!empty($tools)) {
            return $this->chatWithTools($prompt, $tools, $options);
        }

        $chat = $this->getChatProvider();
        return $chat->generateText($prompt, $options);
    }

    /**
     * @param string $prompt
     * @param array<string, \PhpLLP\Contracts\ToolInterface> $tools
     * @param array<string, mixed> $options
     * @return string
     */
    private function chatWithTools(string $prompt, array $tools, array $options = []): string
    {
        $chat = $this->getChatProvider();
        $toolManager = $this->getToolManager();
        $debug = $options['debug'] ?? ($this->config['debug'] ?? false);

        $toolSchemas = [];
        foreach ($tools as $tool) {
            $functionInfo = \PhpLLP\Chat\FunctionCall\FunctionInfo::fromTool($tool);
            $toolSchemas[] = $functionInfo->toToolFormat();
        }

        $messages = [
            ['role' => 'user', 'content' => $prompt],
        ];

        $iteration = 0;
        $maxIterations = $options['max_iterations'] ?? 10;

        if ($debug) {
            error_log('[LLP] Starting tool-enabled chat with ' . count($tools) . ' tools, max iterations: ' . $maxIterations);
        }

        while ($iteration < $maxIterations) {
            $iteration++;

            if ($debug) {
                error_log('[LLP] Iteration ' . $iteration . ', messages count: ' . count($messages));
            }

            $result = $chat->generateChatWithTools($messages, $toolSchemas, $options);

            if ($debug) {
                error_log('[LLP] Result type: ' . gettype($result) . ', value: ' . (is_string($result) ? $result : json_encode($result, JSON_UNESCAPED_UNICODE)));
            }

            if (is_string($result)) {
                if ($debug) {
                    error_log('[LLP] Got string response, returning directly');
                }
                return $result;
            }

            if (is_array($result) && isset($result['tool_calls'])) {
                $toolCalls = $result['tool_calls'];
                $assistantContent = $result['content'] ?? '';

                if ($debug) {
                    error_log('[LLP] Got ' . count($toolCalls) . ' tool calls');
                }

                $assistantMessage = [
                    'role' => 'assistant',
                    'content' => $assistantContent,
                    'tool_calls' => [],
                ];

                foreach ($toolCalls as $toolCallData) {
                    $toolCall = \PhpLLP\Chat\FunctionCall\ToolCall::fromArray($toolCallData);
                    $functionName = $toolCall->getFunctionName();

                    if ($debug) {
                        error_log('[LLP] Tool call: ' . $functionName . ' with args: ' . json_encode($toolCall->getArguments(), JSON_UNESCAPED_UNICODE));
                    }

                    $assistantMessage['tool_calls'][] = $toolCallData;
                }

                $messages[] = $assistantMessage;

                foreach ($toolCalls as $toolCallData) {
                    $toolCall = \PhpLLP\Chat\FunctionCall\ToolCall::fromArray($toolCallData);
                    $functionName = $toolCall->getFunctionName();

                    $toolResult = $toolManager->execute($functionName, $toolCall->getArguments());

                    if ($debug) {
                        error_log('[LLP] Tool ' . $functionName . ' result: ' . ($toolResult->isSuccess() ? 'success' : 'failed') . ' - ' . (string)$toolResult);
                    }

                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $toolCall->getId(),
                        'content' => $toolResult->isSuccess()
                            ? (is_array($toolResult->getData()) ? \PhpLLP\Support\Json::encode($toolResult->getData()) : (string)$toolResult->getData())
                            : $toolResult->getError(),
                    ];
                }
            } else {
                if ($debug) {
                    error_log('[LLP] No tool calls and not a string result, breaking loop');
                }
                break;
            }
        }

        if ($debug) {
            error_log('[LLP] Tool chat completed after ' . $iteration . ' iterations');
        }

        return '';
    }

    /**
     * @param array<int, array{role: string, content: string}> $messages
     * @param array<string, mixed> $options
     * @return string
     */
    public function conversation(array $messages, array $options = []): string
    {
        $chat = $this->getChatProvider();
        return $chat->generateChat($messages, $options);
    }

    public function chatStream(string $prompt, array $options = []): \Generator
    {
        $chat = $this->getChatProvider();
        return $chat->generateStream($prompt, $options);
    }

    /**
     * @param string $prompt
     * @param array<int, array<string, mixed>> $tools
     * @param array<string, mixed> $options
     * @return mixed
     */
    public function toolCall(string $prompt, array $tools, array $options = [])
    {
        $chat = $this->getChatProvider();
        return $chat->generateTextWithTools($prompt, $tools, $options);
    }

    /**
     * @param string $prompt
     * @param array<string, mixed> $options
     * @return array{url?: string, base64?: string}
     */
    public function image(string $prompt, array $options = []): array
    {
        $provider = $this->getImageProvider();
        return $provider->generate($prompt, $options);
    }

    /**
     * @param string $filePath
     * @param array<string, mixed> $options
     * @return array{text: string, language: string|null, duration: float|null}
     */
    public function transcribe(string $filePath, array $options = []): array
    {
        $provider = $this->getAudioProvider();
        return $provider->transcribe($filePath, $options);
    }

    /**
     * @param string $text
     * @param array<string, mixed> $options
     * @return array<int, float>
     */
    public function embed(string $text, array $options = []): array
    {
        $generator = $this->getEmbeddingProvider();
        return $generator->embedText($text, $options);
    }

    public function ask(string $question, array $options = []): string
    {
        $vectorStoreType = $options['vector_store'] ?? 'filesystem';
        $vectorStore = $this->createVectorStore($vectorStoreType);
        $embeddingProvider = $this->getEmbeddingProvider();
        $chatProvider = $this->getChatProvider();

        $qa = new \PhpLLP\Query\QuestionAnswering($chatProvider, $embeddingProvider, $vectorStore);
        $qa->setTopK($options['top_k'] ?? 5);
        $qa->setThreshold($options['threshold'] ?? 0.0);

        $result = $qa->answer($question, $options);
        return $result['answer'] ?? '';
    }

    private function getChatProvider(): ChatInterface
    {
        $provider = $this->config['provider'];

        if (!isset($this->chatProviders[$provider])) {
            $this->chatProviders[$provider] = $this->createChatProvider($provider);
        }

        return $this->chatProviders[$provider];
    }

    private function createChatProvider(string $provider): ChatInterface
    {
        switch ($provider) {
            case 'openai':
                return new \PhpLLP\Chat\Provider\OpenAIChat($this->config, $this->httpClient);
            case 'anthropic':
                return new \PhpLLP\Chat\Provider\AnthropicChat($this->config, $this->httpClient);
            case 'mistral':
                return new \PhpLLP\Chat\Provider\MistralChat($this->config, $this->httpClient);
            case 'ollama':
                return new \PhpLLP\Chat\Provider\OllamaChat($this->config, $this->httpClient);
            default:
                throw new ConfigException("不支持的 Chat Provider: {$provider}");
        }
    }

    private function getEmbeddingProvider(): EmbeddingInterface
    {
        $provider = $this->config['provider'];

        if (!isset($this->embeddingProviders[$provider])) {
            $this->embeddingProviders[$provider] = $this->createEmbeddingProvider($provider);
        }

        return $this->embeddingProviders[$provider];
    }

    private function createEmbeddingProvider(string $provider): EmbeddingInterface
    {
        switch ($provider) {
            case 'openai':
                return new \PhpLLP\Embeddings\Generator\OpenAIEmbeddingGenerator($this->config, $this->httpClient);
            case 'mistral':
                return new \PhpLLP\Embeddings\Generator\MistralEmbeddingGenerator($this->config, $this->httpClient);
            case 'ollama':
                return new \PhpLLP\Embeddings\Generator\OllamaEmbeddingGenerator($this->config, $this->httpClient);
            default:
                throw new ConfigException("不支持的 Embedding Provider: {$provider}");
        }
    }

    private function getImageProvider(): ImageInterface
    {
        $provider = $this->config['provider'];

        switch ($provider) {
            case 'openai':
                return new \PhpLLP\Image\Provider\OpenAIImage($this->config, $this->httpClient);
            default:
                throw new ConfigException("不支持的 Image Provider: {$provider}");
        }
    }

    private function getAudioProvider(): AudioInterface
    {
        $provider = $this->config['provider'];

        switch ($provider) {
            case 'openai':
                return new \PhpLLP\Audio\Provider\WhisperAudio($this->config, $this->httpClient);
            default:
                throw new ConfigException("不支持的 Audio Provider: {$provider}");
        }
    }

    private function createVectorStore(string $type): VectorStoreBase
    {
        $config = array_merge([
            'collection' => $this->config['collection'] ?? 'default',
            'dimension' => $this->config['dimension'] ?? 1536,
            'path' => $this->config['path'] ?? './vector_store',
            'db_path' => $this->config['db_path'] ?? ':memory:',
        ], $this->config);

        $typeMap = [
            'filesystem' => VectorStoreFactory::TYPE_FILE_SYSTEM,
            'sqlite' => VectorStoreFactory::TYPE_SQLITE,
            'postgres' => VectorStoreFactory::TYPE_POSTGRES,
            'qdrant' => VectorStoreFactory::TYPE_QDRANT,
            'redis' => VectorStoreFactory::TYPE_REDIS,
            'elasticsearch' => VectorStoreFactory::TYPE_ELASTICSEARCH,
            'milvus' => VectorStoreFactory::TYPE_MILVUS,
            'chromadb' => VectorStoreFactory::TYPE_CHROMA,
            'astradb' => VectorStoreFactory::TYPE_ASTRA,
        ];

        $factoryType = $typeMap[$type] ?? $type;

        return VectorStoreFactory::create($factoryType, $config);
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return self
     */
    public function setConfig(string $key, $value): self
    {
        $this->config[$key] = $value;
        return $this;
    }

    /**
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getConfig(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * @return \PhpLLP\Tools\ToolManager
     */
    public function getToolManager(): \PhpLLP\Tools\ToolManager
    {
        static $manager = null;
        if ($manager === null) {
            $manager = new \PhpLLP\Tools\ToolManager();
        }
        return $manager;
    }

    /**
     * @param ToolInterface|\PhpLLP\Chat\FunctionCall\FunctionInfo $tool
     * @return self
     */
    public function registerTool($tool): self
    {
        $this->getToolManager()->register($tool);
        return $this;
    }

    /**
     * @param array<string, mixed> $params
     * @return string
     */
    private function handleChat(array $params): string
    {
        if (isset($params['messages'])) {
            return $this->conversation($params['messages'], $params['options'] ?? []);
        }
        return $this->chat($params['prompt'] ?? '', $params['options'] ?? []);
    }

    /**
     * @param array<string, mixed> $params
     * @return array
     */
    private function handleImage(array $params): array
    {
        return $this->image($params['prompt'] ?? '', $params['options'] ?? []);
    }

    /**
     * @param array<string, mixed> $params
     * @return array
     */
    private function handleAudio(array $params): array
    {
        return $this->transcribe($params['file_path'] ?? '', $params['options'] ?? []);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<int, float>
     */
    private function handleEmbedding(array $params): array
    {
        return $this->embed($params['text'] ?? '', $params['options'] ?? []);
    }

    /**
     * @param array<string, mixed> $params
     * @return string
     */
    private function handleQuestionAnswering(array $params): string
    {
        return $this->ask($params['question'] ?? '', $params);
    }
}