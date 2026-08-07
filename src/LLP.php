<?php

declare(strict_types=1);

namespace PhpLLP;

use PhpLLP\Contracts\ChatInterface;
use PhpLLP\Contracts\ImageInterface;
use PhpLLP\Contracts\AudioInterface;
use PhpLLP\Contracts\EmbeddingInterface;
use PhpLLP\Contracts\VectorStoreInterface;
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
        $chat = $this->getChatProvider();
        return $chat->generateText($prompt, $options);
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

        $qa = new \PhpLLP\Query\QuestionAnswering($vectorStore, $embeddingProvider, $chatProvider);
        return $qa->answer($question, $options);
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

    private function createVectorStore(string $type): VectorStoreInterface
    {
        $config = $this->config;
        switch ($type) {
            case 'filesystem':
                return new \PhpLLP\VectorStore\FileSystemVectorStore($config);
            case 'sqlite':
                return new \PhpLLP\VectorStore\SQLiteVectorStore($config);
            case 'postgres':
                return new \PhpLLP\VectorStore\PostgresVectorStore($config);
            case 'qdrant':
                return new \PhpLLP\VectorStore\QdrantVectorStore($config, $this->httpClient);
            case 'redis':
                return new \PhpLLP\VectorStore\RedisVectorStore($config);
            case 'elasticsearch':
                return new \PhpLLP\VectorStore\ElasticsearchVectorStore($config, $this->httpClient);
            case 'milvus':
                return new \PhpLLP\VectorStore\MilvusVectorStore($config, $this->httpClient);
            case 'chromadb':
                return new \PhpLLP\VectorStore\ChromaDBVectorStore($config, $this->httpClient);
            case 'astradb':
                return new \PhpLLP\VectorStore\AstraDBVectorStore($config, $this->httpClient);
            default:
                throw new ConfigException("不支持的 VectorStore: {$type}");
        }
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