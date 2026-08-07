# phpLLP 开发方案

> 一个纯 PHP 实现的大语言模型应用开发库，参考 LLPhant 架构，支持 PHP 7.4+，零外部依赖。

---

## 1. 项目定位

phpLLP 是一个轻量级、零依赖的 PHP 库，用于构建基于大语言模型的应用程序。与 LLPhant 不同，phpLLP 完全通过原生 PHP 实现所有功能，不依赖任何第三方 Composer 包。

### 核心设计原则

- **零依赖**：不引入任何第三方包，HTTP 通信使用原生 cURL，所有序列化/反序列化、向量运算均自行实现
- **PHP 7.4+ 兼容**：严格遵循 PHP 7.4 语法规范，不使用 8.0+ 特性
- **统一入口**：通过一个主类 `LLP` 作为统一入口，通过参数配置或方法调用实现不同功能
- **驱动式架构**：AI  Provider 和 VectorStore 均采用驱动式设计，易于扩展

---

## 2. 系统架构

### 2.1 总体架构图

```
┌──────────────────────────────────────────────────────┐
│                    统一入口 (LLP)                      │
│  ┌─────────────────────────────────────────────────┐ │
│  │  Chat │ Image │ Audio │ Tools │ Embeddings │ QA │ │
│  └─────────────────────────────────────────────────┘ │
└─────────────┬──────────────────────┬─────────────────┘
              │                      │
    ┌─────────▼─────────┐    ┌───────▼───────────┐
    │   AI Provider     │    │   VectorStore    │
    │  ┌─────────────┐  │    │  ┌─────────────┐  │
    │  │ OpenAI      │  │    │  │ FileSystem  │  │
    │  ├─────────────┤  │    │  ├─────────────┤  │
    │  │ Anthropic   │  │    │  │ PostgreSQL  │  │
    │  ├─────────────┤  │    │  ├─────────────┤  │
    │  │ Mistral     │  │    │  │ Qdrant      │  │
    │  ├─────────────┤  │    │  ├─────────────┤  │
    │  │ Ollama      │  │    │  │ Redis       │  │
    │  └─────────────┘  │    │  ├─────────────┤  │
    │                    │    │  │ Elasticsearch│  │
    │                    │    │  ├─────────────┤  │
    │                    │    │  │ Milvus      │  │
    │                    │    │  ├─────────────┤  │
    │                    │    │  │ ChromaDB    │  │
    │                    │    │  ├─────────────┤  │
    │                    │    │  │ AstraDB     │  │
    │                    │    │  ├─────────────┤  │
    │                    │    │  │ SQLite      │  │
    │                    │    │  └─────────────┘  │
    └───────────────────┘    └───────────────────┘
```

### 2.2 模块说明

| 模块 | 功能 | 说明 |
|------|------|------|
| Chat | 多轮对话、文本生成、流式输出、Function Calling | 核心聊天功能 |
| Image | 图像生成 | 文生图 |
| Audio | 语音转文字 | Speech-to-Text（Whisper） |
| Tools | 工具调用（Function Calling） | 让 LLM 能调用外部工具 |
| Embeddings | 文本向量化 | 将文本转为向量嵌入 |
| VectorStore | 向量存储与检索 | 存储和相似度搜索 |
| Question Answering | 基于文档的问答 | RAG 流程实现 |

---

## 3. 目录结构

```
phpLLP/
├── composer.json
├── phpunit.xml
├── LICENSE
├── README.md
├── src/
│   ├── LLP.php                          # 统一入口主类
│   ├── Contracts/                       # 接口定义
│   │   ├── ChatInterface.php
│   │   ├── ImageInterface.php
│   │   ├── AudioInterface.php
│   │   ├── EmbeddingInterface.php
│   │   ├── VectorStoreInterface.php
│   │   ├── ToolInterface.php
│   │   └── HttpClientInterface.php
│   ├── Chat/                            # 聊天模块
│   │   ├── Message.php
│   │   ├── ChatRole.php                # 常量类(替代enum)
│   │   ├── Model/
│   │   │   ├── OpenAIChatModel.php
│   │   │   ├── AnthropicChatModel.php
│   │   │   ├── MistralChatModel.php
│   │   │   └── OllamaChatModel.php
│   │   ├── Provider/
│   │   │   ├── OpenAIChat.php
│   │   │   ├── AnthropicChat.php
│   │   │   ├── MistralChat.php
│   │   │   └── OllamaChat.php
│   │   └── FunctionCall/
│   │       ├── FunctionInfo.php
│   │       ├── FunctionBuilder.php
│   │       ├── FunctionFormatter.php
│   │       ├── Parameter.php
│   │       ├── ToolCall.php
│   │       └── ToolExecutor.php
│   ├── Image/                           # 图像模块
│   │   ├── Image.php
│   │   ├── Provider/
│   │   │   └── OpenAIImage.php
│   │   └── Enum/
│   │       ├── ImageModel.php
│   │       ├── ImageSize.php
│   │       └── ImageStyle.php
│   ├── Audio/                           # 音频模块
│   │   ├── Transcription.php
│   │   ├── Provider/
│   │   │   └── WhisperAudio.php
│   │   └── AudioModel.php
│   ├── Tools/                           # 工具模块
│   │   ├── ToolManager.php
│   │   ├── ToolResult.php
│   │   └── Builtin/
│   │       ├── WebPageFetcher.php
│   │       ├── HttpRequester.php
│   │       ├── Calculator.php
│   │       └── TextSummarizer.php
│   ├── Embeddings/                      # 嵌入模块
│   │   ├── Document.php
│   │   ├── DocumentUtils.php
│   │   ├── Generator/
│   │   │   ├── EmbeddingGenerator.php
│   │   │   ├── OpenAIEmbeddingGenerator.php
│   │   │   ├── MistralEmbeddingGenerator.php
│   │   │   └── OllamaEmbeddingGenerator.php
│   │   ├── Distances/
│   │   │   ├── Distance.php
│   │   │   ├── CosineDistance.php
│   │   │   └── EuclideanDistance.php
│   │   ├── Splitter/
│   │   │   └── DocumentSplitter.php
│   │   ├── Formatter/
│   │   │   └── EmbeddingFormatter.php
│   │   └── Reader/
│   │       ├── DataReader.php
│   │       ├── FileReader.php
│   │       └── TextReader.php
│   ├── VectorStore/                     # 向量存储
│   │   ├── VectorStoreBase.php          # 抽象基类
│   │   ├── VectorStoreFactory.php       # 工厂类
│   │   ├── FileSystemVectorStore.php
│   │   ├── PostgresVectorStore.php
│   │   ├── QdrantVectorStore.php
│   │   ├── RedisVectorStore.php
│   │   ├── ElasticsearchVectorStore.php
│   │   ├── MilvusVectorStore.php
│   │   ├── ChromaDBVectorStore.php
│   │   ├── AstraDBVectorStore.php
│   │   └── SQLiteVectorStore.php
│   ├── Query/                           # 查询/问答模块
│   │   ├── QuestionAnswering.php
│   │   ├── SemanticSearch.php
│   │   └── Transformer/
│   │       ├── QueryTransformer.php
│   │       └── DocumentsTransformer.php
│   ├── Http/                            # HTTP 客户端(纯 cURL + Generator 流式)
│   │   ├── HttpClient.php
│   │   └── HttpResponse.php
│   ├── Exception/                       # 异常定义
│   │   ├── LLPException.php
│   │   ├── HttpException.php
│   │   ├── ConfigException.php
│   │   └── ModelException.php
│   └── Support/                         # 辅助工具
│       ├── Json.php
│       ├── Str.php
│       └── Arr.php
├── tests/
│   ├── Unit/
│   ├── Integration/
│   └── bootstrap.php
└── examples/
    ├── chat.php
    ├── image.php
    ├── audio.php
    ├── embeddings.php
    ├── vectorstore.php
    └── qa.php
```

---

## 4. PHP 7.4 兼容策略

### 4.1 需避免的语法特性

| PHP 8.0+ 特性 | PHP 7.4 替代方案 |
|---|---|
| `enum` | 使用常量类 + 静态方法 |
| `mixed` 类型声明 | 使用 `@param mixed` PHPDoc，实际类型检查在运行时 |
| `string\|int\|float\|null` 联合类型 | PHPDoc `@param`/`@return`，运行时 `gettype()` 检查 |
| `fn($x) =>` 箭头函数 | 传统闭包 `function($x) use() {}` |
| `match($x)` | `switch` 语句 |
| `str_contains()` | `strpos() !== false` |
| `str_starts_with()` | `strpos() === 0` |
| `str_ends_with()` | `substr() === $needle` |
| `get_debug_type()` | `gettype()` + 类型判断 |
| `named arguments` | 传数组参数 |
| `readonly` | 普通属性 + 仅 getter |
| `final` 类/属性 | 可省略（PHP 7.4 支持 final 类但不支持 final 属性） |
| 空合并运算符 `??` | 正常使用（PHP 7.4 已支持） |
| `function签名` 中的 `?Type` | 正常使用（PHP 7.4 已支持） |
| `\DateTimeImmutable` 类型 | 正常使用 |

### 4.2 需注意的语法

- 不使用 `#[Attribute]`（PHP 8.0+）
- 不使用 `static function` 作为返回类型
- 不使用 `$this` 在静态方法中
- 不使用命名参数调用
- 不使用 `throw` 作为表达式

### 4.3 PHP 版本动态检测与分层策略

> 在运行时检测 PHP 版本，7.4+ 使用兼容实现，8.1+ 自动启用新特性。
> 通过 `PhpVersion` 工具类集中判断，避免各处散落版本判断逻辑。

#### 4.3.1 PhpVersion 工具类

```php
<?php

declare(strict_types=1);

namespace PhpLLP\Support;

/**
 * PHP 版本检测工具
 *
 * 双重检测策略:
 * 1. function_exists / class_exists — 优先使用，即使 PHP 7.4 通过 polyfill
 *    提供了 str_contains 等函数，也能正确识别并使用
 * 2. 版本号推断 (is81Plus / is82Plus) — 用于 enum / readonly 等语言级特性
 *    (这些特性无法通过 function_exists 检测)
 *
 * 用法:
 *   // 函数级特性 (推荐用 function_exists)
 *   PhpVersion::strContains($haystack, $needle);
 *   PhpVersion::supports('str_contains');  // true/false
 *
 *   // 语言级特性 (必须用版本号)
 *   if (PhpVersion::is81Plus()) {
 *       // 使用 enum / readonly / Fiber
 *   }
 */
class PhpVersion
{
    /** @var int */
    private static $major = 0;

    /** @var int */
    private static $minor = 0;

    /** @var bool */
    private static $initialized = false;

    /**
     * 初始化版本号（仅执行一次）
     */
    private static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        $version = PHP_VERSION;
        $parts = explode('.', $version);
        self::$major = (int)($parts[0] ?? 0);
        self::$minor = (int)($parts[1] ?? 0);
        self::$initialized = true;
    }

    /**
     * 获取主版本号
     */
    public static function getMajor(): int
    {
        self::init();
        return self::$major;
    }

    /**
     * 获取次版本号
     */
    public static function getMinor(): int
    {
        self::init();
        return self::$minor;
    }

    /**
     * 是否为 PHP 7.4
     */
    public static function is74(): bool
    {
        self::init();
        return self::$major === 7 && self::$minor === 4;
    }

    /**
     * 是否为 PHP 8.0+
     */
    public static function is80Plus(): bool
    {
        self::init();
        return self::$major >= 8;
    }

    /**
     * 是否为 PHP 8.1+（支持 enum / readonly / Fiber）
     */
    public static function is81Plus(): bool
    {
        self::init();
        return self::$major > 8 || (self::$major === 8 && self::$minor >= 1);
    }

    /**
     * 是否为 PHP 8.2+（支持 readonly class、元信息 Attribute）
     */
    public static function is82Plus(): bool
    {
        self::init();
        return self::$major > 8 || (self::$major === 8 && self::$minor >= 2);
    }

    /**
     * 是否支持指定特性
     *
     * 使用 function_exists / class_exists 检测，比版本号推断更可靠:
     * - 即使 PHP 7.4 通过 polyfill 提供了 str_contains，也能正确识别
     * - 避免版本号碎片化问题（如 8.0.x 某些特性缺失）
     *
     * @param string $feature
     * @return bool
     */
    public static function supports(string $feature): bool
    {
        switch ($feature) {
            case 'str_contains':
                return function_exists('str_contains');
            case 'str_starts_with':
                return function_exists('str_starts_with');
            case 'str_ends_with':
                return function_exists('str_ends_with');
            case 'get_debug_type':
                return function_exists('get_debug_type');
            case 'enum':
                return self::is81Plus();
            case 'readonly':
                return self::is81Plus();
            case 'fiber':
                return class_exists('Fiber');
            case 'readonly_class':
                return self::is82Plus();
            default:
                return false;
        }
    }

    /**
     * 字符串包含判断（自动选择最优实现）
     *
     * 使用 function_exists 检测，兼容 polyfill 场景
     *
     * @param string $haystack
     * @param string $needle
     * @return bool
     */
    public static function strContains(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }

        if (function_exists('str_contains')) {
            return str_contains($haystack, $needle);
        }

        return strpos($haystack, $needle) !== false;
    }

    /**
     * 字符串起始判断（自动选择最优实现）
     *
     * @param string $haystack
     * @param string $needle
     * @return bool
     */
    public static function strStartsWith(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }

        if (function_exists('str_starts_with')) {
            return str_starts_with($haystack, $needle);
        }

        return strpos($haystack, $needle) === 0;
    }

    /**
     * 字符串结束判断（自动选择最优实现）
     *
     * @param string $haystack
     * @param string $needle
     * @return bool
     */
    public static function strEndsWith(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }

        if (function_exists('str_ends_with')) {
            return str_ends_with($haystack, $needle);
        }

        return substr($haystack, -strlen($needle)) === $needle;
    }

    /**
     * 获取调试类型（自动选择最优实现）
     *
     * 使用 function_exists 检测 get_debug_type，兼容 polyfill
     *
     * @param mixed $value
     * @return string
     */
    public static function getDebugType($value): string
    {
        if (function_exists('get_debug_type')) {
            return get_debug_type($value);
        }

        if (is_object($value)) {
            return get_class($value);
        }
        if (is_array($value)) {
            return 'array';
        }
        if (is_string($value)) {
            return 'string';
        }
        if (is_int($value)) {
            return 'int';
        }
        if (is_float($value)) {
            return 'float';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if ($value === null) {
            return 'null';
        }

        return gettype($value);
    }

    /**
     * 版本比较
     *
     * @param string $version 例如 "8.1.0"
     * @param string $operator >, >=, <, <=, ==
     * @return bool
     */
    public static function compare(string $version, string $operator = '>='): bool
    {
        return version_compare(PHP_VERSION, $version, $operator);
    }
}
```

#### 4.3.2 分层兼容策略示例

```php
<?php

namespace PhpLLP\Chat;

use PhpLLP\Support\PhpVersion;

/**
 * ChatRole 实现：7.4 使用常量类，8.1+ 可通过桥接模式升级为 enum
 *
 * 设计思路：
 * - 主实现始终使用常量类（兼容 7.4+）
 * - 8.1+ 环境下可通过工厂方法 PhpVersion::is81Plus() 切换到 enum 版本
 * - 接口层保持一致，上层业务无需修改
 */
class ChatRole
{
    const SYSTEM = 'system';
    const USER = 'user';
    const ASSISTANT = 'assistant';
    const TOOL = 'tool';

    /**
     * @return string[]
     */
    public static function all(): array
    {
        return [self::SYSTEM, self::USER, self::ASSISTANT, self::TOOL];
    }

    /**
     * @param string $role
     * @return bool
     */
    public static function isValid(string $role): bool
    {
        return in_array($role, self::all(), true);
    }

    /**
     * 规范化角色名（8.1+ 下可由 enum 重写）
     *
     * @param string $role
     * @return string
     */
    public static function normalize(string $role): string
    {
        return self::isValid($role) ? $role : self::USER;
    }
}
```

#### 4.3.3 目录结构更新

```
src/
├── Support/
│   ├── PhpVersion.php    # [新增] 版本检测工具
│   ├── Json.php
│   ├── Str.php
│   └── Arr.php
└── ...
```

---

## 5. 核心模块设计

### 5.1 统一入口类 (LLP)

```php
<?php

declare(strict_types=1);

namespace PhpLLP;

use PhpLLP\Contracts\ChatInterface;
use PhpLLP\Contracts\ImageInterface;
use PhpLLP\Contracts\AudioInterface;
use PhpLLP\Contracts\EmbeddingInterface;
use PhpLLP\Contracts\VectorStoreInterface;
use PhpLLP\Contracts\ToolInterface;
use PhpLLP\VectorStore\VectorStoreFactory;
use PhpLLP\Exception\ConfigException;

/**
 * phpLLP 统一入口类
 *
 * 使用方式一：通过方法调用
 *   $llp = new LLP(['provider' => 'openai', 'api_key' => '...']);
 *   $result = $llp->chat('你好');
 *
 * 使用方式二：通过 run() 方法 + 路由参数
 *   $result = $llp->run([
 *       'task' => 'chat',
 *       'messages' => [...]
 *   ]);
 */
class LLP
{
    /** @var array<string, mixed> */
    private array $config;

    private HttpClient $httpClient;

    /** @var array<string, ChatInterface> */
    private array $chatProviders = [];

    /** @var array<string, EmbeddingInterface> */
    private array $embeddingProviders = [];

    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->httpClient = new HttpClient();
    }

    /**
     * 默认配置
     *
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
        ];
    }

    /**
     * 统一入口：根据 task 参数执行不同功能
     *
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

    /**
     * Chat 功能
     *
     * @param string $prompt
     * @param array<string, mixed> $options
     * @return string
     */
    public function chat(string $prompt, array $options = []): string
    {
        $chat = $this->getChatProvider();

        return $chat->generateText($prompt, $options);
    }

    /**
     * 多轮对话
     *
     * @param array<int, array{role: string, content: string}> $messages
     * @param array<string, mixed> $options
     * @return string
     */
    public function conversation(array $messages, array $options = []): string
    {
        $chat = $this->getChatProvider();

        return $chat->generateChat($messages, $options);
    }

    /**
     * 流式对话
     *
     * @param string $prompt
     * @param array<string, mixed> $options
     * @return \Generator
     */
    public function chatStream(string $prompt, array $options = []): \Generator
    {
        $chat = $this->getChatProvider();

        return $chat->generateStream($prompt, $options);
    }

    /**
     * 工具调用
     *
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
     * 图像生成
     *
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
     * 语音转文字
     *
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
     * 文本嵌入
     *
     * @param string $text
     * @param array<string, mixed> $options
     * @return array<int, float>
     */
    public function embed(string $text, array $options = []): array
    {
        $generator = $this->getEmbeddingProvider();

        return $generator->embedText($text, $options);
    }

    /**
     * 问答 (RAG)
     *
     * @param string $question
     * @param array<string, mixed> $options
     * @return string
     */
    public function ask(string $question, array $options = []): string
    {
        $qa = new QuestionAnswering(
            $this->getVectorStore($options['vector_store'] ?? 'filesystem'),
            $this->getEmbeddingProvider(),
            $this->getChatProvider()
        );

        return $qa->answer($question, $options);
    }

    /**
     * 获取 Chat Provider
     */
    private function getChatProvider(): ChatInterface
    {
        $provider = $this->config['provider'];

        if (!isset($this->chatProviders[$provider])) {
            $this->chatProviders[$provider] = $this->createChatProvider($provider);
        }

        return $this->chatProviders[$provider];
    }

    /**
     * 创建 Chat Provider
     */
    private function createChatProvider(string $provider): ChatInterface
    {
        $config = $this->config;

        switch ($provider) {
            case 'openai':
                return new Chat\Provider\OpenAIChat($config, $this->httpClient);
            case 'anthropic':
                return new Chat\Provider\AnthropicChat($config, $this->httpClient);
            case 'mistral':
                return new Chat\Provider\MistralChat($config, $this->httpClient);
            case 'ollama':
                return new Chat\Provider\OllamaChat($config, $this->httpClient);
            default:
                throw new ConfigException("不支持的 Provider: {$provider}");
        }
    }

    /**
     * 获取 Embedding Provider
     */
    private function getEmbeddingProvider(): EmbeddingInterface
    {
        $provider = $this->config['provider'];

        if (!isset($this->embeddingProviders[$provider])) {
            $this->embeddingProviders[$provider] = $this->createEmbeddingProvider($provider);
        }

        return $this->embeddingProviders[$provider];
    }

    /**
     * 创建 Embedding Provider
     */
    private function createEmbeddingProvider(string $provider): EmbeddingInterface
    {
        switch ($provider) {
            case 'openai':
                return new Embeddings\Generator\OpenAIEmbeddingGenerator($this->config, $this->httpClient);
            case 'mistral':
                return new Embeddings\Generator\MistralEmbeddingGenerator($this->config, $this->httpClient);
            case 'ollama':
                return new Embeddings\Generator\OllamaEmbeddingGenerator($this->config, $this->httpClient);
            default:
                throw new ConfigException("不支持的 Embedding Provider: {$provider}");
        }
    }

    /**
     * 获取 VectorStore
     */
    private function getVectorStore(string $type): VectorStoreInterface
    {
        return VectorStoreFactory::create($type, $this->config);
    }

    /**
     * 设置配置
     *
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
     * 获取配置
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getConfig(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * 获取 ToolManager 实例
     *
     * @return Tools\ToolManager
     */
    public function getToolManager(): Tools\ToolManager
    {
        static $manager = null;
        if ($manager === null) {
            $manager = new Tools\ToolManager();
        }
        return $manager;
    }

    /**
     * 注册工具
     *
     * @param Contracts\ToolInterface|Chat\FunctionCall\FunctionInfo $tool
     * @return self
     */
    public function registerTool($tool): self
    {
        $this->getToolManager()->register($tool);
        return $this;
    }

    /**
     * run() 处理器 - Chat
     *
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
     * run() 处理器 - Image
     *
     * @param array<string, mixed> $params
     * @return array
     */
    private function handleImage(array $params): array
    {
        return $this->image($params['prompt'] ?? '', $params['options'] ?? []);
    }

    /**
     * run() 处理器 - Audio
     *
     * @param array<string, mixed> $params
     * @return array
     */
    private function handleAudio(array $params): array
    {
        return $this->transcribe($params['file_path'] ?? '', $params['options'] ?? []);
    }

    /**
     * run() 处理器 - Embedding
     *
     * @param array<string, mixed> $params
     * @return array<int, float>
     */
    private function handleEmbedding(array $params): array
    {
        return $this->embed($params['text'] ?? '', $params['options'] ?? []);
    }

    /**
     * run() 处理器 - Question Answering
     *
     * @param array<string, mixed> $params
     * @return string
     */
    private function handleQuestionAnswering(array $params): string
    {
        return $this->ask($params['question'] ?? '', $params);
    }
}
```

### 5.2 HTTP 客户端

> 所有 HTTP 请求通过原生 cURL 实现，不依赖 Guzzle 等第三方库。

```php
<?php

declare(strict_types=1);

namespace PhpLLP\Http;

use PhpLLP\Exception\HttpException;

class HttpClient
{
    /** @var int */
    private $timeout;

    /** @var string|null */
    private $proxy;

    /** @var array<string, string> */
    private $defaultHeaders;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $this->timeout = $config['timeout'] ?? 30;
        $this->proxy = $config['proxy'] ?? null;
        $this->defaultHeaders = $config['headers'] ?? [];
    }

    /**
     * 发送同步 GET 请求
     *
     * @param string $url
     * @param array<string, string> $headers
     * @return HttpResponse
     */
    public function get(string $url, array $headers = []): HttpResponse
    {
        return $this->request('GET', $url, $headers);
    }

    /**
     * 发送同步 POST 请求
     *
     * @param string $url
     * @param array<string, string> $headers
     * @param mixed $body
     * @return HttpResponse
     */
    public function post(string $url, array $headers = [], $body = null): HttpResponse
    {
        return $this->request('POST', $url, $headers, $body);
    }

    /**
     * 发送流式请求（真正的流式响应，基于 Generator）
     * 用于 SSE / Stream 协议，逐行 yield 数据
     *
     * PHP 7.4+ 兼容说明:
     * - 使用 Generator 作为迭代器，消费端通过 foreach 逐行处理
     * - 内部基于 cURL WRITEFUNCTION 回调，按行分割 SSE 数据
     * - 消费端接口与真正的流式 API 完全一致
     *
     * @param string $method
     * @param string $url
     * @param array<string, string> $headers
     * @param mixed $body
     * @return \Generator 逐行 yield SSE 事件字符串
     */
    public function stream(string $method, string $url, array $headers = [], $body = null): \Generator
    {
        return $this->requestStream($method, $url, $headers, $body);
    }

    /**
     * 发送 PUT 请求
     *
     * @param string $url
     * @param array<string, string> $headers
     * @param mixed $body
     * @return HttpResponse
     */
    public function put(string $url, array $headers = [], $body = null): HttpResponse
    {
        return $this->request('PUT', $url, $headers, $body);
    }

    /**
     * 发送 DELETE 请求
     *
     * @param string $url
     * @param array<string, string> $headers
     * @param mixed $body
     * @return HttpResponse
     */
    public function delete(string $url, array $headers = [], $body = null): HttpResponse
    {
        return $this->request('DELETE', $url, $headers, $body);
    }

    /**
     * 通用请求方法
     *
     * @param string $method
     * @param string $url
     * @param array<string, string> $headers
     * @param mixed $body
     * @return HttpResponse
     * @throws HttpException
     */
    public function request(string $method, string $url, array $headers = [], $body = null): HttpResponse
    {
        $ch = curl_init();

        $mergedHeaders = array_merge($this->defaultHeaders, $headers);
        $formattedHeaders = [];
        foreach ($mergedHeaders as $key => $value) {
            $formattedHeaders[] = $key . ': ' . $value;
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => $formattedHeaders,
            CURLOPT_CUSTOMREQUEST => $method,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($body) ? json_encode($body) : $body);
        }

        if ($this->proxy !== null) {
            curl_setopt($ch, CURLOPT_PROXY, $this->proxy);
        }

        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $headersSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $responseHeaders = substr($responseBody, 0, $headersSize);
        $bodyContent = substr($responseBody, $headersSize);

        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new HttpException("HTTP请求失败: [{$httpCode}] " . ($error ?: $bodyContent), $httpCode);
        }

        return new HttpResponse($httpCode, $bodyContent, $responseHeaders);
    }

    /**
     * 真正的流式请求 - 基于 Generator 逐行 yield SSE 数据
     *
     * 实现原理:
     * 1. 使用 CURLOPT_WRITEFUNCTION 回调接收原始数据块
     * 2. 内部缓冲区按行分割（SSE 协议以 \n 分隔事件）
     * 3. 请求完成后通过 Generator 按行 yield 完整事件
     * 4. 消费端通过 foreach 逐行处理，避免一次性内存占用
     *
     * PHP 7.4+ 兼容:
     * - Generator 原生支持（PHP 5.5+）
     * - cURL WRITEFUNCTION 回调原生支持
     * - 不依赖 Fiber / 协程等 PHP 8.1+ 特性
     *
     * @param string $method
     * @param string $url
     * @param array<string, string> $headers
     * @param mixed $body
     * @return \Generator
     * @throws HttpException
     */
    private function requestStream(string $method, string $url, array $headers = [], $body = null): \Generator
    {
        $ch = curl_init();

        $mergedHeaders = array_merge($this->defaultHeaders, $headers);
        $formattedHeaders = [];
        foreach ($mergedHeaders as $key => $value) {
            $formattedHeaders[] = $key . ': ' . $value;
        }

        $lines = [];
        $lineBuffer = '';

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_HTTPHEADER => $formattedHeaders,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_WRITEFUNCTION => function ($ch, $chunk) use (&$lines, &$lineBuffer) {
                $lineBuffer .= $chunk;
                $parts = explode("\n", $lineBuffer);
                $lineBuffer = array_pop($parts);

                foreach ($parts as $part) {
                    $part = rtrim($part, "\r");
                    if ($part !== '') {
                        $lines[] = $part;
                    }
                }

                return strlen($chunk);
            },
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($body) ? json_encode($body) : $body);
        }

        if ($this->proxy !== null) {
            curl_setopt($ch, CURLOPT_PROXY, $this->proxy);
        }

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($lineBuffer !== '') {
            $line = rtrim($lineBuffer, "\r");
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new HttpException("HTTP流式请求失败: [{$httpCode}] " . ($error ?: ''), $httpCode);
        }

        foreach ($lines as $line) {
            yield $line;
        }
    }
}
```

### 5.3 Chat 模块

#### 5.3.1 消息模型

```php
<?php

declare(strict_types=1);

namespace PhpLLP\Chat;

/**
 * 消息角色常量类（替代 PHP 8.1 enum）
 */
class ChatRole
{
    const SYSTEM = 'system';
    const USER = 'user';
    const ASSISTANT = 'assistant';
    const TOOL = 'tool';

    /**
     * @return string[]
     */
    public static function all(): array
    {
        return [self::SYSTEM, self::USER, self::ASSISTANT, self::TOOL];
    }

    /**
     * @param string $role
     * @return bool
     */
    public static function isValid(string $role): bool
    {
        return in_array($role, self::all(), true);
    }
}
```

```php
<?php

declare(strict_types=1);

namespace PhpLLP\Chat;

class Message
{
    /** @var string */
    public $role;

    /** @var string */
    public $content;

    /** @var string|null */
    public $toolCallId;

    /** @var string|null */
    public $name;

    /** @var array<int, array<string, mixed>> */
    public $toolCalls = [];

    /**
     * @param string $role
     * @param string $content
     * @param string|null $toolCallId
     * @param string|null $name
     * @param array<int, array<string, mixed>> $toolCalls
     */
    public function __construct(
        string $role = '',
        string $content = '',
        ?string $toolCallId = null,
        ?string $name = null,
        array $toolCalls = []
    ) {
        $this->role = $role;
        $this->content = $content;
        $this->toolCallId = $toolCallId;
        $this->name = $name;
        $this->toolCalls = $toolCalls;
    }

    /**
     * 创建系统消息
     *
     * @param string $content
     * @return self
     */
    public static function system(string $content): self
    {
        return new self(ChatRole::SYSTEM, $content);
    }

    /**
     * 创建用户消息
     *
     * @param string $content
     * @return self
     */
    public static function user(string $content): self
    {
        return new self(ChatRole::USER, $content);
    }

    /**
     * 创建助手消息
     *
     * @param string $content
     * @return self
     */
    public static function assistant(string $content): self
    {
        return new self(ChatRole::ASSISTANT, $content);
    }

    /**
     * 创建工具调用结果消息
     *
     * @param string $content
     * @param string|null $toolCallId
     * @return self
     */
    public static function toolResult(string $content, ?string $toolCallId = null): self
    {
        return new self(ChatRole::TOOL, $content, $toolCallId);
    }

    /**
     * 转换为数组
     *
     * @return array<string, mixed>
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

        if ($this->name !== null) {
            $result['name'] = $this->name;
        }

        if (!empty($this->toolCalls)) {
            $result['tool_calls'] = $this->toolCalls;
        }

        return $result;
    }

    /**
     * 从数组创建
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['role'] ?? '',
            $data['content'] ?? '',
            $data['tool_call_id'] ?? null,
            $data['name'] ?? null,
            $data['tool_calls'] ?? []
        );
    }
}
```

#### 5.3.2 Chat 接口

```php
<?php

declare(strict_types=1);

namespace PhpLLP\Contracts;

use PhpLLP\Chat\Message;

interface ChatInterface
{
    /**
     * 根据提示生成文本
     *
     * @param string $prompt
     * @param array<string, mixed> $options
     * @return string
     */
    public function generateText(string $prompt, array $options = []): string;

    /**
     * 根据消息列表生成回复
     *
     * @param array<int, array<string, mixed>> $messages
     * @param array<string, mixed> $options
     * @return string
     */
    public function generateChat(array $messages, array $options = []): string;

    /**
     * 流式生成文本
     *
     * @param string $prompt
     * @param array<string, mixed> $options
     * @return \Generator
     */
    public function generateStream(string $prompt, array $options = []): \Generator;

    /**
     * 流式生成对话
     *
     * @param array<int, array<string, mixed>> $messages
     * @param array<string, mixed> $options
     * @return \Generator
     */
    public function generateChatStream(array $messages, array $options = []): \Generator;

    /**
     * 设置系统消息
     *
     * @param string $systemMessage
     * @return void
     */
    public function setSystemMessage(string $systemMessage): void;

    /**
     * 设置温度参数
     *
     * @param float $temperature
     * @return void
     */
    public function setTemperature(float $temperature): void;

    /**
     * 设置最大 token 数
     *
     * @param int $maxTokens
     * @return void
     */
    public function setMaxTokens(int $maxTokens): void;

    /**
     * 获取总 token 使用量
     *
     * @return int
     */
    public function getTotalTokens(): int;
}
```

#### 5.3.3 Provider 设计

每个 AI Provider 实现 `ChatInterface`，内部使用 `HttpClient` 发送请求。以下以 OpenAI 为例：

```php
<?php

declare(strict_types=1);

namespace PhpLLP\Chat\Provider;

use PhpLLP\Chat\ChatRole;
use PhpLLP\Chat\Message;
use PhpLLP\Contracts\ChatInterface;
use PhpLLP\Http\HttpClient;
use PhpLLP\Support\Json;
use PhpLLP\Exception\HttpException;

class OpenAIChat implements ChatInterface
{
    /** @var array<string, mixed> */
    private $config;

    /** @var HttpClient */
    private $httpClient;

    /** @var string */
    private $apiKey;

    /** @var string */
    private $baseUrl;

    /** @var string */
    private $model;

    /** @var string */
    private $systemMessage = '';

    /** @var int */
    private $totalTokens = 0;

    /**
     * @param array<string, mixed> $config
     * @param HttpClient $httpClient
     */
    public function __construct(array $config, HttpClient $httpClient)
    {
        $this->config = $config;
        $this->httpClient = $httpClient;
        $this->apiKey = $config['api_key'] ?? '';
        $this->baseUrl = rtrim($config['base_url'] ?? 'https://api.openai.com/v1', '/');
        $this->model = $config['model'] ?? 'gpt-4o';
    }

    public function generateText(string $prompt, array $options = []): string
    {
        return $this->generateChat([Message::user($prompt)->toArray()], $options);
    }

    public function generateChat(array $messages, array $options = []): string
    {
        $allMessages = $this->buildMessages($messages);
        $payload = $this->buildPayload($allMessages, $options);

        $response = $this->httpClient->post(
            $this->baseUrl . '/chat/completions',
            $this->getDefaultHeaders(),
            $payload
        );

        $data = Json::decode($response->getBody());

        $this->totalTokens += $data['usage']['total_tokens'] ?? 0;

        return $data['choices'][0]['message']['content'] ?? '';
    }

    public function generateStream(string $prompt, array $options = []): \Generator
    {
        $messages = [Message::user($prompt)->toArray()];

        return $this->generateChatStream($messages, $options);
    }

    public function generateChatStream(array $messages, array $options = []): \Generator
    {
        $allMessages = $this->buildMessages($messages);
        $payload = $this->buildPayload($allMessages, $options);
        $payload['stream'] = true;

        $stream = $this->httpClient->stream(
            'POST',
            $this->baseUrl . '/chat/completions',
            $this->getDefaultHeaders(),
            $payload
        );

        foreach ($stream as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, 'data: ') !== 0) {
                continue;
            }

            $dataStr = substr($line, 6);
            if ($dataStr === '[DONE]') {
                break;
            }

            $data = Json::decode($dataStr);
            $delta = $data['choices'][0]['delta']['content'] ?? '';
            if ($delta !== '') {
                yield $delta;
            }
        }
    }

    public function setSystemMessage(string $systemMessage): void
    {
        $this->systemMessage = $systemMessage;
    }

    public function setTemperature(float $temperature): void
    {
        $this->config['temperature'] = $temperature;
    }

    public function setMaxTokens(int $maxTokens): void
    {
        $this->config['max_tokens'] = $maxTokens;
    }

    public function getTotalTokens(): int
    {
        return $this->totalTokens;
    }

    /**
     * 构建消息数组（加入系统消息）
     *
     * @param array<int, array<string, mixed>> $messages
     * @return array<int, array<string, mixed>>
     */
    private function buildMessages(array $messages): array
    {
        $allMessages = [];

        if ($this->systemMessage !== '') {
            $allMessages[] = Message::system($this->systemMessage)->toArray();
        }

        foreach ($messages as $msg) {
            if (is_array($msg)) {
                $allMessages[] = $msg;
            } elseif ($msg instanceof Message) {
                $allMessages[] = $msg->toArray();
            }
        }

        return $allMessages;
    }

    /**
     * 构建请求 Payload
     *
     * @param array<int, array<string, mixed>> $messages
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function buildPayload(array $messages, array $options = []): array
    {
        $payload = [
            'model' => $options['model'] ?? $this->model,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? ($this->config['temperature'] ?? 0.7),
            'max_tokens' => $options['max_tokens'] ?? ($this->config['max_tokens'] ?? 1024),
        ];

        if (isset($options['tools'])) {
            $payload['tools'] = $options['tools'];
        }

        if (isset($options['response_format'])) {
            $payload['response_format'] = $options['response_format'];
        }

        if (isset($options['stream'])) {
            $payload['stream'] = $options['stream'];
        }

        return $payload;
    }

    /**
     * 获取默认请求头
     *
     * @return array<string, string>
     */
    private function getDefaultHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ];
    }
}
```

#### 5.3.4 Anthropic Chat Provider

```php
<?php

declare(strict_types=1);

namespace PhpLLP\Chat\Provider;

use PhpLLP\Chat\ChatRole;
use PhpLLP\Chat\Message;
use PhpLLP\Contracts\ChatInterface;
use PhpLLP\Http\HttpClient;
use PhpLLP\Support\Json;
use PhpLLP\Exception\HttpException;

class AnthropicChat implements ChatInterface
{
    /** @var array<string, mixed> */
    private $config;

    /** @var HttpClient */
    private $httpClient;

    /** @var string */
    private $apiKey;

    /** @var string */
    private $baseUrl;

    /** @var string */
    private $model;

    /** @var string */
    private $systemMessage = '';

    /** @var int */
    private $totalTokens = 0;

    /**
     * @param array<string, mixed> $config
     * @param HttpClient $httpClient
     */
    public function __construct(array $config, HttpClient $httpClient)
    {
        $this->config = $config;
        $this->httpClient = $httpClient;
        $this->apiKey = $config['api_key'] ?? '';
        $this->baseUrl = rtrim($config['base_url'] ?? 'https://api.anthropic.com', '/');
        $this->model = $config['model'] ?? 'claude-3-opus-20240229';
    }

    public function generateText(string $prompt, array $options = []): string
    {
        return $this->generateChat([Message::user($prompt)->toArray()], $options);
    }

    public function generateChat(array $messages, array $options = []): string
    {
        $payload = $this->buildPayload($messages, $options);

        $response = $this->httpClient->post(
            $this->baseUrl . '/v1/messages',
            $this->getDefaultHeaders(),
            $payload
        );

        $data = Json::decode($response->getBody());

        $this->totalTokens += ($data['usage']['input_tokens'] ?? 0) + ($data['usage']['output_tokens'] ?? 0);

        return $data['content'][0]['text'] ?? '';
    }

    public function generateStream(string $prompt, array $options = []): \Generator
    {
        $messages = [Message::user($prompt)->toArray()];
        return $this->generateChatStream($messages, $options);
    }

    public function generateChatStream(array $messages, array $options = []): \Generator
    {
        $payload = $this->buildPayload($messages, $options);
        $payload['stream'] = true;

        $stream = $this->httpClient->stream(
            'POST',
            $this->baseUrl . '/v1/messages',
            $this->getDefaultHeaders(),
            $payload
        );

        $text = '';

        foreach ($stream as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, 'data: ') !== 0) {
                continue;
            }

            $dataStr = substr($line, 6);
            if ($dataStr === '[DONE]') {
                break;
            }

            $data = Json::decode($dataStr);
            if (isset($data['type']) && $data['type'] === 'content_block_delta') {
                $delta = $data['delta']['text'] ?? '';
                if ($delta !== '') {
                    $text .= $delta;
                    yield $delta;
                }
            }
        }
    }

    public function setSystemMessage(string $systemMessage): void
    {
        $this->systemMessage = $systemMessage;
    }

    public function setTemperature(float $temperature): void
    {
        $this->config['temperature'] = $temperature;
    }

    public function setMaxTokens(int $maxTokens): void
    {
        $this->config['max_tokens'] = $maxTokens;
    }

    public function getTotalTokens(): int
    {
        return $this->totalTokens;
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function buildPayload(array $messages, array $options = []): array
    {
        $payload = [
            'model' => $options['model'] ?? $this->model,
            'messages' => $this->convertMessages($messages),
            'max_tokens' => $options['max_tokens'] ?? ($this->config['max_tokens'] ?? 1024),
            'temperature' => $options['temperature'] ?? ($this->config['temperature'] ?? 0.7),
        ];

        if ($this->systemMessage !== '') {
            $payload['system'] = $this->systemMessage;
        }

        if (isset($options['tools'])) {
            $payload['tools'] = $options['tools'];
        }

        return $payload;
    }

    /**
     * 转换消息格式为 Anthropic 格式
     *
     * @param array<int, array<string, mixed>> $messages
     * @return array<int, array<string, mixed>>
     */
    private function convertMessages(array $messages): array
    {
        $result = [];
        foreach ($messages as $msg) {
            if (isset($msg['role']) && $msg['role'] === ChatRole::SYSTEM) {
                $this->systemMessage = $msg['content'];
                continue;
            }
            $result[] = [
                'role' => $msg['role'],
                'content' => $msg['content'],
            ];
        }
        return $result;
    }

    /**
     * @return array<string, string>
     */
    private function getDefaultHeaders(): array
    {
        return [
            'x-api-key' => $this->apiKey,
            'Content-Type' => 'application/json',
            'anthropic-version' => '2024-02-29',
        ];
    }
}
```

#### 5.3.5 MistralAI Chat Provider

```php
<?php

declare(strict_types=1);

namespace PhpLLP\Chat\Provider;

use PhpLLP\Chat\ChatRole;
use PhpLLP\Chat\Message;
use PhpLLP\Contracts\ChatInterface;
use PhpLLP\Http\HttpClient;
use PhpLLP\Support\Json;

class MistralChat implements ChatInterface
{
    /** @var array<string, mixed> */
    private $config;

    /** @var HttpClient */
    private $httpClient;

    /** @var string */
    private $apiKey;

    /** @var string */
    private $baseUrl;

    /** @var string */
    private $model;

    /** @var string */
    private $systemMessage = '';

    /** @var int */
    private $totalTokens = 0;

    /**
     * @param array<string, mixed> $config
     * @param HttpClient $httpClient
     */
    public function __construct(array $config, HttpClient $httpClient)
    {
        $this->config = $config;
        $this->httpClient = $httpClient;
        $this->apiKey = $config['api_key'] ?? '';
        $this->baseUrl = rtrim($config['base_url'] ?? 'https://api.mistral.ai/v1', '/');
        $this->model = $config['model'] ?? 'mistral-large-latest';
    }

    public function generateText(string $prompt, array $options = []): string
    {
        return $this->generateChat([Message::user($prompt)->toArray()], $options);
    }

    public function generateChat(array $messages, array $options = []): string
    {
        $allMessages = $this->buildMessages($messages);
        $payload = $this->buildPayload($allMessages, $options);

        $response = $this->httpClient->post(
            $this->baseUrl . '/chat/completions',
            $this->getDefaultHeaders(),
            $payload
        );

        $data = Json::decode($response->getBody());
        $this->totalTokens += $data['usage']['total_tokens'] ?? 0;

        return $data['choices'][0]['message']['content'] ?? '';
    }

    public function generateStream(string $prompt, array $options = []): \Generator
    {
        $messages = [Message::user($prompt)->toArray()];
        return $this->generateChatStream($messages, $options);
    }

    public function generateChatStream(array $messages, array $options = []): \Generator
    {
        $allMessages = $this->buildMessages($messages);
        $payload = $this->buildPayload($allMessages, $options);
        $payload['stream'] = true;

        $stream = $this->httpClient->stream(
            'POST',
            $this->baseUrl . '/chat/completions',
            $this->getDefaultHeaders(),
            $payload
        );

        foreach ($stream as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, 'data: ') !== 0) {
                continue;
            }

            $dataStr = substr($line, 6);
            if ($dataStr === '[DONE]') {
                break;
            }

            $data = Json::decode($dataStr);
            $delta = $data['choices'][0]['delta']['content'] ?? '';
            if ($delta !== '') {
                yield $delta;
            }
        }
    }

    public function setSystemMessage(string $systemMessage): void
    {
        $this->systemMessage = $systemMessage;
    }

    public function setTemperature(float $temperature): void
    {
        $this->config['temperature'] = $temperature;
    }

    public function setMaxTokens(int $maxTokens): void
    {
        $this->config['max_tokens'] = $maxTokens;
    }

    public function getTotalTokens(): int
    {
        return $this->totalTokens;
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     * @return array<int, array<string, mixed>>
     */
    private function buildMessages(array $messages): array
    {
        $allMessages = [];
        if ($this->systemMessage !== '') {
            $allMessages[] = Message::system($this->systemMessage)->toArray();
        }
        foreach ($messages as $msg) {
            $allMessages[] = is_array($msg) ? $msg : $msg->toArray();
        }
        return $allMessages;
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function buildPayload(array $messages, array $options = []): array
    {
        $payload = [
            'model' => $options['model'] ?? $this->model,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? ($this->config['temperature'] ?? 0.7),
            'max_tokens' => $options['max_tokens'] ?? ($this->config['max_tokens'] ?? 1024),
        ];

        if (isset($options['tools'])) {
            $payload['tools'] = $options['tools'];
        }

        return $payload;
    }

    /**
     * @return array<string, string>
     */
    private function getDefaultHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ];
    }
}
```

#### 5.3.6 Ollama Chat Provider

```php
<?php

declare(strict_types=1);

namespace PhpLLP\Chat\Provider;

use PhpLLP\Chat\ChatRole;
use PhpLLP\Chat\Message;
use PhpLLP\Contracts\ChatInterface;
use PhpLLP\Http\HttpClient;
use PhpLLP\Support\Json;

class OllamaChat implements ChatInterface
{
    /** @var array<string, mixed> */
    private $config;

    /** @var HttpClient */
    private $httpClient;

    /** @var string */
    private $baseUrl;

    /** @var string */
    private $model;

    /** @var string */
    private $systemMessage = '';

    /** @var int */
    private $totalTokens = 0;

    /**
     * @param array<string, mixed> $config
     * @param HttpClient $httpClient
     */
    public function __construct(array $config, HttpClient $httpClient)
    {
        $this->config = $config;
        $this->httpClient = $httpClient;
        $this->baseUrl = rtrim($config['base_url'] ?? 'http://localhost:11434', '/');
        $this->model = $config['model'] ?? 'llama3';
    }

    public function generateText(string $prompt, array $options = []): string
    {
        return $this->generateChat([Message::user($prompt)->toArray()], $options);
    }

    public function generateChat(array $messages, array $options = []): string
    {
        $allMessages = $this->buildMessages($messages);
        $payload = [
            'model' => $options['model'] ?? $this->model,
            'messages' => $allMessages,
            'stream' => false,
            'options' => [
                'temperature' => $options['temperature'] ?? ($this->config['temperature'] ?? 0.7),
                'num_predict' => $options['max_tokens'] ?? ($this->config['max_tokens'] ?? 1024),
            ],
        ];

        $response = $this->httpClient->post(
            $this->baseUrl . '/api/chat',
            ['Content-Type' => 'application/json'],
            $payload
        );

        $data = Json::decode($response->getBody());
        $this->totalTokens = $data['eval_count'] ?? 0;

        return $data['message']['content'] ?? '';
    }

    public function generateStream(string $prompt, array $options = []): \Generator
    {
        $messages = [Message::user($prompt)->toArray()];
        return $this->generateChatStream($messages, $options);
    }

    public function generateChatStream(array $messages, array $options = []): \Generator
    {
        $allMessages = $this->buildMessages($messages);
        $payload = [
            'model' => $options['model'] ?? $this->model,
            'messages' => $allMessages,
            'stream' => true,
            'options' => [
                'temperature' => $options['temperature'] ?? ($this->config['temperature'] ?? 0.7),
                'num_predict' => $options['max_tokens'] ?? ($this->config['max_tokens'] ?? 1024),
            ],
        ];

        $stream = $this->httpClient->stream(
            'POST',
            $this->baseUrl . '/api/chat',
            ['Content-Type' => 'application/json'],
            $payload
        );

        foreach ($stream as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $data = Json::decode($line);
            if (isset($data['done']) && $data['done']) {
                break;
            }

            $delta = $data['message']['content'] ?? '';
            if ($delta !== '') {
                yield $delta;
            }
        }
    }

    public function setSystemMessage(string $systemMessage): void
    {
        $this->systemMessage = $systemMessage;
    }

    public function setTemperature(float $temperature): void
    {
        $this->config['temperature'] = $temperature;
    }

    public function setMaxTokens(int $maxTokens): void
    {
        $this->config['max_tokens'] = $maxTokens;
    }

    public function getTotalTokens(): int
    {
        return $this->totalTokens;
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     * @return array<int, array<string, mixed>>
     */
    private function buildMessages(array $messages): array
    {
        $allMessages = [];
        if ($this->systemMessage !== '') {
            $allMessages[] = Message::system($this->systemMessage)->toArray();
        }
        foreach ($messages as $msg) {
            $allMessages[] = is_array($msg) ? $msg : $msg->toArray();
        }
        return $allMessages;
    }
}
```

### 5.4 图像模块

```php
<?php

declare(strict_types=1);

namespace PhpLLP\Contracts;

interface ImageInterface
{
    /**
     * 根据描述生成图像
     *
     * @param string $prompt
     * @param array<string, mixed> $options
     * @return array{url?: string, base64?: string, revised_prompt?: string}
     */
    public function generate(string $prompt, array $options = []): array;
}
```

图像生成 Provider 实现（DALL-E 3 接口）：

```php
<?php

declare(strict_types=1);

namespace PhpLLP\Image\Provider;

use PhpLLP\Contracts\ImageInterface;
use PhpLLP\Http\HttpClient;
use PhpLLP\Support\Json;

class OpenAIImage implements ImageInterface
{
    /** @var array<string, mixed> */
    private $config;

    /** @var HttpClient */
    private $httpClient;

    /** @var string */
    private $apiKey;

    /** @var string */
    private $baseUrl;

    /** @var string */
    private $model;

    /**
     * @param array<string, mixed> $config
     * @param HttpClient $httpClient
     */
    public function __construct(array $config, HttpClient $httpClient)
    {
        $this->config = $config;
        $this->httpClient = $httpClient;
        $this->apiKey = $config['api_key'] ?? '';
        $this->baseUrl = rtrim($config['base_url'] ?? 'https://api.openai.com/v1', '/');
        $this->model = $config['image_model'] ?? 'dall-e-3';
    }

    public function generate(string $prompt, array $options = []): array
    {
        $payload = [
            'model' => $options['model'] ?? $this->model,
            'prompt' => $prompt,
            'n' => 1,
            'size' => $options['size'] ?? '1024x1024',
            'style' => $options['style'] ?? 'vivid',
            'response_format' => $options['response_format'] ?? 'url',
        ];

        $response = $this->httpClient->post(
            $this->baseUrl . '/images/generations',
            [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            $payload
        );

        $data = Json::decode($response->getBody());

        return $data['data'][0] ?? [];
    }
}
```

### 5.5 音频模块

```php
<?php

declare(strict_types=1);

namespace PhpLLP\Contracts;

interface AudioInterface
{
    /**
     * 语音转文字
     *
     * @param string $filePath 音频文件路径
     * @param array<string, mixed> $options
     * @return array{text: string, language: string|null, duration: float|null}
     */
    public function transcribe(string $filePath, array $options = []): array;
}
```

Whisper 实现：

```php
<?php

declare(strict_types=1);

namespace PhpLLP\Audio\Provider;

use PhpLLP\Contracts\AudioInterface;
use PhpLLP\Http\HttpClient;
use PhpLLP\Support\Json;

class WhisperAudio implements AudioInterface
{
    /** @var array<string, mixed> */
    private $config;

    /** @var HttpClient */
    private $httpClient;

    /** @var string */
    private $apiKey;

    /** @var string */
    private $baseUrl;

    /** @var string */
    private $model;

    /**
     * @param array<string, mixed> $config
     * @param HttpClient $httpClient
     */
    public function __construct(array $config, HttpClient $httpClient)
    {
        $this->config = $config;
        $this->httpClient = $httpClient;
        $this->apiKey = $config['api_key'] ?? '';
        $this->baseUrl = rtrim($config['base_url'] ?? 'https://api.openai.com/v1', '/');
        $this->model = $config['audio_model'] ?? 'whisper-1';
    }

    public function transcribe(string $filePath, array $options = []): array
    {
        $fileContent = file_get_contents($filePath);
        $fileName = basename($filePath);
        $mimeType = mime_content_type($filePath) ?: 'audio/mpeg';

        $boundary = '----PhpLLP' . uniqid();

        $body = "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"model\"\r\n\r\n";
        $body .= $options['model'] ?? $this->model . "\r\n";

        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"file\"; filename=\"{$fileName}\"\r\n";
        $body .= "Content-Type: {$mimeType}\r\n\r\n";
        $body .= $fileContent . "\r\n";

        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"response_format\"\r\n\r\n";
        $body .= "verbose_json\r\n";
        $body .= "--{$boundary}--\r\n";

        $response = $this->httpClient->post(
            $this->baseUrl . '/audio/transcriptions',
            [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
            ],
            $body
        );

        $data = Json::decode($response->getBody());

        return [
            'text' => $data['text'] ?? '',
            'language' => $data['language'] ?? null,
            'duration' => $data['duration'] ?? null,
        ];
    }
}
```

### 5.6 Tools 模块

#### 5.6.1 FunctionInfo（工具元数据）

```php
<?php

declare(strict_types=1);

namespace PhpLLP\Chat\FunctionCall;

class ParameterInfo
{
    /** @var string */
    public $name;

    /** @var string */
    public $type;

    /** @var string */
    public $description;

    /** @var array<string, mixed> */
    public $properties = [];

    /** @var bool */
    public $required = false;

    /**
     * @param string $name
     * @param string $type
     * @param string $description
     * @param array<string, mixed> $properties
     * @param bool $required
     */
    public function __construct(
        string $name,
        string $type,
        string $description = '',
        array $properties = [],
        bool $required = false
    ) {
        $this->name = $name;
        $this->type = $type;
        $this->description = $description;
        $this->properties = $properties;
        $this->required = $required;
    }
}
```

```php
<?php

declare(strict_types=1);

namespace PhpLLP\Chat\FunctionCall;

class FunctionInfo
{
    /** @var string */
    public $name;

    /** @var object */
    public $instance;

    /** @var string */
    public $description;

    /** @var ParameterInfo[] */
    public $parameters = [];

    /** @var string */
    public $jsonArgs = '';

    /** @var string|null */
    private $toolCallId = null;

    /**
     * @param string $name
     * @param object $instance
     * @param string $description
     * @param ParameterInfo[] $parameters
     */
    public function __construct(
        string $name,
        $instance,
        string $description,
        array $parameters = []
    ) {
        $this->name = $name;
        $this->instance = $instance;
        $this->description = $description;
        $this->parameters = $parameters;
    }

    /**
     * 执行函数调用
     *
     * @return mixed
     */
    public function call()
    {
        $args = json_decode($this->jsonArgs, true);

        return call_user_func_array([$this->instance, $this->name], $args ?: []);
    }

    /**
     * 设置工具调用 ID
     *
     * @param string|null $id
     * @return void
     */
    public function setToolCallId($id): void
    {
        $this->toolCallId = $id;
    }

    /**
     * 获取工具调用 ID
     *
     * @return string|null
     */
    public function getToolCallId()
    {
        return $this->toolCallId;
    }

    /**
     * 转换为 API 工具格式
     *
     * @return array<string, mixed>
     */
    public function toToolFormat(): array
    {
        $properties = [];
        $required = [];

        foreach ($this->parameters as $param) {
            $properties[$param->name] = [
                'type' => $param->type,
                'description' => $param->description,
            ];

            if ($param->type === 'array' && !empty($param->properties)) {
                $properties[$param->name]['items'] = $param->properties;
            }

            if ($param->required) {
                $required[] = $param->name;
            }
        }

        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name,
                'description' => $this->description,
                'parameters' => [
                    'type' => 'object',
                    'properties' => $properties,
                    'required' => $required,
                ],
            ],
        ];
    }
}
```

#### 5.6.2 FunctionBuilder（自动扫描方法生成工具）

```php
<?php

declare(strict_types=1);

namespace PhpLLP\Chat\FunctionCall;

class FunctionBuilder
{
    /**
     * 从对象的方法构建 FunctionInfo
     *
     * @param object $instance
     * @param string $methodName
     * @return FunctionInfo
     * @throws \RuntimeException
     */
    public static function build($instance, $methodName): FunctionInfo
    {
        $reflection = new \ReflectionMethod(get_class($instance), $methodName);
        $docComment = $reflection->getDocComment() ?: '';
        $params = $reflection->getParameters();

        $paramDescriptions = self::extractParamDescriptions($docComment);
        $functionDescription = self::extractDescription($docComment);

        $parameters = [];
        $required = [];

        foreach ($params as $param) {
            $paramName = $param->getName();
            $type = self::resolveType($param);
            $description = $paramDescriptions[$paramName] ?? '';

            $paramInfo = new ParameterInfo(
                $paramName,
                $type,
                $description,
                [],
                !$param->isOptional()
            );

            $parameters[] = $paramInfo;

            if (!$param->isOptional()) {
                $required[] = $paramInfo;
            }
        }

        $functionInfo = new FunctionInfo($methodName, $instance, $functionDescription, $parameters);

        return $functionInfo;
    }

    /**
     * 批量构建
     *
     * @param object $instance
     * @param string[] $methodNames
     * @return FunctionInfo[]
     */
    public static function buildBatch($instance, array $methodNames): array
    {
        $result = [];
        foreach ($methodNames as $name) {
            $result[] = self::build($instance, $name);
        }

        return $result;
    }

    /**
     * 从 PHPDoc 提取参数描述
     *
     * @param string $docComment
     * @return array<string, string>
     */
    private static function extractParamDescriptions($docComment): array
    {
        $descriptions = [];
        $pattern = '/@param\s+(\S+)\s+\$(\w+)\s+(.*)/';

        if (preg_match_all($pattern, $docComment, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $descriptions[$match[2]] = trim($match[3]);
            }
        }

        return $descriptions;
    }

    /**
     * 从 PHPDoc 提取描述
     *
     * @param string $docComment
     * @return string
     */
    private static function extractDescription($docComment): string
    {
        $description = preg_replace('/\s*\* @.*/', '', $docComment);
        $description = str_replace(['/**', '*/', '*'], '', $description);

        return trim($description);
    }

    /**
     * 解析参数类型
     *
     * @param \ReflectionParameter $param
     * @return string
     */
    private static function resolveType(\ReflectionParameter $param): string
    {
        $type = $param->getType();

        if ($type !== null) {
            $typeStr = $type->__toString();
            $map = [
                'int' => 'integer',
                'integer' => 'integer',
                'float' => 'number',
                'double' => 'number',
                'string' => 'string',
                'bool' => 'boolean',
                'boolean' => 'boolean',
                'array' => 'array',
                'null' => 'null',
            ];

            return $map[$typeStr] ?? 'string';
        }

        return 'string';
    }
}
```

#### 5.6.3 ToolInterface（工具接口）

```php
<?php

declare(strict_types=1);

namespace PhpLLP\Contracts;

interface ToolInterface
{
    /**
     * 获取工具名称
     *
     * @return string
     */
    public function getName(): string;

    /**
     * 获取工具描述
     *
     * @return string
     */
    public function getDescription(): string;

    /**
     * 获取参数定义
     *
     * @return array<string, mixed>
     */
    public function getParameters(): array;

    /**
     * 执行工具
     *
     * @param array<string, mixed> $params
     * @return mixed
     */
    public function execute(array $params);
}
```

#### 5.6.4 ToolResult（工具执行结果）

```php
<?php

declare(strict_types=1);

namespace PhpLLP\Tools;

class ToolResult
{
    /** @var mixed */
    private $result;

    /** @var bool */
    private $success;

    /** @var string */
    private $errorMessage;

    /**
     * @param mixed $result
     * @param bool $success
     * @param string $errorMessage
     */
    public function __construct($result, bool $success = true, string $errorMessage = '')
    {
        $this->result = $result;
        $this->success = $success;
        $this->errorMessage = $errorMessage;
    }

    /**
     * @return mixed
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * @return string
     */
    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }

    /**
     * @return string
     */
    public function toString(): string
    {
        if (!$this->success) {
            return 'Error: ' . $this->errorMessage;
        }

        if (is_string($this->result)) {
            return $this->result;
        }

        return json_encode($this->result, JSON_UNESCAPED_UNICODE);
    }
}
```

#### 5.6.5 ToolManager（工具管理器）

```php
<?php

declare(strict_types=1);

namespace PhpLLP\Tools;

use PhpLLP\Contracts\ToolInterface;
use PhpLLP\Chat\FunctionCall\FunctionInfo;
use PhpLLP\Exception\LLPException;

class ToolManager
{
    /** @var array<string, ToolInterface|FunctionInfo> */
    private $tools = [];

    /**
     * 注册一个工具
     *
     * @param ToolInterface|FunctionInfo $tool
     * @return void
     */
    public function register($tool): void
    {
        if ($tool instanceof ToolInterface) {
            $this->tools[$tool->getName()] = $tool;
        } elseif ($tool instanceof FunctionInfo) {
            $this->tools[$tool->name] = $tool;
        } else {
            throw new LLPException('工具必须实现 ToolInterface 或为 FunctionInfo 实例');
        }
    }

    /**
     * 批量注册工具
     *
     * @param array<ToolInterface|FunctionInfo> $tools
     * @return void
     */
    public function registerBatch(array $tools): void
    {
        foreach ($tools as $tool) {
            $this->register($tool);
        }
    }

    /**
     * 注销工具
     *
     * @param string $name
     * @return void
     */
    public function unregister(string $name): void
    {
        unset($this->tools[$name]);
    }

    /**
     * 获取工具
     *
     * @param string $name
     * @return ToolInterface|FunctionInfo|null
     */
    public function get(string $name)
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * 获取所有工具
     *
     * @return array<string, ToolInterface|FunctionInfo>
     */
    public function all(): array
    {
        return $this->tools;
    }

    /**
     * 获取工具列表（供 API 调用）
     *
     * @return array<int, array<string, mixed>>
     */
    public function getToolsForApi(): array
    {
        $result = [];
        foreach ($this->tools as $tool) {
            if ($tool instanceof FunctionInfo) {
                $result[] = $tool->toToolFormat();
            } elseif ($tool instanceof ToolInterface) {
                $result[] = [
                    'type' => 'function',
                    'function' => [
                        'name' => $tool->getName(),
                        'description' => $tool->getDescription(),
                        'parameters' => $tool->getParameters(),
                    ],
                ];
            }
        }
        return $result;
    }

    /**
     * 执行工具
     *
     * @param string $name
     * @param array<string, mixed> $args
     * @return ToolResult
     */
    public function execute(string $name, array $args = []): ToolResult
    {
        $tool = $this->get($name);

        if ($tool === null) {
            return new ToolResult(null, false, "工具 '{$name}' 未注册");
        }

        try {
            if ($tool instanceof FunctionInfo) {
                $tool->jsonArgs = json_encode($args);
                $result = $tool->call();
                return new ToolResult($result, true);
            }

            if ($tool instanceof ToolInterface) {
                $result = $tool->execute($args);
                return new ToolResult($result, true);
            }

            return new ToolResult(null, false, '未知的工具类型');
        } catch (\Throwable $e) {
            return new ToolResult(null, false, $e->getMessage());
        }
    }

    /**
     * 检查工具是否存在
     *
     * @param string $name
     * @return bool
     */
    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }
}
```

#### 5.6.6 内置工具

##### WebPageFetcher（网页抓取工具）

```php
<?php

declare(strict_types=1);

namespace PhpLLP\Tools\Builtin;

use PhpLLP\Contracts\ToolInterface;
use PhpLLP\Http\HttpClient;

class WebPageFetcher implements ToolInterface
{
    /** @var HttpClient */
    private $httpClient;

    public function __construct()
    {
        $this->httpClient = new HttpClient(['timeout' => 10]);
    }

    public function getName(): string
    {
        return 'fetch_web_page';
    }

    public function getDescription(): string
    {
        return '获取指定 URL 的网页内容，返回纯文本内容';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'url' => [
                    'type' => 'string',
                    'description' => '要抓取的网页 URL',
                ],
            ],
            'required' => ['url'],
        ];
    }

    public function execute(array $params)
    {
        $url = $params['url'] ?? '';
        if (empty($url)) {
            return '错误：必须提供 URL 参数';
        }

        $response = $this->httpClient->get($url);
        $html = $response->getBody();

        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);

        return mb_substr(trim($text), 0, 5000);
    }
}
```

##### Calculator（计算器工具 - 安全实现）

> **安全说明**: 不使用 `eval()`，采用自定义递归下降解析器，仅支持数学运算，杜绝代码注入风险。

```php
<?php

declare(strict_types=1);

namespace PhpLLP\Tools\Builtin;

use PhpLLP\Contracts\ToolInterface;

class Calculator implements ToolInterface
{
    /** @var string */
    private $input;

    /** @var int */
    private $pos;

    public function getName(): string
    {
        return 'calculator';
    }

    public function getDescription(): string
    {
        return '执行数学计算，支持加减乘除等基本运算';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'expression' => [
                    'type' => 'string',
                    'description' => '数学表达式，如 "2 + 3 * 4"',
                ],
            ],
            'required' => ['expression'],
        ];
    }

    /**
     * 安全执行表达式 - 递归下降解析器
     *
     * 支持: +  -  *  /  %  ()  一元正负
     * 不支持: 变量、函数、幂运算等危险特性
     *
     * @param array $params
     * @return string
     */
    public function execute(array $params)
    {
        $expression = $params['expression'] ?? '';

        if (trim($expression) === '') {
            return '错误：必须提供表达式';
        }

        $this->input = preg_replace('/[^0-9+\-*\/().%\s]/', '', $expression);
        $this->input = preg_replace('/\s+/', '', $this->input);
        $this->pos = 0;

        if ($this->input === '') {
            return '错误：无效的表达式';
        }

        try {
            $result = $this->parseExpression();
            $this->skipWhitespace();

            if ($this->pos < strlen($this->input)) {
                return '错误：表达式解析失败，位置 ' . $this->pos . ' 有多余字符';
            }

            if (is_float($result) && floor($result) == $result && !is_infinite($result)) {
                return (string)(int)$result;
            }

            return (string)$result;
        } catch (\Throwable $e) {
            return '错误：' . $e->getMessage();
        }
    }

    /**
     * 解析表达式 (加减法)
     *
     * @return float|int
     */
    private function parseExpression()
    {
        $result = $this->parseTerm();

        $this->skipWhitespace();
        while ($this->pos < strlen($this->input) && in_array($this->input[$this->pos], ['+', '-'], true)) {
            $op = $this->input[$this->pos];
            $this->pos++;
            $right = $this->parseTerm();

            if ($op === '+') {
                $result += $right;
            } else {
                $result -= $right;
            }
            $this->skipWhitespace();
        }

        return $result;
    }

    /**
     * 解析项 (乘除法)
     *
     * @return float|int
     */
    private function parseTerm()
    {
        $result = $this->parseFactor();

        $this->skipWhitespace();
        while ($this->pos < strlen($this->input) && in_array($this->input[$this->pos], ['*', '/', '%'], true)) {
            $op = $this->input[$this->pos];
            $this->pos++;
            $right = $this->parseFactor();

            if ($op === '*') {
                $result *= $right;
            } elseif ($op === '/') {
                if ($right == 0) {
                    throw new \RuntimeException('除数不能为零');
                }
                $result /= $right;
            } else {
                if ($right == 0) {
                    throw new \RuntimeException('除数不能为零');
                }
                $result = fmod((float)$result, (float)$right);
            }
            $this->skipWhitespace();
        }

        return $result;
    }

    /**
     * 解析因子 (数字或括号表达式)
     *
     * @return float|int
     */
    private function parseFactor()
    {
        $this->skipWhitespace();

        if ($this->pos >= strlen($this->input)) {
            throw new \RuntimeException('表达式不完整');
        }

        $char = $this->input[$this->pos];

        if ($char === '+') {
            $this->pos++;
            return $this->parseFactor();
        }

        if ($char === '-') {
            $this->pos++;
            return -$this->parseFactor();
        }

        if ($char === '(') {
            $this->pos++;
            $result = $this->parseExpression();
            $this->skipWhitespace();

            if ($this->pos >= strlen($this->input) || $this->input[$this->pos] !== ')') {
                throw new \RuntimeException('缺少右括号');
            }
            $this->pos++;

            return $result;
        }

        return $this->parseNumber();
    }

    /**
     * 解析数字
     *
     * @return float|int
     */
    private function parseNumber()
    {
        $start = $this->pos;

        while ($this->pos < strlen($this->input) && (ctype_digit($this->input[$this->pos]) || $this->input[$this->pos] === '.')) {
            $this->pos++;
        }

        $numStr = substr($this->input, $start, $this->pos - $start);

        if ($numStr === '') {
            throw new \RuntimeException('期望数字，位置 ' . $start);
        }

        if (substr_count($numStr, '.') > 1) {
            throw new \RuntimeException('无效的数字格式: ' . $numStr);
        }

        return strpos($numStr, '.') !== false ? (float)$numStr : (int)$numStr;
    }

    /**
     * 跳过空白字符
     */
    private function skipWhitespace(): void
    {
        while ($this->pos < strlen($this->input) && $this->input[$this->pos] === ' ') {
            $this->pos++;
        }
    }
}
```

##### HttpRequester（HTTP 请求工具）

```php
<?php

declare(strict_types=1);

namespace PhpLLP\Tools\Builtin;

use PhpLLP\Contracts\ToolInterface;
use PhpLLP\Http\HttpClient;
use PhpLLP\Support\Json;

class HttpRequester implements ToolInterface
{
    /** @var HttpClient */
    private $httpClient;

    public function __construct()
    {
        $this->httpClient = new HttpClient(['timeout' => 15]);
    }

    public function getName(): string
    {
        return 'http_request';
    }

    public function getDescription(): string
    {
        return '发送 HTTP 请求到指定 URL，支持 GET/POST 方法';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'url' => [
                    'type' => 'string',
                    'description' => '请求的 URL',
                ],
                'method' => [
                    'type' => 'string',
                    'description' => 'HTTP 方法（GET/POST），默认 GET',
                ],
                'body' => [
                    'type' => 'string',
                    'description' => 'POST 请求的请求体（JSON 字符串）',
                ],
            ],
            'required' => ['url'],
        ];
    }

    public function execute(array $params)
    {
        $url = $params['url'] ?? '';
        $method = strtoupper($params['method'] ?? 'GET');
        $body = isset($params['body']) ? $params['body'] : null;

        if (empty($url)) {
            return '错误：必须提供 URL';
        }

        $headers = ['Content-Type' => 'application/json'];

        if ($method === 'POST') {
            $response = $this->httpClient->post($url, $headers, $body);
        } else {
            $response = $this->httpClient->get($url);
        }

        return $response->getBody();
    }
}
```

##### TextSummarizer（文本摘要工具 - 基于 LLM）

```php
<?php

declare(strict_types=1);

namespace PhpLLP\Tools\Builtin;

use PhpLLP\Contracts\ToolInterface;
use PhpLLP\Contracts\ChatInterface;

class TextSummarizer implements ToolInterface
{
    /** @var ChatInterface */
    private $chat;

    /**
     * @param ChatInterface $chat
     */
    public function __construct(ChatInterface $chat)
    {
        $this->chat = $chat;
    }

    public function getName(): string
    {
        return 'summarize_text';
    }

    public function getDescription(): string
    {
        return '使用 LLM 对长文本进行摘要';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'text' => [
                    'type' => 'string',
                    'description' => '需要摘要的文本内容',
                ],
                'max_length' => [
                    'type' => 'integer',
                    'description' => '摘要最大长度（字符数），默认 200',
                ],
            ],
            'required' => ['text'],
        ];
    }

    public function execute(array $params)
    {
        $text = $params['text'] ?? '';
        $maxLength = $params['max_length'] ?? 200;

        if (empty($text)) {
            return '错误：必须提供文本内容';
        }

        $prompt = "请对以下文本进行摘要，不超过 {$maxLength} 个字符：\n\n" . $text;
        return $this->chat->generateText($prompt);
    }
}
```

#### 5.6.7 ToolExecutor（工具执行器）

```php
<?php

declare(strict_types=1);

namespace PhpLLP\Tools;

use PhpLLP\Contracts\ChatInterface;
use PhpLLP\Chat\FunctionCall\FunctionInfo;
use PhpLLP\Support\Json;
use PhpLLP\Exception\LLPException;

class ToolExecutor
{
    /** @var ChatInterface */
    private $chat;

    /** @var ToolManager */
    private $toolManager;

    /** @var int */
    private $maxIterations;

    /**
     * @param ChatInterface $chat
     * @param ToolManager|null $toolManager
     * @param int $maxIterations
     */
    public function __construct(
        ChatInterface $chat,
        ToolManager $toolManager = null,
        int $maxIterations = 5
    ) {
        $this->chat = $chat;
        $this->toolManager = $toolManager ?: new ToolManager();
        $this->maxIterations = $maxIterations;
    }

    /**
     * 执行带工具的对话
     *
     * @param string $prompt
     * @param array<string, mixed> $options
     * @return string
     */
    public function execute(string $prompt, array $options = []): string
    {
        $messages = [
            ['role' => 'user', 'content' => $prompt],
        ];

        $tools = $this->toolManager->getToolsForApi();
        $iteration = 0;

        while ($iteration < $this->maxIterations) {
            $iteration++;

            $response = $this->chat->generateChatWithTools($messages, $tools, $options);

            if ($response['type'] === 'message') {
                return $response['content'];
            }

            if ($response['type'] === 'tool_call') {
                $toolName = $response['tool_name'];
                $toolArgs = $response['tool_args'];

                $toolResult = $this->toolManager->execute($toolName, $toolArgs);

                $messages[] = [
                    'role' => 'assistant',
                    'content' => null,
                    'tool_calls' => [[
                        'id' => $response['tool_call_id'] ?? 'call_' . $iteration,
                        'type' => 'function',
                        'function' => [
                            'name' => $toolName,
                            'arguments' => json_encode($toolArgs),
                        ],
                    ]],
                ];

                $messages[] = [
                    'role' => 'tool',
                    'content' => $toolResult->toString(),
                    'tool_call_id' => $response['tool_call_id'] ?? 'call_' . $iteration,
                ];
            }
        }

        return '已达到最大迭代次数，无法完成任务。';
    }

    /**
     * 获取 ToolManager
     *
     * @return ToolManager
     */
    public function getToolManager(): ToolManager
    {
        return $this->toolManager;
    }
}
```

### 5.7 Embeddings 模块

#### 5.7.1 Document 模型

```php
<?php

declare(strict_types=1);

namespace PhpLLP\Embeddings;

class Document
{
    /** @var string */
    public $content = '';

    /** @var string|null */
    public $formattedContent = null;

    /** @var array<int, float>|null */
    public $embedding = null;

    /** @var string */
    public $sourceType = 'manual';

    /** @var string */
    public $sourceName = 'manual';

    /** @var string */
    public $hash = '';

    /** @var int */
    public $chunkNumber = 0;

    /** @var array<string, mixed> */
    public $metadata = [];

    /**
     * 从数组创建
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $doc = new self();
        $doc->content = $data['content'] ?? '';
        $doc->formattedContent = $data['formattedContent'] ?? null;
        $doc->embedding = $data['embedding'] ?? null;
        $doc->sourceType = $data['sourceType'] ?? 'manual';
        $doc->sourceName = $data['sourceName'] ?? 'manual';
        $doc->hash = $data['hash'] ?? '';
        $doc->chunkNumber = $data['chunkNumber'] ?? 0;
        $doc->metadata = $data['metadata'] ?? [];

        return $doc;
    }

    /**
     * 转换为数组
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'formattedContent' => $this->formattedContent,
            'embedding' => $this->embedding,
            'sourceType' => $this->sourceType,
            'sourceName' => $this->sourceName,
            'hash' => $this->hash,
            'chunkNumber' => $this->chunkNumber,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * 获取唯一 ID
     *
     * @return string
     */
    public function getUniqueId(): string
    {
        return $this->sourceType . ':' . $this->sourceName . ':' . $this->chunkNumber;
    }

    /**
     * 生成哈希
     *
     * @return string
     */
    public function generateHash(): string
    {
        $this->hash = hash('sha256', $this->content);

        return $this->hash;
    }
}
```

#### 5.7.2 Embedding 接口

```php
<?php

declare(strict_types=1);

namespace PhpLLP\Contracts;

use PhpLLP\Embeddings\Document;

interface EmbeddingInterface
{
    /**
     * 将文本转换为向量
     *
     * @param string $text
     * @param array<string, mixed> $options
     * @return array<int, float>
     */
    public function embedText(string $text, array $options = []): array;

    /**
     * 为文档生成嵌入
     *
     * @param Document $document
     * @param array<string, mixed> $options
     * @return Document
     */
    public function embedDocument(Document $document, array $options = []): Document;

    /**
     * 批量为文档生成嵌入
     *
     * @param Document[] $documents
     * @param array<string, mixed> $options
     * @return Document[]
     */
    public function embedDocuments(array $documents, array $options = []): array;

    /**
     * 获取嵌入维度
     *
     * @return int
     */
    public function getEmbeddingDimension(): int;
}
```

#### 5.7.3 距离度量

```php
<?php

declare(strict_types=1);

namespace PhpLLP\Embeddings\Distances;

interface DistanceInterface
{
    /**
     * 计算两个向量之间的距离
     *
     * @param array<int, float> $vector1
     * @param array<int, float> $vector2
     * @return float
     */
    public function measure(array $vector1, array $vector2): float;
}
```

```php
<?php

declare(strict_types=1);

namespace PhpLLP\Embeddings\Distances;

class CosineDistance implements DistanceInterface
{
    public function measure(array $vector1, array $vector2): float
    {
        if (count($vector1) !== count($vector2)) {
            throw new \InvalidArgumentException('两个向量的维度必须相同');
        }

        $dotProduct = 0.0;
        $magnitude1 = 0.0;
        $magnitude2 = 0.0;

        for ($i = 0; $i < count($vector1); $i++) {
            $dotProduct += $vector1[$i] * $vector2[$i];
            $magnitude1 += $vector1[$i] * $vector1[$i];
            $magnitude2 += $vector2[$i] * $vector2[$i];
        }

        $magnitude1 = sqrt($magnitude1);
        $magnitude2 = sqrt($magnitude2);

        if ($magnitude1 * $magnitude2 == 0) {
            return 0.0;
        }

        return 1.0 - ($dotProduct / ($magnitude1 * $magnitude2));
    }
}
```

#### 5.7.4 Embedding 生成器

##### 基础抽象类

```php
<?php

declare(strict_types=1);

namespace PhpLLP\Embeddings\Generator;

use PhpLLP\Contracts\EmbeddingInterface;
use PhpLLP\Embeddings\Document;
use PhpLLP\Http\HttpClient;

abstract class EmbeddingGenerator implements EmbeddingInterface
{
    /** @var array<string, mixed> */
    protected $config;

    /** @var HttpClient */
    protected $httpClient;

    /** @var int */
    protected $dimension = 1536;

    /**
     * @param array<string, mixed> $config
     * @param HttpClient $httpClient
     */
    public function __construct(array $config, HttpClient $httpClient)
    {
        $this->config = $config;
        $this->httpClient = $httpClient;
    }

    public function embedText(string $text, array $options = []): array
    {
        $response = $this->sendEmbeddingRequest($text, $options);
        return $response;
    }

    public function embedDocument(Document $document, array $options = []): Document
    {
        if ($document->formattedContent !== null) {
            $text = $document->formattedContent;
        } else {
            $text = $document->content;
        }

        $document->embedding = $this->embedText($text, $options);
        return $document;
    }

    public function embedDocuments(array $documents, array $options = []): array
    {
        foreach ($documents as $document) {
            $this->embedDocument($document, $options);
        }
        return $documents;
    }

    public function getEmbeddingDimension(): int
    {
        return $this->dimension;
    }

    /**
     * 发送嵌入请求
     *
     * @param string $text
     * @param array<string, mixed> $options
     * @return array<int, float>
     */
    abstract protected function sendEmbeddingRequest(string $text, array $options = []): array;
}
```

##### OpenAI Embedding Generator

```php
<?php

declare(strict_types=1);

namespace PhpLLP\Embeddings\Generator;

use PhpLLP\Support\Json;

class OpenAIEmbeddingGenerator extends EmbeddingGenerator
{
    /** @var string */
    private $apiKey;

    /** @var string */
    private $baseUrl;

    /** @var string */
    private $model;

    public function __construct(array $config, \PhpLLP\Http\HttpClient $httpClient)
    {
        parent::__construct($config, $httpClient);
        $this->apiKey = $config['api_key'] ?? '';
        $this->baseUrl = rtrim($config['base_url'] ?? 'https://api.openai.com/v1', '/');
        $this->model = $config['embedding_model'] ?? 'text-embedding-3-small';
        $this->dimension = $config['embedding_dim'] ?? 1536;
    }

    protected function sendEmbeddingRequest(string $text, array $options = []): array
    {
        $payload = [
            'model' => $options['model'] ?? $this->model,
            'input' => $text,
        ];

        if (isset($options['dimensions'])) {
            $payload['dimensions'] = $options['dimensions'];
        }

        $response = $this->httpClient->post(
            $this->baseUrl . '/embeddings',
            [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            $payload
        );

        $data = Json::decode($response->getBody());
        return $data['data'][0]['embedding'] ?? [];
    }
}
```

##### Mistral Embedding Generator

```php
<?php

declare(strict_types=1);

namespace PhpLLP\Embeddings\Generator;

use PhpLLP\Support\Json;

class MistralEmbeddingGenerator extends EmbeddingGenerator
{
    /** @var string */
    private $apiKey;

    /** @var string */
    private $baseUrl;

    /** @var string */
    private $model;

    public function __construct(array $config, \PhpLLP\Http\HttpClient $httpClient)
    {
        parent::__construct($config, $httpClient);
        $this->apiKey = $config['api_key'] ?? '';
        $this->baseUrl = rtrim($config['base_url'] ?? 'https://api.mistral.ai/v1', '/');
        $this->model = $config['embedding_model'] ?? 'mistral-embed';
        $this->dimension = $config['embedding_dim'] ?? 1024;
    }

    protected function sendEmbeddingRequest(string $text, array $options = []): array
    {
        $payload = [
            'model' => $options['model'] ?? $this->model,
            'input' => [$text],
        ];

        $response = $this->httpClient->post(
            $this->baseUrl . '/embeddings',
            [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            $payload
        );

        $data = Json::decode($response->getBody());
        return $data['data'][0]['embedding'] ?? [];
    }
}
```

##### Ollama Embedding Generator

```php
<?php

declare(strict_types=1);

namespace PhpLLP\Embeddings\Generator;

use PhpLLP\Support\Json;

class OllamaEmbeddingGenerator extends EmbeddingGenerator
{
    /** @var string */
    private $baseUrl;

    /** @var string */
    private $model;

    public function __construct(array $config, \PhpLLP\Http\HttpClient $httpClient)
    {
        parent::__construct($config, $httpClient);
        $this->baseUrl = rtrim($config['base_url'] ?? 'http://localhost:11434', '/');
        $this->model = $config['embedding_model'] ?? 'nomic-embed-text';
        $this->dimension = $config['embedding_dim'] ?? 768;
    }

    protected function sendEmbeddingRequest(string $text, array $options = []): array
    {
        $payload = [
            'model' => $options['model'] ?? $this->model,
            'prompt' => $text,
        ];

        $response = $this->httpClient->post(
            $this->baseUrl . '/api/embeddings',
            ['Content-Type' => 'application/json'],
            $payload
        );

        $data = Json::decode($response->getBody());
        return $data['embedding'] ?? [];
    }
}
```

#### 5.7.5 DocumentSplitter（文档分割器）

```php
<?php

declare(strict_types=1);

namespace PhpLLP\Embeddings\Splitter;

use PhpLLP\Embeddings\Document;

class DocumentSplitter
{
    /** @var int */
    private $chunkSize;

    /** @var int */
    private $chunkOverlap;

    /** @var string */
    private $separator;

    /**
     * @param int $chunkSize
     * @param int $chunkOverlap
     * @param string $separator
     */
    public function __construct(int $chunkSize = 1000, int $chunkOverlap = 200, string $separator = "\n")
    {
        $this->chunkSize = $chunkSize;
        $this->chunkOverlap = $chunkOverlap;
        $this->separator = $separator;
    }

    /**
     * 将文本分割为文档块
     *
     * @param string $text
     * @param string $sourceType
     * @param string $sourceName
     * @return Document[]
     */
    public function split(string $text, string $sourceType = 'manual', string $sourceName = ''): array
    {
        $chunks = $this->splitText($text);
        $documents = [];

        foreach ($chunks as $i => $chunkText) {
            $doc = new Document();
            $doc->content = $chunkText;
            $doc->sourceType = $sourceType;
            $doc->sourceName = $sourceName ?: 'chunk_' . $i;
            $doc->chunkNumber = $i;
            $doc->generateHash();
            $documents[] = $doc;
        }

        return $documents;
    }

    /**
     * 将已有文档按内容重新分割
     *
     * @param Document[] $documents
     * @return Document[]
     */
    public function splitDocuments(array $documents): array
    {
        $result = [];

        foreach ($documents as $document) {
            $chunks = $this->splitText($document->content);
            foreach ($chunks as $i => $chunkText) {
                $doc = new Document();
                $doc->content = $chunkText;
                $doc->sourceType = $document->sourceType;
                $doc->sourceName = $document->sourceName;
                $doc->chunkNumber = $document->chunkNumber * 1000 + $i;
                $doc->metadata = $document->metadata;
                $doc->generateHash();
                $result[] = $doc;
            }
        }

        return $result;
    }

    /**
     * @param string $text
     * @return string[]
     */
    private function splitText(string $text): array
    {
        if (empty($text)) {
            return [];
        }

        $segments = explode($this->separator, $text);
        $chunks = [];
        $currentChunk = '';

        foreach ($segments as $segment) {
            $segment = trim($segment);
            if ($segment === '') {
                continue;
            }

            if (strlen($currentChunk) + strlen($segment) > $this->chunkSize && $currentChunk !== '') {
                $chunks[] = $currentChunk;
                $currentChunk = $this->getOverlapText($currentChunk) . $segment;
            } else {
                $currentChunk = $currentChunk === '' ? $segment : $currentChunk . $this->separator . $segment;
            }
        }

        if ($currentChunk !== '') {
            $chunks[] = $currentChunk;
        }

        return $chunks;
    }

    /**
     * 获取重叠文本
     *
     * @param string $text
     * @return string
     */
    private function getOverlapText(string $text): string
    {
        $length = strlen($text);
        if ($length <= $this->chunkOverlap) {
            return $text;
        }

        return substr($text, -$this->chunkOverlap);
    }
}
```

#### 5.7.6 EmbeddingFormatter（嵌入格式化器）

```php
<?php

declare(strict_types=1);

namespace PhpLLP\Embeddings\Formatter;

use PhpLLP\Embeddings\Document;

class EmbeddingFormatter
{
    /** @var string */
    private $template;

    /**
     * @param string $template
     */
    public function __construct(string $template = '{text}')
    {
        $this->template = $template;
    }

    /**
     * 格式化文档内容
     *
     * @param Document $document
     * @return Document
     */
    public function format(Document $document): Document
    {
        $document->formattedContent = str_replace('{text}', $document->content, $this->template);
        return $document;
    }

    /**
     * 批量格式化
     *
     * @param Document[] $documents
     * @return Document[]
     */
    public function formatBatch(array $documents): array
    {
        foreach ($documents as $document) {
            $this->format($document);
        }
        return $documents;
    }
}
```

#### 5.7.7 EuclideanDistance（欧几里得距离）

```php
<?php

declare(strict_types=1);

namespace PhpLLP\Embeddings\Distances;

class EuclideanDistance implements DistanceInterface
{
    public function measure(array $vector1, array $vector2): float
    {
        if (count($vector1) !== count($vector2)) {
            throw new \InvalidArgumentException('两个向量的维度必须相同');
        }

        $sum = 0.0;
        for ($i = 0; $i < count($vector1); $i++) {
            $diff = $vector1[$i] - $vector2[$i];
            $sum += $diff * $diff;
        }

        return sqrt($sum);
    }
}
```

### 5.8 VectorStore 模块

#### 5.8.1 VectorStore 接口

```php
<?php

declare(strict_types=1);

namespace PhpLLP\Contracts;

use PhpLLP\Embeddings\Document;

interface VectorStoreInterface
{
    /**
     * 添加单个文档
     *
     * @param Document $document
     * @return void
     */
    public function addDocument(Document $document): void;

    /**
     * 批量添加文档
     *
     * @param Document[] $documents
     * @return void
     */
    public function addDocuments(array $documents): void;

    /**
     * 相似度搜索
     *
     * @param array<int, float> $embedding 查询向量
     * @param int $k 返回最相似的 k 个文档
     * @param array<string, mixed> $filters 过滤条件
     * @return Document[]
     */
    public function similaritySearch(array $embedding, int $k = 4, array $filters = []): array;

    /**
     * 删除存储
     *
     * @return bool
     */
    public function delete(): bool;

    /**
     * 获取文档数量
     *
     * @return int
     */
    public function count(): int;
}
```

#### 5.8.2 VectorStoreBase 抽象基类

> 提供通用的 CRUD 逻辑、距离计算和过滤支持，供所有向量存储继承。

```php
<?php

declare(strict_types=1);

namespace PhpLLP\VectorStore;

use PhpLLP\Contracts\VectorStoreInterface;
use PhpLLP\Embeddings\Distances\CosineDistance;
use PhpLLP\Embeddings\Distances\DistanceInterface;
use PhpLLP\Embeddings\Document;
use PhpLLP\Exception\LLPException;

abstract class VectorStoreBase implements VectorStoreInterface
{
    /** @var DistanceInterface */
    protected $distance;

    /** @var int */
    protected $vectorDim;

    /**
     * @param DistanceInterface|null $distance
     * @param int $vectorDim
     */
    public function __construct(DistanceInterface $distance = null, int $vectorDim = 1536)
    {
        $this->distance = $distance ?: new CosineDistance();
        $this->vectorDim = $vectorDim;
    }

    /**
     * 从文档提取元数据键值对（用于过滤）
     *
     * @param Document $document
     * @return array<string, mixed>
     */
    protected function extractFilterableFields(Document $document): array
    {
        return [
            'sourceType' => $document->sourceType,
            'sourceName' => $document->sourceName,
            'hash' => $document->hash,
            'chunkNumber' => $document->chunkNumber,
        ];
    }

    /**
     * 验证嵌入向量维度
     *
     * @param array<int, float> $embedding
     * @throws LLPException
     */
    protected function validateEmbedding(array $embedding): void
    {
        if (empty($embedding)) {
            throw new LLPException('嵌入向量不能为空');
        }

        if ($this->vectorDim > 0 && count($embedding) !== $this->vectorDim) {
            throw new LLPException(sprintf(
                '嵌入维度不匹配: 期望 %d, 实际 %d',
                $this->vectorDim,
                count($embedding)
            ));
        }
    }

    /**
     * 本地计算排序文档（用于 FileSystem、SQLite 等本地存储）
     *
     * @param array<int, float> $queryEmbedding
     * @param Document[] $documents
     * @param int $k
     * @param array<string, mixed> $filters
     * @return Document[]
     */
    protected function computeRankedDocuments(array $queryEmbedding, array $documents, int $k, array $filters = []): array
    {
        $this->validateEmbedding($queryEmbedding);

        $distances = [];
        foreach ($documents as $index => $document) {
            if ($document->embedding === null) {
                continue;
            }

            if (!empty($filters) && !$this->matchesFilter($document, $filters)) {
                continue;
            }

            $dist = $this->distance->measure($queryEmbedding, $document->embedding);
            $distances[$index] = $dist;
        }

        asort($distances);
        $topK = array_slice(array_keys($distances), 0, $k, true);

        $results = [];
        foreach ($topK as $index) {
            if (isset($documents[$index])) {
                $results[] = $documents[$index];
            }
        }

        return $results;
    }

    /**
     * 检查文档是否匹配过滤条件
     *
     * @param Document $document
     * @param array<string, mixed> $filters
     * @return bool
     */
    protected function matchesFilter(Document $document, array $filters): bool
    {
        $fields = $this->extractFilterableFields($document);

        foreach ($filters as $key => $value) {
            if (!isset($fields[$key]) || (string) $fields[$key] !== (string) $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * 将 Document 转为可存储的数组
     *
     * @param Document $document
     * @return array<string, mixed>
     */
    protected function documentToArray(Document $document): array
    {
        return [
            'content' => $document->content,
            'formattedContent' => $document->formattedContent,
            'embedding' => $document->embedding,
            'sourceType' => $document->sourceType,
            'sourceName' => $document->sourceName,
            'hash' => $document->hash,
            'chunkNumber' => $document->chunkNumber,
            'metadata' => $document->metadata,
        ];
    }

    /**
     * 从数组创建 Document
     *
     * @param array<string, mixed> $data
     * @return Document
     */
    protected function arrayToDocument(array $data): Document
    {
        $doc = new Document();
        $doc->content = $data['content'] ?? '';
        $doc->formattedContent = $data['formattedContent'] ?? null;
        $doc->embedding = $data['embedding'] ?? null;
        $doc->sourceType = $data['sourceType'] ?? '';
        $doc->sourceName = $data['sourceName'] ?? '';
        $doc->hash = $data['hash'] ?? '';
        $doc->chunkNumber = (int) ($data['chunkNumber'] ?? 0);
        $doc->metadata = $data['metadata'] ?? [];
        return $doc;
    }

    /**
     * 获取距离计算器
     *
     * @return DistanceInterface
     */
    public function getDistance(): DistanceInterface
    {
        return $this->distance;
    }

    /**
     * 获取向量维度
     *
     * @return int
     */
    public function getVectorDim(): int
    {
        return $this->vectorDim;
    }
}
```

#### 5.8.3 VectorStoreFactory（向量存储工厂）

> 根据配置字符串创建对应的 VectorStore 实例，避免在业务代码中使用 switch/case。

```php
<?php

declare(strict_types=1);

namespace PhpLLP\VectorStore;

use PhpLLP\Contracts\VectorStoreInterface;
use PhpLLP\Exception\ConfigException;

class VectorStoreFactory
{
    /**
     * 创建向量存储实例
     *
     * @param string $type
     * @param array<string, mixed> $config
     * @return VectorStoreInterface
     * @throws ConfigException
     */
    public static function create(string $type, array $config = []): VectorStoreInterface
    {
        switch ($type) {
            case 'filesystem':
                return new FileSystemVectorStore(
                    $config['vector_store_path'] ?? './vectors.json',
                    $config['distance'] ?? null,
                    $config['vector_dim'] ?? 1536
                );

            case 'postgres':
                return new PostgresVectorStore($config);

            case 'qdrant':
                return new QdrantVectorStore($config);

            case 'redis':
                return new RedisVectorStore($config);

            case 'elasticsearch':
                return new ElasticsearchVectorStore($config);

            case 'milvus':
            case 'zilliz':
                return new MilvusVectorStore($config);

            case 'chromadb':
                return new ChromaDBVectorStore($config);

            case 'astradb':
                return new AstraDBVectorStore($config);

            case 'sqlite':
                return new SQLiteVectorStore($config);

            default:
                throw new ConfigException("不支持的 VectorStore 类型: {$type}");
        }
    }

    /**
     * 获取所有可用的类型列表
     *
     * @return string[]
     */
    public static function availableTypes(): array
    {
        return [
            'filesystem',
            'postgres',
            'qdrant',
            'redis',
            'elasticsearch',
            'milvus',
            'zilliz',
            'chromadb',
            'astradb',
            'sqlite',
        ];
    }
}
```

#### 5.8.4 实现逻辑对比表

| 向量存储 | 通信协议 | 序列化方式 | 相似度计算 | 过滤支持 | 批量操作 | 自动建库 |
|---------|---------|----------|----------|---------|---------|--------|
| FileSystem | 文件系统 | JSON 编解码 | CosineDistance 本地计算 | PHP 层过滤 | JSON 批量读写 | 自动创建目录 |
| PostgreSQL | pgsql/PDO | pgvector `[1,2,3]` 格式 | pgvector `<=>` SQL 操作符 | SQL WHERE | INSERT 多行 | CREATE TABLE + EXTENSION |
| Qdrant | HTTP REST | JSON | Qdrant 服务端 Cosine | payload filter JSON | points 批量 upsert | PUT collections |
| Redis | RESP 协议 (socket) | Redis JSON 序列化 | RedisFT KNN COSINE | FT.SEARCH 过滤 | SET 批量 | FT.CREATE 索引 |
| Elasticsearch | HTTP REST (NDJSON) | NDJSON bulk | ES kNN cosine | kNN filter JSON | _bulk API | PUT index |
| Milvus/Zilliz | HTTP REST (v2.4) | JSON | Milvus COSINE metric | filter 表达式字符串 | /vectordb/insert | POST /collections |
| ChromaDB | HTTP REST (v2) | JSON | ChromaDB cosine | where filter | /add 批量 | POST /collections |
| AstraDB | HTTP REST | JSON | Astra cosine metric | find filter | insertMany | createCollection |
| SQLite | PDO SQLite | JSON + 内存计算 | CosineDistance 本地计算 | SQL WHERE | INSERT 多行 | CREATE TABLE |

#### 5.8.5 各向量存储实现要点

##### 通用设计模式

```
所有向量存储遵循的统一模式:
1. __construct(config) → 解析配置 → 初始化连接 → 自动建库
2. addDocument(Document) → 单条插入
3. addDocuments(Document[]) → 批量插入（优化性能）
4. similaritySearch(embedding, k, filters) → 相似度检索
5. delete() → 删除整个集合/表
6. count() → 文档数量
```

##### 本地计算 vs 服务端计算

- **本地计算**（FileSystem、SQLite）：获取全部数据 → PHP 层计算距离 → 排序取 TopK。适合小规模数据（<10万条），零外部依赖
- **服务端计算**（PostgreSQL、Qdrant、Redis、Elasticsearch、Milvus、ChromaDB、AstraDB）：将向量发送到服务端 → 数据库内部计算 → 返回 TopK。适合大规模数据，利用数据库索引优化

##### 过滤机制差异

| 存储 | 过滤实现 | 示例 |
|-----|--------|------|
| FileSystem | PHP 层 `matchesFilter()` | `['sourceType' => 'pdf']` |
| PostgreSQL | SQL WHERE 子句 | `WHERE source_type = 'pdf'` |
| Qdrant | payload filter JSON | `{'sourceType': 'pdf'}` |
| Redis | FT.SEARCH 过滤表达式 | `@sourceType:pdf` |
| Elasticsearch | kNN filter JSON | `{'term': {'sourceType': 'pdf'}}` |
| Milvus | filter 表达式字符串 | `sourceType == "pdf"` |
| ChromaDB | where JSON | `{'sourceType': 'pdf'}` |
| AstraDB | find filter JSON | `{'sourceType': 'pdf'}` |
| SQLite | SQL WHERE 子句 | `WHERE source_type = 'pdf'` |

#### 5.8.6 文件系统向量存储

> 将向量数据持久化到 JSON 文件，支持进程重启后恢复。适合单机轻量场景（<1万条）。

**实现逻辑**：
- 数据存储：`file_get_contents` 读取 JSON → `json_decode` 还原 Document 对象
- 写入策略：原子写入（先写临时文件再 rename）防止写入中断导致数据损坏
- 并发保护：使用 `flock()` 文件锁防止多进程写入冲突
- 相似度计算：本地计算（继承 `VectorStoreBase::computeRankedDocuments`）
- 自动建文件：首次写入时自动创建目录和 JSON 文件

```php
<?php

declare(strict_types=1);

namespace PhpLLP\VectorStore;

use PhpLLP\Embeddings\Distances\DistanceInterface;
use PhpLLP\Embeddings\Document;
use PhpLLP\Exception\LLPException;

class FileSystemVectorStore extends VectorStoreBase
{
    /** @var string */
    private $filePath;

    /** @var string */
    private $lockPath;

    /** @var int */
    private $filePerm;

    /** @var bool */
    private $autoSave;

    /**
     * @param string $filePath
     * @param DistanceInterface|null $distance
     * @param int $vectorDim
     * @param int $filePerm
     * @param bool $autoSave
     */
    public function __construct(
        string $filePath,
        DistanceInterface $distance = null,
        int $vectorDim = 1536,
        int $filePerm = 0666,
        bool $autoSave = true
    ) {
        parent::__construct($distance, $vectorDim);
        $this->filePath = $filePath;
        $this->lockPath = $filePath . '.lock';
        $this->filePerm = $filePerm;
        $this->autoSave = $autoSave;

        $dir = dirname($this->filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    public function addDocument(Document $document): void
    {
        $documents = $this->loadDocuments();
        $documents[] = $document;

        if ($this->autoSave) {
            $this->saveDocuments($documents);
        }
    }

    public function addDocuments(array $documents): void
    {
        $existing = $this->loadDocuments();
        $merged = array_merge($existing, $documents);

        if ($this->autoSave) {
            $this->saveDocuments($merged);
        }
    }

    public function similaritySearch(array $embedding, int $k = 4, array $filters = []): array
    {
        $documents = $this->loadDocuments();
        return $this->computeRankedDocuments($embedding, $documents, $k, $filters);
    }

    public function delete(): bool
    {
        if (file_exists($this->filePath)) {
            return unlink($this->filePath);
        }
        return true;
    }

    public function count(): int
    {
        return count($this->loadDocuments());
    }

    /**
     * 手动保存（autoSave=false 时使用）
     */
    public function save(): void
    {
        $this->saveDocuments($this->loadDocuments());
    }

    /**
     * 重新加载（绕过缓存，用于只读场景）
     *
     * @return Document[]
     */
    public function reload(): array
    {
        return $this->loadDocuments();
    }

    /**
     * @return Document[]
     * @throws LLPException
     */
    private function loadDocuments(): array
    {
        if (!file_exists($this->filePath)) {
            return [];
        }

        $content = file_get_contents($this->filePath);
        if ($content === false || $content === '') {
            return [];
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            throw new LLPException('向量文件损坏，JSON 解析失败');
        }

        $documents = [];
        foreach ($data as $item) {
            $documents[] = $this->arrayToDocument($item);
        }

        return $documents;
    }

    /**
     * 原子保存（文件锁 + 临时文件 + rename）
     *
     * @param Document[] $documents
     * @throws LLPException
     */
    private function saveDocuments(array $documents): void
    {
        $lockFp = fopen($this->lockPath, 'c+');
        if ($lockFp === false) {
            throw new LLPException('无法创建锁文件');
        }

        try {
            if (!flock($lockFp, LOCK_EX)) {
                throw new LLPException('无法获取文件锁');
            }

            $data = [];
            foreach ($documents as $document) {
                $data[] = $this->documentToArray($document);
            }

            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            $tmpFile = $this->filePath . '.tmp';
            if (file_put_contents($tmpFile, $json) === false) {
                throw new LLPException('临时文件写入失败');
            }

            if (!rename($tmpFile, $this->filePath)) {
                @unlink($tmpFile);
                throw new LLPException('文件重命名失败');
            }

            @chmod($this->filePath, $this->filePerm);
        } finally {
            flock($lockFp, LOCK_UN);
            fclose($lockFp);
        }
    }
}
```

#### 5.8.7 Qdrant 向量存储

> 通过原生 HTTP 请求与 Qdrant REST API 交互，不依赖 hkulekci/qdrant 客户端库。
> Qdrant 是一个用 Rust 编写的高性能向量数据库，通过 REST API 提供集合管理、点（point）增删改查和向量搜索功能。

**实现逻辑**：
- 通信协议：HTTP REST API（JSON 编解码）
- 自动建库：`PUT /collections/{name}` 创建集合，配置向量维度和距离度量
- 文档存储：以 point 形式存储，包含 `id`（哈希）、`vector`（嵌入向量）和 `payload`（元数据）
- 相似度搜索：`POST /collections/{name}/points/search` 服务端计算 Cosine 距离
- 过滤支持：Qdrant payload filter 格式，支持精确匹配和范围查询

```php
<?php

declare(strict_types=1);

namespace PhpLLP\VectorStore;

use PhpLLP\Embeddings\Document;
use PhpLLP\Http\HttpClient;
use PhpLLP\Support\Json;
use PhpLLP\Exception\LLPException;

class QdrantVectorStore extends VectorStoreBase
{
    /** @var HttpClient */
    private $httpClient;

    /** @var string */
    private $baseUrl;

    /** @var string */
    private $collectionName;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        $this->baseUrl = rtrim($config['url'] ?? 'http://localhost:6333', '/');
        $this->collectionName = $config['collection'] ?? 'phpLLP';
        $vectorDim = $config['vector_size'] ?? 1536;
        parent::__construct(null, $vectorDim);
        $this->httpClient = new HttpClient(['timeout' => $config['timeout'] ?? 30]);

        if ($config['create_collection'] ?? true) {
            $this->createCollectionIfNotExists();
        }
    }

    public function addDocument(Document $document): void
    {
        $this->addDocuments([$document]);
    }

    public function addDocuments(array $documents): void
    {
        if (empty($documents)) {
            return;
        }

        $points = [];
        foreach ($documents as $document) {
            $id = hash('sha256', $document->getUniqueId());
            $points[] = [
                'id' => $id,
                'vector' => $document->embedding,
                'payload' => $this->documentToArray($document),
            ];
        }

        $url = "{$this->baseUrl}/collections/{$this->collectionName}/points";
        $this->httpClient->put($url, [], ['points' => $points]);
    }

    public function similaritySearch(array $embedding, int $k = 4, array $filters = []): array
    {
        $this->validateEmbedding($embedding);

        $url = "{$this->baseUrl}/collections/{$this->collectionName}/points/search";

        $payload = [
            'vector' => $embedding,
            'limit' => $k,
            'with_payload' => true,
        ];

        if (!empty($filters)) {
            $payload['filter'] = $filters;
        }

        $response = $this->httpClient->post($url, [], $payload);
        $data = Json::decode($response->getBody());

        $results = [];
        foreach ($data['result'] ?? [] as $point) {
            $payload = $point['payload'] ?? [];
            $results[] = $this->arrayToDocument($payload);
        }

        return $results;
    }

    public function delete(): bool
    {
        $url = "{$this->baseUrl}/collections/{$this->collectionName}";
        $this->httpClient->delete($url);
        return true;
    }

    public function count(): int
    {
        $url = "{$this->baseUrl}/collections/{$this->collectionName}/count";
        $response = $this->httpClient->get($url);
        $data = Json::decode($response->getBody());
        return $data['result']['count'] ?? 0;
    }

    /**
     * 通过 ID 删除点
     *
     * @param string[] $ids
     */
    public function deletePoints(array $ids): void
    {
        $url = "{$this->baseUrl}/collections/{$this->collectionName}/points/delete";
        $this->httpClient->post($url, [], ['points' => $ids]);
    }

    /**
     * 创建集合（如不存在）
     */
    private function createCollectionIfNotExists(): void
    {
        try {
            $url = "{$this->baseUrl}/collections/{$this->collectionName}";
            $this->httpClient->get($url);
        } catch (\Exception $e) {
            $this->createCollection();
        }
    }

    /**
     * 执行创建集合
     */
    private function createCollection(): void
    {
        $url = "{$this->baseUrl}/collections/{$this->collectionName}";
        $this->httpClient->put($url, [], [
            'vectors' => [
                'size' => $this->vectorDim,
                'distance' => 'Cosine',
            ],
        ]);
    }
}
```

#### 5.8.8 Redis 向量存储

> 使用原生 socket 实现 RESP 协议与 Redis（Redisearch + RedisJSON）通信，不依赖 predis/predis 等第三方包。
> Redis 是内存键值数据库，通过 Redisearch 扩展支持向量索引和 KNN 搜索。

**实现逻辑**：
- 通信协议：RESP（Redis Serialization Protocol），通过 `stream_socket_client` 实现
- 自动建库：`FT.CREATE` 创建 RediSearch 索引，定义 JSON 文档结构和 VECTOR 字段
- 文档存储：以 Redis JSON 格式存储，key 为 `{indexName}:{uniqueId}`
- 相似度搜索：`FT.SEARCH` 使用 KNN 搜索语法，Redis 内部计算 Cosine 距离
- 向量传输：二进制格式（`pack('f', $value)`）节省空间和带宽
- 降级方案：优先使用 `\Redis` 原生扩展，不可用时降级到 socket RESP 协议

```php
<?php

declare(strict_types=1);

namespace PhpLLP\VectorStore;

use PhpLLP\Embeddings\Document;
use PhpLLP\Exception\LLPException;

class RedisVectorStore extends VectorStoreBase
{
    /** @var string */
    private $host;

    /** @var int */
    private $port;

    /** @var string */
    private $indexName;

    /** @var resource|\Redis */
    private $redis;

    /** @var bool */
    private $isNative;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        $this->host = $config['host'] ?? 'localhost';
        $this->port = $config['port'] ?? 6379;
        $this->indexName = $config['index'] ?? 'phpLLP_index';
        $vectorDim = $config['vector_dim'] ?? 1536;
        parent::__construct(null, $vectorDim);

        $this->connect();
        $this->createIndexIfNotExists();
    }

    /**
     * 建立 Redis 连接（优先原生扩展，降级 socket）
     *
     * @throws LLPException
     */
    private function connect(): void
    {
        if (class_exists(\Redis::class)) {
            $this->redis = new \Redis();
            $this->redis->connect($this->host, $this->port);
            $this->isNative = true;
        } else {
            $this->redis = stream_socket_client("tcp://{$this->host}:{$this->port}");
            if (!$this->redis) {
                throw new LLPException("无法连接到 Redis: {$this->host}:{$this->port}");
            }
            stream_set_timeout($this->redis, 30);
            $this->isNative = false;
        }
    }

    public function addDocument(Document $document): void
    {
        $this->addDocuments([$document]);
    }

    public function addDocuments(array $documents): void
    {
        if (empty($documents)) {
            return;
        }

        foreach ($documents as $document) {
            $key = $this->indexName . ':' . $document->getUniqueId();
            $data = json_encode($this->documentToArray($document));
            $this->execute('SET', $key, $data);
        }
    }

    public function similaritySearch(array $embedding, int $k = 4, array $filters = []): array
    {
        $this->validateEmbedding($embedding);

        // 将向量打包为二进制格式
        $binaryVector = '';
        foreach ($embedding as $value) {
            $binaryVector .= pack('f', (float) $value);
        }

        $filterStr = $this->buildFilterString($filters);
        $query = "{$filterStr}=>[KNN {$k} @embedding \$vec AS dist]";

        $result = $this->executeRaw(
            "FT.SEARCH", $this->indexName, $query,
            "PARAMS", "2", "vec", $binaryVector,
            "SORTBY", "dist", "ASC"
        );

        return $this->parseSearchResult($result);
    }

    public function delete(): bool
    {
        $this->execute('FLUSHDB');
        return true;
    }

    public function count(): int
    {
        $result = $this->executeRaw('FT.INFO', $this->indexName);
        if (is_array($result)) {
            // FT.INFO 返回的数组中，索引总数在 [2] 位置
            return isset($result[2]) ? (int) $result[2] : 0;
        }
        return 0;
    }

    /**
     * 构建 Redis 过滤字符串
     * Redisearch 使用 @field:value 格式进行过滤
     *
     * @param array<string, mixed> $filters
     * @return string
     */
    private function buildFilterString(array $filters): string
    {
        if (empty($filters)) {
            return '*';
        }

        $parts = [];
        foreach ($filters as $key => $value) {
            $parts[] = "@{$key}:{$value}";
        }

        return implode(' ', $parts);
    }

    /**
     * 创建索引（如不存在）
     */
    private function createIndexIfNotExists(): void
    {
        try {
            $this->executeRaw('FT.INFO', $this->indexName);
        } catch (\Exception $e) {
            $this->executeRaw(
                'FT.CREATE', $this->indexName,
                'ON', 'JSON',
                'PREFIX', '1', $this->indexName . ':',
                'SCHEMA',
                'content', 'TEXT',
                'formattedContent', 'TEXT',
                'sourceType', 'TAG',
                'sourceName', 'TAG',
                'hash', 'TAG',
                'chunkNumber', 'NUMERIC',
                'embedding', 'VECTOR', 'FLAT',
                'DIM', (string) $this->vectorDim,
                'TYPE', 'FLOAT32',
                'DISTANCE_METRIC', 'COSINE'
            );
        }
    }

    /**
     * 统一执行命令（自动选择原生方法或 RESP 协议）
     *
     * @param string ...$args
     * @return mixed
     */
    private function execute(...$args)
    {
        if ($this->isNative) {
            return call_user_func_array([$this->redis, array_shift($args)], $args);
        }

        return $this->executeRaw(...$args);
    }

    /**
     * RESP 协议原始命令执行
     * 构造 RESP 协议格式: *<count>\r\n$<len>\r\n<value>\r\n...
     *
     * @param string ...$args
     * @return array|string|int|null
     */
    private function executeRaw(...$args)
    {
        // 构建 RESP 协议命令
        $command = '*' . count($args) . "\r\n";
        foreach ($args as $arg) {
            $len = strlen((string) $arg);
            $command .= "\${$len}\r\n{$arg}\r\n";
        }

        fwrite($this->redis, $command);
        return $this->readResponse();
    }

    /**
     * 解析 RESP 协议响应
     * 支持: +(简单字符串)、-(错误)、:(整数)、$(批量字符串)、*(数组)
     *
     * @return array|string|int|null
     * @throws LLPException
     */
    private function readResponse()
    {
        $line = trim(fgets($this->redis));
        if ($line === false || $line === '') {
            throw new LLPException('Redis 连接已断开');
        }

        $prefix = $line[0];
        $data = substr($line, 1);

        switch ($prefix) {
            case '+':
                // 简单字符串: +OK\r\n
                return $data;

            case '-':
                // 错误: -ERR ...\r\n
                throw new LLPException("Redis Error: {$data}");

            case ':':
                // 整数: :42\r\n
                return (int) $data;

            case '$':
                // 批量字符串: $5\r\nhello\r\n
                $len = (int) $data;
                if ($len === -1) {
                    return null;
                }
                $result = fread($this->redis, $len);
                fgets($this->redis); // 消费尾部 \r\n
                return $result;

            case '*':
                // 数组: *2\r\n$3\r\nfoo\r\n$3\r\nbar\r\n
                $count = (int) $data;
                if ($count === -1) {
                    return null;
                }
                $result = [];
                for ($i = 0; $i < $count; $i++) {
                    $result[] = $this->readResponse();
                }
                return $result;

            default:
                return $data;
        }
    }

    /**
     * 解析 FT.SEARCH 搜索结果
     * Redisearch 返回格式: [total, key1, [field1, val1, field2, val2, ...], key2, ...]
     *
     * @param array $result
     * @return Document[]
     */
    private function parseSearchResult(array $result): array
    {
        $documents = [];

        // $result[0] 是命中总数，$result[1] 是第一个 key，$result[2] 是第一个文档的字段数组
        if (!isset($result[0])) {
            return $documents;
        }

        for ($i = 1; $i < count($result); $i += 2) {
            // $result[$i] 是 key，$result[$i+1] 是字段数组或 JSON 字符串
            $jsonData = '';
            if (isset($result[$i + 1])) {
                $fieldData = $result[$i + 1];
                if (is_array($fieldData)) {
                    // FT.SEARCH 返回的是 [fieldName, value, fieldName, value, ...] 格式
                    // 其中第一个字段通常是 "$" 包含 JSON 数据
                    for ($j = 0; $j < count($fieldData); $j += 2) {
                        if (isset($fieldData[$j]) && $fieldData[$j] === '$') {
                            $jsonData = $fieldData[$j + 1];
                            break;
                        }
                    }
                    // 如果没找到 $ 字段，尝试直接用第一个值
                    if ($jsonData === '' && isset($fieldData[1])) {
                        $jsonData = $fieldData[1];
                    }
                } else {
                    $jsonData = $fieldData;
                }
            }

            $data = json_decode($jsonData, true);
            if (is_array($data)) {
                $documents[] = $this->arrayToDocument($data);
            }
        }

        return $documents;
    }

    /**
     * 关闭连接
     */
    public function __destruct()
    {
        if ($this->isNative && $this->redis instanceof \Redis) {
            try {
                $this->redis->close();
            } catch (\Exception $e) {
                // 忽略关闭错误
            }
        } elseif (is_resource($this->redis)) {
            fclose($this->redis);
        }
    }
}
```

#### 5.8.9 PostgreSQL 向量存储

> 使用原生 PHP PDO（pgsql 驱动）与 PostgreSQL + pgvector 扩展交互，不使用 Doctrine 等 ORM 框架。
> pgvector 是 PostgreSQL 的向量扩展，提供 `vector` 数据类型和 `<=>`（Cosine 距离）操作符。

**实现逻辑**：
- 通信协议：PDO（pgsql 驱动），参数化查询防止 SQL 注入
- 自动建库：`CREATE EXTENSION IF NOT EXISTS vector` + `CREATE TABLE IF NOT EXISTS`
- 文档存储：嵌入向量以 pgvector 格式 `[1,2,3]` 存储在 `vector(N)` 列
- 相似度搜索：`ORDER BY embedding <=> '[1,2,3]'` 服务端计算 Cosine 距离
- 过滤支持：标准 SQL WHERE 子句
- 索引优化：可创建 IVFFlat 或 HNSW 索引加速大规模搜索

```php
<?php

declare(strict_types=1);

namespace PhpLLP\VectorStore;

use PhpLLP\Embeddings\Document;
use PhpLLP\Exception\LLPException;

class PostgresVectorStore extends VectorStoreBase
{
    /** @var \PDO */
    private $pdo;

    /** @var string */
    private $tableName;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        $dsn = $config['dsn'] ?? '';
        $username = $config['username'] ?? '';
        $password = $config['password'] ?? '';
        $this->tableName = $config['table'] ?? 'llp_vectors';
        $vectorDim = $config['vector_dim'] ?? 1536;
        parent::__construct(null, $vectorDim);

        if ($dsn === '') {
            $host = $config['host'] ?? 'localhost';
            $port = $config['port'] ?? 5432;
            $dbname = $config['database'] ?? 'postgres';
            $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
        }

        $this->pdo = new \PDO($dsn, $username, $password);
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

        $this->ensureTableExists();
    }

    public function addDocument(Document $document): void
    {
        $this->addDocuments([$document]);
    }

    public function addDocuments(array $documents): void
    {
        if (empty($documents)) {
            return;
        }

        $values = [];
        $params = [];

        foreach ($documents as $i => $document) {
            $idx = $i * 8;
            $values[] = "(:content{$idx}, :formatted{$idx}, :embedding{$idx}, :source_type{$idx}, :source_name{$idx}, :hash{$idx}, :chunk{$idx}, :metadata{$idx})";
            $params[":content{$idx}"] = $document->content;
            $params[":formatted{$idx}"] = $document->formattedContent;
            $params[":embedding{$idx}"] = '[' . implode(',', $document->embedding ?? []) . ']';
            $params[":source_type{$idx}"] = $document->sourceType;
            $params[":source_name{$idx}"] = $document->sourceName;
            $params[":hash{$idx}"] = $document->hash;
            $params[":chunk{$idx}"] = $document->chunkNumber;
            $params[":metadata{$idx}"] = json_encode($document->metadata ?? []);
        }

        $sql = "INSERT INTO {$this->tableName} (content, formatted_content, embedding, source_type, source_name, hash, chunk_number, metadata)
                VALUES " . implode(',', $values);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    public function similaritySearch(array $embedding, int $k = 4, array $filters = []): array
    {
        $this->validateEmbedding($embedding);
        $embeddingStr = '[' . implode(',', $embedding) . ']';

        $params = [':embedding' => $embeddingStr];
        $conditions = [];

        if (!empty($filters)) {
            foreach ($filters as $key => $value) {
                $conditions[] = "{$key} = :f_{$key}";
                $params[":f_{$key}"] = $value;
            }
        }

        $sql = "SELECT * FROM {$this->tableName}";
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
        $sql .= " ORDER BY embedding <=> :embedding LIMIT :k";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':k', $k, \PDO::PARAM_INT);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $results = [];
        foreach ($rows as $row) {
            $data = [
                'content' => $row['content'],
                'formattedContent' => $row['formatted_content'],
                'embedding' => json_decode($row['embedding'], true),
                'sourceType' => $row['source_type'],
                'sourceName' => $row['source_name'],
                'hash' => $row['hash'],
                'chunkNumber' => (int) $row['chunk_number'],
                'metadata' => json_decode($row['metadata'] ?? '[]', true) ?: [],
            ];
            $results[] = $this->arrayToDocument($data);
        }

        return $results;
    }

    public function delete(): bool
    {
        $this->pdo->exec("TRUNCATE TABLE {$this->tableName}");
        return true;
    }

    public function count(): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM {$this->tableName}");
        return (int) $stmt->fetchColumn();
    }

    /**
     * 确保 pgvector 扩展和数据表存在
     */
    private function ensureTableExists(): void
    {
        $this->pdo->exec('CREATE EXTENSION IF NOT EXISTS vector');

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS {$this->tableName} (
                id SERIAL PRIMARY KEY,
                content TEXT NOT NULL,
                formatted_content TEXT,
                embedding vector({$this->vectorDim}),
                source_type VARCHAR(255),
                source_name VARCHAR(255),
                hash VARCHAR(64),
                chunk_number INTEGER DEFAULT 0,
                metadata JSONB DEFAULT '{}'
            )
        ");

        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_{$this->tableName}_hash ON {$this->tableName} (hash)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_{$this->tableName}_source ON {$this->tableName} (source_type, source_name)");
    }

    /**
     * 创建 HNSW 索引（可选，用于加速大规模搜索）
     *
     * @param string $operatorClass 如：vector_cosine_ops
     */
    public function createVectorIndex(string $operatorClass = 'vector_cosine_ops'): void
    {
        $indexName = "idx_{$this->tableName}_embedding";
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS {$indexName} ON {$this->tableName} USING hnsw (embedding {$operatorClass})");
    }
}
```

#### 5.8.10 Elasticsearch 向量存储

> 通过原生 HTTP REST API 与 Elasticsearch 交互，不依赖 elasticsearch/elasticsearch 官方客户端。
> Elasticsearch 是分布式搜索引擎，通过 dense_vector 字段和 kNN 搜索支持向量检索。

**实现逻辑**：
- 通信协议：HTTP REST API（JSON/NDJSON 编解码）
- 自动建库：`PUT /{index}` 创建索引，配置 dense_vector 映射和 cosine 相似度
- 文档存储：使用 `_bulk` API 的 NDJSON 格式批量写入
- 相似度搜索：`POST /{index}/_search` 使用 kNN 搜索，ES 内部计算 cosine 相似度
- 过滤支持：kNN 搜索的 filter 参数，使用 ES Query DSL
- 批量优化：NDJSON (Newline Delimited JSON) 格式减少序列化开销

```php
<?php

declare(strict_types=1);

namespace PhpLLP\VectorStore;

use PhpLLP\Embeddings\Document;
use PhpLLP\Http\HttpClient;
use PhpLLP\Support\Json;
use PhpLLP\Exception\LLPException;

class ElasticsearchVectorStore extends VectorStoreBase
{
    /** @var HttpClient */
    private $httpClient;

    /** @var string */
    private $baseUrl;

    /** @var string */
    private $indexName;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        $this->baseUrl = rtrim($config['url'] ?? 'http://localhost:9200', '/');
        $this->indexName = $config['index'] ?? 'phpLLP';
        $vectorDim = $config['vector_dim'] ?? 1536;
        parent::__construct(null, $vectorDim);
        $this->httpClient = new HttpClient(['timeout' => $config['timeout'] ?? 30]);

        if ($config['create_index'] ?? true) {
            $this->createIndexIfNotExists();
        }
    }

    public function addDocument(Document $document): void
    {
        $this->addDocuments([$document]);
    }

    public function addDocuments(array $documents): void
    {
        if (empty($documents)) {
            return;
        }

        $body = [];
        foreach ($documents as $document) {
            $id = hash('sha256', $document->getUniqueId());
            $body[] = ['index' => ['_index' => $this->indexName, '_id' => $id]];
            $body[] = $this->documentToArray($document);
        }

        $url = "{$this->baseUrl}/_bulk";
        $this->httpClient->post($url, ['Content-Type' => 'application/x-ndjson'], $this->ndjsonEncode($body));

        // 刷新索引使其立即可搜索
        $this->httpClient->post("{$this->baseUrl}/{$this->indexName}/_refresh");
    }

    public function similaritySearch(array $embedding, int $k = 4, array $filters = []): array
    {
        $this->validateEmbedding($embedding);

        $numCandidates = max(50, $k * 4);

        $searchParams = [
            'index' => $this->indexName,
            'body' => [
                'knn' => [
                    'field' => 'embedding',
                    'query_vector' => $embedding,
                    'k' => $k,
                    'num_candidates' => $numCandidates,
                ],
                'sort' => ['_score' => ['order' => 'desc']],
            ],
        ];

        if (!empty($filters)) {
            $searchParams['body']['knn']['filter'] = $filters;
        }

        $url = "{$this->baseUrl}/{$this->indexName}/_search";
        $response = $this->httpClient->post($url, ['Content-Type' => 'application/json'], $searchParams);
        $data = Json::decode($response->getBody());

        $results = [];
        foreach ($data['hits']['hits'] ?? [] as $hit) {
            $source = $hit['_source'] ?? [];
            $results[] = $this->arrayToDocument($source);
        }

        return $results;
    }

    public function delete(): bool
    {
        $url = "{$this->baseUrl}/{$this->indexName}";
        $this->httpClient->delete($url);
        return true;
    }

    public function count(): int
    {
        $url = "{$this->baseUrl}/{$this->indexName}/_count";
        $response = $this->httpClient->get($url);
        $data = Json::decode($response->getBody());
        return $data['count'] ?? 0;
    }

    /**
     * 创建索引（如不存在）
     */
    private function createIndexIfNotExists(): void
    {
        try {
            $this->httpClient->get("{$this->baseUrl}/{$this->indexName}");
        } catch (\Exception $e) {
            $this->createIndex();
        }
    }

    /**
     * 执行创建索引
     */
    private function createIndex(): void
    {
        $mapping = [
            'mappings' => [
                'properties' => [
                    'content' => ['type' => 'text'],
                    'formattedContent' => ['type' => 'text'],
                    'sourceType' => ['type' => 'keyword'],
                    'sourceName' => ['type' => 'keyword'],
                    'hash' => ['type' => 'keyword'],
                    'chunkNumber' => ['type' => 'integer'],
                    'embedding' => [
                        'type' => 'dense_vector',
                        'element_type' => 'float',
                        'dims' => $this->vectorDim,
                        'index' => true,
                        'similarity' => 'cosine',
                    ],
                    'metadata' => ['type' => 'object', 'dynamic' => true],
                ],
            ],
        ];

        $this->httpClient->put(
            "{$this->baseUrl}/{$this->indexName}",
            ['Content-Type' => 'application/json'],
            $mapping
        );
    }

    /**
     * NDJSON 编码（用于 _bulk API）
     * 每行一个 JSON 对象，行尾必须有换行符
     *
     * @param array<int, array<string, mixed>> $body
     * @return string
     */
    private function ndjsonEncode(array $body): string
    {
        $result = '';
        foreach ($body as $item) {
            $result .= json_encode($item) . "\n";
        }
        return $result;
    }
}
```

#### 5.8.11 Milvus 向量存储（含 Zilliz）

> 通过原生 HTTP REST API（v2.4+）与 Milvus 交互，同时支持 Zilliz Cloud（托管版 Milvus，仅连接参数不同）。
> Milvus 是云原生向量数据库，支持多种索引类型（IVF、HNSW、ANNOY）和度量方式。

**实现逻辑**：
- 通信协议：HTTP REST API（JSON 编解码），路径前缀 `/v2.4/`
- 自动建库：`POST /vectordb/collections/create` 创建集合，配置向量维度和 COSINE 度量
- 文档存储：`POST /vectordb/insert` 批量插入数据
- 相似度搜索：`POST /vectordb/search` 服务端计算向量距离，支持 filter 表达式
- Zilliz 兼容：仅需将 `url` 改为 Zilliz Cloud 地址，添加 `username`/`password` 认证

```php
<?php

declare(strict_types=1);

namespace PhpLLP\VectorStore;

use PhpLLP\Embeddings\Document;
use PhpLLP\Http\HttpClient;
use PhpLLP\Support\Json;
use PhpLLP\Exception\LLPException;

class MilvusVectorStore extends VectorStoreBase
{
    /** @var HttpClient */
    private $httpClient;

    /** @var string */
    private $baseUrl;

    /** @var string */
    private $collectionName;

    /** @var string */
    private $username;

    /** @var string */
    private $password;

    /** @var string */
    private $database;

    /** @var bool */
    private $collectionExists = false;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        $this->baseUrl = rtrim($config['url'] ?? 'http://localhost:19530', '/');
        $this->collectionName = $config['collection'] ?? 'phpLLP';
        $vectorDim = $config['vector_dim'] ?? 1536;
        parent::__construct(null, $vectorDim);
        $this->username = $config['username'] ?? 'root';
        $this->password = $config['password'] ?? '';
        $this->database = $config['database'] ?? 'default';

        $this->httpClient = new HttpClient(['timeout' => $config['timeout'] ?? 30]);

        if ($config['create_collection'] ?? true) {
            $this->createCollectionIfNotExists();
        }
    }

    public function addDocument(Document $document): void
    {
        $this->addDocuments([$document]);
    }

    public function addDocuments(array $documents): void
    {
        if (empty($documents)) {
            return;
        }

        $this->createCollectionIfNotExists();

        $data = [];
        foreach ($documents as $document) {
            $data[] = $this->documentToArray($document) + ['embedding' => $document->embedding];
        }

        $response = $this->sendRequest('POST', '/vectordb/insert', [
            'collectionName' => $this->collectionName,
            'data' => $data,
        ]);

        $this->checkResponse($response);
    }

    public function similaritySearch(array $embedding, int $k = 4, array $filters = []): array
    {
        $this->validateEmbedding($embedding);

        $body = [
            'collectionName' => $this->collectionName,
            'data' => [$embedding],
            'annsField' => 'embedding',
            'topK' => $k,
            'outputFields' => ['content', 'formattedContent', 'sourceType', 'sourceName', 'hash', 'chunkNumber'],
        ];

        if (!empty($filters)) {
            $body['filter'] = $this->buildFilterExpr($filters);
        }

        $response = $this->sendRequest('POST', '/vectordb/search', $body);
        $this->checkResponse($response);

        $results = [];
        $data = $response['data'] ?? [];
        if (isset($data[0])) {
            foreach ($data[0] as $hit) {
                $fields = $hit['entity'] ?? $hit;
                $results[] = $this->arrayToDocument($fields);
            }
        }

        return $results;
    }

    public function delete(): bool
    {
        $this->sendRequest('DELETE', "/vectordb/collections/drop", [
            'collectionName' => $this->collectionName,
        ]);
        $this->collectionExists = false;
        return true;
    }

    public function count(): int
    {
        $response = $this->sendRequest('POST', '/vectordb/query', [
            'collectionName' => $this->collectionName,
            'filter' => '',
            'limit' => 1,
        ]);
        return isset($response['data']) ? count($response['data']) : 0;
    }

    /**
     * 构建 Milvus 过滤表达式
     * Milvus 使用 C++ 表达式语法: field == value and field2 == value2
     *
     * @param array<string, mixed> $filters
     * @return string
     */
    private function buildFilterExpr(array $filters): string
    {
        $parts = [];
        foreach ($filters as $key => $value) {
            if (is_numeric($value)) {
                $parts[] = "{$key} == {$value}";
            } else {
                $parts[] = "{$key} == \"{$value}\"";
            }
        }
        return implode(' and ', $parts);
    }

    /**
     * 创建集合（如不存在）
     */
    private function createCollectionIfNotExists(): void
    {
        if ($this->collectionExists) {
            return;
        }

        try {
            $this->sendRequest('GET', '/vectordb/collections');
            $this->collectionExists = true;
        } catch (\Exception $e) {
            $this->createCollection();
        }
    }

    /**
     * 执行创建集合
     */
    private function createCollection(): void
    {
        $response = $this->sendRequest('POST', '/vectordb/collections/create', [
            'collectionName' => $this->collectionName,
            'dimension' => $this->vectorDim,
            'metricType' => 'COSINE',
            'primaryField' => 'id',
            'vectorField' => 'embedding',
        ]);
        $this->checkResponse($response);
        $this->collectionExists = true;
    }

    /**
     * 发送 HTTP 请求
     *
     * @param string $method
     * @param string $path
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function sendRequest(string $method, string $path, array $body = []): array
    {
        $url = "{$this->baseUrl}/v2.4" . $path;

        $headers = [
            'Authorization' => 'Bearer ' . $this->username . ':' . $this->password,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        $response = $this->httpClient->request($method, $url, $headers, $body);
        return Json::decode($response->getBody());
    }

    /**
     * 检查 API 响应错误
     *
     * @param array<string, mixed> $response
     * @throws LLPException
     */
    private function checkResponse(array $response): void
    {
        $code = $response['code'] ?? 0;
        if ($code !== 0) {
            $msg = $response['message'] ?? 'Unknown error';
            throw new LLPException("Milvus API Error [{$code}]: {$msg}");
        }
    }
}
```

#### 5.8.12 ChromaDB 向量存储

> 通过原生 HTTP REST API 与 ChromaDB 交互，不依赖 codewithkyrian/chromadb 客户端库。
> ChromaDB 是轻量级嵌入式向量数据库，支持元数据过滤和自动嵌入。

**实现逻辑**：
- 通信协议：HTTP REST API（JSON 编解码），支持多租户/多数据库架构
- 自动建库：`POST /api/v2/tenants/{tenant}/databases/{db}/collections` 创建集合
- 文档存储：`POST /api/v2/.../collections/{name}/add` 批量添加（ids、embeddings、metadatas、documents 分离）
- 相似度搜索：`POST /api/v2/.../collections/{name}/query` 查询嵌入向量
- 过滤支持：`where` 参数传递元数据过滤条件
- 认证：可选 Bearer Token 认证

```php
<?php

declare(strict_types=1);

namespace PhpLLP\VectorStore;

use PhpLLP\Embeddings\Document;
use PhpLLP\Http\HttpClient;
use PhpLLP\Support\Json;
use PhpLLP\Exception\LLPException;

class ChromaDBVectorStore extends VectorStoreBase
{
    /** @var HttpClient */
    private $httpClient;

    /** @var string */
    private $baseUrl;

    /** @var string */
    private $collectionName;

    /** @var string */
    private $tenant;

    /** @var string */
    private $database;

    /** @var string|null */
    private $authToken;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        $this->baseUrl = rtrim($config['url'] ?? 'http://localhost:8000', '/');
        $this->collectionName = $config['collection'] ?? 'phpLLP';
        $this->tenant = $config['tenant'] ?? 'default_tenant';
        $this->database = $config['database'] ?? 'default_database';
        $this->authToken = $config['auth_token'] ?? null;
        $vectorDim = $config['vector_dim'] ?? 1536;
        parent::__construct(null, $vectorDim);

        $this->httpClient = new HttpClient(['timeout' => $config['timeout'] ?? 30]);

        if ($config['create_collection'] ?? true) {
            $this->createCollectionIfNotExists();
        }
    }

    public function addDocument(Document $document): void
    {
        $this->addDocuments([$document]);
    }

    public function addDocuments(array $documents): void
    {
        if (empty($documents)) {
            return;
        }

        $ids = [];
        $embeddings = [];
        $metadatas = [];
        $contents = [];

        foreach ($documents as $document) {
            $ids[] = $this->generateId($document);
            $embeddings[] = $document->embedding;
            $metadatas[] = [
                'hash' => $document->hash,
                'sourceName' => $document->sourceName,
                'sourceType' => $document->sourceType,
                'chunkNumber' => $document->chunkNumber,
            ];
            $contents[] = $document->content;
        }

        $url = "{$this->baseUrl}/api/v2/tenants/{$this->tenant}/databases/{$this->database}/collections/{$this->collectionName}/add";
        $this->httpClient->post($url, $this->getDefaultHeaders(), [
            'ids' => $ids,
            'embeddings' => $embeddings,
            'metadatas' => $metadatas,
            'documents' => $contents,
        ]);
    }

    public function similaritySearch(array $embedding, int $k = 4, array $filters = []): array
    {
        $this->validateEmbedding($embedding);

        $url = "{$this->baseUrl}/api/v2/tenants/{$this->tenant}/databases/{$this->database}/collections/{$this->collectionName}/query";

        $body = [
            'query_embeddings' => [$embedding],
            'n_results' => $k,
            'include' => ['metadatas', 'documents'],
        ];

        if (!empty($filters)) {
            $body['where'] = $filters;
        }

        $response = $this->httpClient->post($url, $this->getDefaultHeaders(), $body);
        $data = Json::decode($response->getBody());

        $results = [];
        $documents = $data['documents'] ?? [];
        $metadatas = $data['metadatas'] ?? [];

        if (!empty($documents) && isset($documents[0])) {
            foreach ($documents[0] as $i => $content) {
                $dataArr = [
                    'content' => $content,
                    'formattedContent' => null,
                    'embedding' => null,
                    'sourceType' => '',
                    'sourceName' => '',
                    'hash' => '',
                    'chunkNumber' => 0,
                    'metadata' => [],
                ];

                if (isset($metadatas[0][$i])) {
                    $meta = $metadatas[0][$i];
                    $dataArr['hash'] = $meta['hash'] ?? '';
                    $dataArr['sourceName'] = $meta['sourceName'] ?? '';
                    $dataArr['sourceType'] = $meta['sourceType'] ?? '';
                    $dataArr['chunkNumber'] = (int) ($meta['chunkNumber'] ?? 0);
                    $dataArr['metadata'] = $meta;
                }

                $results[] = $this->arrayToDocument($dataArr);
            }
        }

        return $results;
    }

    public function delete(): bool
    {
        $url = "{$this->baseUrl}/api/v2/tenants/{$this->tenant}/databases/{$this->database}/collections/{$this->collectionName}";
        $this->httpClient->delete($url, $this->getDefaultHeaders());
        return true;
    }

    public function count(): int
    {
        $url = "{$this->baseUrl}/api/v2/tenants/{$this->tenant}/databases/{$this->database}/collections/{$this->collectionName}/count";
        $response = $this->httpClient->get($url, $this->getDefaultHeaders());
        $data = Json::decode($response->getBody());
        return $data['count'] ?? 0;
    }

    /**
     * 创建集合（如不存在）
     */
    private function createCollectionIfNotExists(): void
    {
        try {
            $url = "{$this->baseUrl}/api/v2/tenants/{$this->tenant}/databases/{$this->database}/collections/{$this->collectionName}";
            $this->httpClient->get($url, $this->getDefaultHeaders());
        } catch (\Exception $e) {
            $this->createCollection();
        }
    }

    /**
     * 执行创建集合
     */
    private function createCollection(): void
    {
        $url = "{$this->baseUrl}/api/v2/tenants/{$this->tenant}/databases/{$this->database}/collections";
        $this->httpClient->post($url, $this->getDefaultHeaders(), [
            'name' => $this->collectionName,
        ]);
    }

    /**
     * 获取默认请求头
     *
     * @return array<string, string>
     */
    private function getDefaultHeaders(): array
    {
        $headers = ['Content-Type' => 'application/json'];
        if ($this->authToken !== null) {
            $headers['Authorization'] = 'Bearer ' . $this->authToken;
        }
        return $headers;
    }

    /**
     * 生成文档 ID
     *
     * @param Document $document
     * @return string
     */
    private function generateId(Document $document): string
    {
        return hash('sha256', $document->content . $document->getUniqueId());
    }
}
```

#### 5.8.13 AstraDB 向量存储

> 通过原生 HTTP REST API 与 DataStax Astra DB（Astra Vector Search）交互。
> Astra DB 是云原生分布式向量数据库，支持向量搜索与全文检索的混合查询。

**实现逻辑**：
- 通信协议：HTTP REST API（JSON 编解码），使用 Astra JSON API
- 自动建库：`POST /api/json/v1/{keyspace}` 发送 `createCollection` 命令
- 文档存储：`insertMany` 命令批量插入，`$vector` 字段存储嵌入向量
- 相似度搜索：`find` + `sort: {$vector: query}` 执行 ANN 搜索
- 认证：使用 `Token` header 传递 Astra DB 应用 Token
- 环境变量：支持 `ASTRADB_ENDPOINT` 和 `ASTRADB_TOKEN` 环境变量

```php
<?php

declare(strict_types=1);

namespace PhpLLP\VectorStore;

use PhpLLP\Embeddings\Document;
use PhpLLP\Http\HttpClient;
use PhpLLP\Support\Json;
use PhpLLP\Exception\LLPException;

class AstraDBVectorStore extends VectorStoreBase
{
    /** @var HttpClient */
    private $httpClient;

    /** @var string */
    private $endpoint;

    /** @var string */
    private $token;

    /** @var string */
    private $keySpace;

    /** @var string */
    private $collectionName;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        $this->endpoint = rtrim($config['endpoint'] ?? '', '/');
        $this->token = $config['token'] ?? '';
        $this->keySpace = $config['keyspace'] ?? 'default_keyspace';
        $this->collectionName = $config['collection'] ?? 'default_collection';
        $vectorDim = $config['vector_dim'] ?? 1536;
        parent::__construct(null, $vectorDim);

        if ($this->endpoint === '') {
            $this->endpoint = getenv('ASTRADB_ENDPOINT') ?: '';
        }
        if ($this->token === '') {
            $this->token = getenv('ASTRADB_TOKEN') ?: '';
        }

        if ($this->endpoint === '' || $this->token === '') {
            throw new LLPException('必须提供 AstraDB endpoint 和 token');
        }

        $this->httpClient = new HttpClient(['timeout' => $config['timeout'] ?? 30]);

        if ($config['create_collection'] ?? true) {
            $this->createCollectionIfNotExists();
        }
    }

    public function addDocument(Document $document): void
    {
        $this->addDocuments([$document]);
    }

    public function addDocuments(array $documents): void
    {
        if (empty($documents)) {
            return;
        }

        $docs = [];
        foreach ($documents as $document) {
            $arr = $this->documentToArray($document);
            $docs[] = [
                '_id' => $this->generateId($document),
                '$vector' => $document->embedding,
            ] + $arr;
        }

        $body = ['insertMany' => ['documents' => $docs]];
        $this->sendRequest('POST', $this->collectionName, $body);
    }

    public function similaritySearch(array $embedding, int $k = 4, array $filters = []): array
    {
        $this->validateEmbedding($embedding);

        $body = [
            'find' => [
                'sort' => ['$vector' => $embedding],
                'projection' => [
                    '_id' => 1,
                    'content' => 1,
                    'formattedContent' => 1,
                    'sourceType' => 1,
                    'sourceName' => 1,
                    'hash' => 1,
                    'chunkNumber' => 1,
                ],
                'options' => [
                    'includeSimilarity' => false,
                    'includeSortVector' => false,
                    'limit' => $k,
                ],
            ],
        ];

        if (!empty($filters)) {
            $body['find']['filter'] = $filters;
        }

        $response = $this->sendRequest('POST', $this->collectionName, $body);
        $data = $response['data']['documents'] ?? [];

        $results = [];
        foreach ($data as $row) {
            $row['embedding'] = $row['$vector'] ?? null;
            $results[] = $this->arrayToDocument($row);
        }

        return $results;
    }

    public function delete(): bool
    {
        $body = ['deleteCollection' => ['name' => $this->collectionName]];
        $this->sendRequest('POST', '', $body);
        return true;
    }

    public function count(): int
    {
        $body = ['findCollections' => ['options' => ['explain' => true]]];
        $response = $this->sendRequest('POST', '', $body);

        $collections = $response['status']['collections'] ?? [];
        foreach ($collections as $collection) {
            if ($collection['name'] === $this->collectionName) {
                return $collection['count'] ?? 0;
            }
        }
        return 0;
    }

    /**
     * 创建集合（如不存在）
     */
    private function createCollectionIfNotExists(): void
    {
        $body = ['findCollections' => ['options' => ['explain' => true]]];
        try {
            $response = $this->sendRequest('POST', '', $body);
            $collections = $response['status']['collections'] ?? [];
            foreach ($collections as $collection) {
                if ($collection['name'] === $this->collectionName) {
                    return;
                }
            }
        } catch (\Exception $e) {
            // Ignore and create
        }
        $this->createCollection();
    }

    /**
     * 执行创建集合
     */
    private function createCollection(): void
    {
        $body = [
            'createCollection' => [
                'name' => $this->collectionName,
                'options' => [
                    'vector' => [
                        'dimension' => $this->vectorDim,
                        'metric' => 'cosine',
                    ],
                ],
            ],
        ];
        $this->sendRequest('POST', '', $body);
    }

    /**
     * 发送 HTTP 请求
     *
     * @param string $method
     * @param string $path
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function sendRequest(string $method, string $path, array $body): array
    {
        $url = $this->endpoint . '/api/json/v1/' . $this->keySpace . '/' . $path;

        $headers = [
            'Token' => $this->token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        if ($method === 'POST') {
            $response = $this->httpClient->post($url, $headers, $body);
        } else {
            $response = $this->httpClient->get($url, $headers);
        }

        $data = Json::decode($response->getBody());

        if (isset($data['errors']) || isset($data['error'])) {
            throw new LLPException('AstraDB API 错误: ' . json_encode($data));
        }

        return $data;
    }

    /**
     * 生成文档 ID
     *
     * @param Document $document
     * @return string
     */
    private function generateId(Document $document): string
    {
        return hash('sha256', $document->content . $document->getUniqueId());
    }
}
```

#### 5.8.14 SQLite 向量存储

> 使用原生 PDO SQLite 扩展实现，无需任何第三方包。通过 PHP 原生实现的余弦相似度计算完成向量搜索，数据持久化到本地文件。
> 适用于轻量级应用、嵌入式场景或单机开发测试环境。

**实现逻辑**：
- 存储引擎：PDO SQLite，嵌入数据库，数据以 JSON 格式存储嵌入向量
- 表结构：`id`（主键自增）、`content`、`formatted_content`、`embedding`（JSON）、`source_type`、`source_name`、`hash`、`chunk_number`、`metadata`（JSON）
- 相似度搜索：SQL 过滤 + PHP 端余弦距离计算，取 top-k 最近邻
- 距离度量：支持可插拔 `DistanceInterface`（默认 `CosineDistance`）
- 性能说明：全量加载数据到内存计算距离，百万级数据建议使用专用向量数据库

```php
<?php

declare(strict_types=1);

namespace PhpLLP\VectorStore;

use PhpLLP\Embeddings\Distances\CosineDistance;
use PhpLLP\Embeddings\Distances\DistanceInterface;
use PhpLLP\Embeddings\Document;
use PhpLLP\Exception\LLPException;

class SQLiteVectorStore extends VectorStoreBase
{
    /** @var \PDO */
    private $pdo;

    /** @var string */
    private $tableName;

    /** @var DistanceInterface */
    private $distance;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        $dbPath = $config['database'] ?? './vectors.sqlite';
        $this->tableName = $config['table'] ?? 'llp_vectors';
        $vectorDim = $config['vector_dim'] ?? 1536;
        parent::__construct(null, $vectorDim);
        $this->distance = $config['distance'] ? new $config['distance']() : new CosineDistance();

        $dsn = "sqlite:" . $dbPath;
        $this->pdo = new \PDO($dsn);
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $this->ensureTableExists();
    }

    public function addDocument(Document $document): void
    {
        $this->addDocuments([$document]);
    }

    public function addDocuments(array $documents): void
    {
        if (empty($documents)) {
            return;
        }

        $values = [];
        $params = [];

        foreach ($documents as $i => $document) {
            $idx = $i * 9;
            $values[] = "(:content{$idx}, :formatted{$idx}, :embedding{$idx}, :source_type{$idx}, :source_name{$idx}, :hash{$idx}, :chunk{$idx}, :metadata{$idx})";
            $params[":content{$idx}"] = $document->content;
            $params[":formatted{$idx}"] = $document->formattedContent;
            $params[":embedding{$idx}"] = json_encode($document->embedding ?? []);
            $params[":source_type{$idx}"] = $document->sourceType;
            $params[":source_name{$idx}"] = $document->sourceName;
            $params[":hash{$idx}"] = $document->hash;
            $params[":chunk{$idx}"] = $document->chunkNumber;
            $params[":metadata{$idx}"] = json_encode($document->metadata ?? []);
        }

        $sql = "INSERT INTO {$this->tableName} (content, formatted_content, embedding, source_type, source_name, hash, chunk_number, metadata)
                VALUES " . implode(',', $values);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    public function similaritySearch(array $embedding, int $k = 4, array $filters = []): array
    {
        $this->validateEmbedding($embedding);

        $sql = "SELECT * FROM {$this->tableName}";
        $params = [];
        $conditions = [];

        foreach ($filters as $key => $value) {
            $conditions[] = "{$key} = :f_{$key}";
            $params[":f_{$key}"] = $value;
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        $sql .= " ORDER BY id DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $documents = [];
        foreach ($rows as $row) {
            $doc = $this->arrayToDocument([
                'content' => $row['content'],
                'formattedContent' => $row['formatted_content'],
                'embedding' => json_decode($row['embedding'], true),
                'sourceType' => $row['source_type'],
                'sourceName' => $row['source_name'],
                'hash' => $row['hash'],
                'chunkNumber' => (int) $row['chunk_number'],
                'metadata' => json_decode($row['metadata'] ?? '[]', true) ?: [],
            ]);
            $documents[] = $doc;
        }

        return $this->computeRankedDocuments($embedding, $documents, $k, $filters);
    }

    public function delete(): bool
    {
        $this->pdo->exec("DELETE FROM {$this->tableName}");
        return true;
    }

    public function count(): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM {$this->tableName}");
        return (int) $stmt->fetchColumn();
    }

    /**
     * 创建表结构（如不存在）
     */
    private function ensureTableExists(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS {$this->tableName} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                content TEXT NOT NULL,
                formatted_content TEXT,
                embedding TEXT NOT NULL,
                source_type VARCHAR(255),
                source_name VARCHAR(255),
                hash VARCHAR(64),
                chunk_number INTEGER DEFAULT 0,
                metadata TEXT DEFAULT '{}'
            )
        ");

        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_{$this->tableName}_hash ON {$this->tableName} (hash)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_{$this->tableName}_source ON {$this->tableName} (source_type, source_name)");
    }
}
```

### 5.9 Question Answering / 语义搜索模块

#### 5.9.1 QueryTransformer 接口

```php
<?php

declare(strict_types=1);

namespace PhpLLP\Query\Transformer;

interface QueryTransformer
{
    /**
     * 将用户查询转换为一个或多个子查询
     *
     * @param string $query
     * @return string[]
     */
    public function transformQuery(string $query): array;
}
```

#### 5.9.2 DocumentsTransformer 接口

```php
<?php

declare(strict_types=1);

namespace PhpLLP\Query\Transformer;

use PhpLLP\Embeddings\Document;

interface DocumentsTransformer
{
    /**
     * 对检索到的文档进行转换/重排序
     *
     * @param string[] $queries
     * @param Document[] $documents
     * @return Document[]
     */
    public function transformDocuments(array $queries, array $documents): array;
}
```

#### 5.9.3 内置 Transformer

```php
<?php

declare(strict_types=1);

namespace PhpLLP\Query\Transformer;

/**
 * 恒等查询转换器 - 原样返回
 */
class IdentityTransformer implements QueryTransformer
{
    public function transformQuery(string $query): array
    {
        return [$query];
    }
}

/**
 * 恒等文档转换器 - 原样返回
 */
class IdentityDocumentsTransformer implements DocumentsTransformer
{
    public function transformDocuments(array $queries, array $documents): array
    {
        return $documents;
    }
}

/**
 * 多查询转换器 - 通过 LLM 生成多个不同角度的子查询
 */
class MultiQueryTransformer implements QueryTransformer
{
    /** @var \PhpLLP\Contracts\ChatInterface */
    private $chat;

    /** @var int */
    private $numQueries;

    /**
     * @param \PhpLLP\Contracts\ChatInterface $chat
     * @param int $numQueries
     */
    public function __construct($chat, int $numQueries = 3)
    {
        $this->chat = $chat;
        $this->numQueries = $numQueries;
    }

    public function transformQuery(string $query): array
    {
        $prompt = "你是一个信息检索专家。请将以下用户问题转换为 {$this->numQueries} 个不同角度的子查询，用换行分隔：\n\n" .
            "原始问题: {$query}\n\n" .
            "子查询:";

        $response = $this->chat->generateText($prompt);
        $queries = array_filter(array_map('trim', explode("\n", $response)));

        return array_merge([$query], $queries);
    }
}
```

#### 5.9.4 QuestionAnswering（RAG 核心）

```php
<?php

declare(strict_types=1);

namespace PhpLLP\Query;

use PhpLLP\Contracts\VectorStoreInterface;
use PhpLLP\Contracts\EmbeddingInterface;
use PhpLLP\Contracts\ChatInterface;
use PhpLLP\Embeddings\Document;
use PhpLLP\Query\Transformer\QueryTransformer;
use PhpLLP\Query\Transformer\DocumentsTransformer;
use PhpLLP\Query\Transformer\IdentityTransformer;
use PhpLLP\Query\Transformer\IdentityDocumentsTransformer;

class QuestionAnswering
{
    /** @var Document[] */
    private $retrievedDocs = [];

    /** @var string */
    public $systemMessageTemplate = 'Use the following pieces of context to answer the question of the user. If you don\'t know the answer, just say that you don\'t know, don\'t try to make up an answer.

{context}.';

    /** @var VectorStoreInterface */
    private $vectorStore;

    /** @var EmbeddingInterface */
    private $embeddingGenerator;

    /** @var ChatInterface */
    private $chat;

    /** @var QueryTransformer */
    private $queryTransformer;

    /** @var DocumentsTransformer */
    private $documentsTransformer;

    /**
     * @param VectorStoreInterface $vectorStore
     * @param EmbeddingInterface $embeddingGenerator
     * @param ChatInterface $chat
     * @param QueryTransformer|null $queryTransformer
     * @param DocumentsTransformer|null $documentsTransformer
     */
    public function __construct(
        VectorStoreInterface $vectorStore,
        EmbeddingInterface $embeddingGenerator,
        ChatInterface $chat,
        QueryTransformer $queryTransformer = null,
        DocumentsTransformer $documentsTransformer = null
    ) {
        $this->vectorStore = $vectorStore;
        $this->embeddingGenerator = $embeddingGenerator;
        $this->chat = $chat;
        $this->queryTransformer = $queryTransformer ?: new IdentityTransformer();
        $this->documentsTransformer = $documentsTransformer ?: new IdentityDocumentsTransformer();
    }

    /**
     * 回答问题
     *
     * @param string $question
     * @param int $k
     * @param array<string, mixed> $filters
     * @return string
     */
    public function answer(string $question, int $k = 4, array $filters = []): string
    {
        $systemMessage = $this->searchAndCreateSystemMessage($question, $k, $filters);
        $this->chat->setSystemMessage($systemMessage);

        return $this->chat->generateText($question);
    }

    /**
     * 流式回答问题
     *
     * @param string $question
     * @param int $k
     * @param array<string, mixed> $filters
     * @return \Generator
     */
    public function answerStream(string $question, int $k = 4, array $filters = []): \Generator
    {
        $systemMessage = $this->searchAndCreateSystemMessage($question, $k, $filters);
        $this->chat->setSystemMessage($systemMessage);

        return $this->chat->generateStream($question);
    }

    /**
     * 获取检索到的文档
     *
     * @return Document[]
     */
    public function getRetrievedDocuments(): array
    {
        return $this->retrievedDocs;
    }

    /**
     * 获取总 token 使用量
     *
     * @return int
     */
    public function getTotalTokens(): int
    {
        return $this->chat->getTotalTokens();
    }

    /**
     * @param string $question
     * @param int $k
     * @param array<string, mixed> $filters
     * @return string
     */
    private function searchAndCreateSystemMessage(string $question, int $k, array $filters): string
    {
        $queries = $this->queryTransformer->transformQuery($question);

        $this->retrievedDocs = [];

        foreach ($queries as $query) {
            $embedding = $this->embeddingGenerator->embedText($query);
            $docs = $this->vectorStore->similaritySearch($embedding, $k, $filters);

            foreach ($docs as $doc) {
                $this->retrievedDocs[md5($doc->content)] = $doc;
            }
        }

        $this->retrievedDocs = array_values($this->retrievedDocs);
        $this->retrievedDocs = $this->documentsTransformer->transformDocuments($queries, $this->retrievedDocs);

        $context = '';
        $i = 0;
        foreach ($this->retrievedDocs as $document) {
            if ($i >= $k) {
                break;
            }
            $i++;
            $context .= $document->content . ' ';
        }

        return str_replace('{context}', $context, $this->systemMessageTemplate);
    }
}
```

#### 5.9.5 SemanticSearch（语义搜索）

```php
<?php

declare(strict_types=1);

namespace PhpLLP\Query;

use PhpLLP\Contracts\VectorStoreInterface;
use PhpLLP\Contracts\EmbeddingInterface;
use PhpLLP\Embeddings\Document;

class SemanticSearch
{
    /** @var VectorStoreInterface */
    private $vectorStore;

    /** @var EmbeddingInterface */
    private $embeddingGenerator;

    /**
     * @param VectorStoreInterface $vectorStore
     * @param EmbeddingInterface $embeddingGenerator
     */
    public function __construct(
        VectorStoreInterface $vectorStore,
        EmbeddingInterface $embeddingGenerator
    ) {
        $this->vectorStore = $vectorStore;
        $this->embeddingGenerator = $embeddingGenerator;
    }

    /**
     * 语义搜索
     *
     * @param string $query
     * @param int $k
     * @param array<string, mixed> $filters
     * @return Document[]
     */
    public function search(string $query, int $k = 4, array $filters = []): array
    {
        $embedding = $this->embeddingGenerator->embedText($query);
        return $this->vectorStore->similaritySearch($embedding, $k, $filters);
    }
}
```

### 5.10 Support 辅助工具类

#### 5.10.1 Json

```php
<?php

declare(strict_types=1);

namespace PhpLLP\Support;

use PhpLLP\Exception\LLPException;

class Json
{
    /**
     * 解码 JSON 字符串
     *
     * @param string $json
     * @param bool $assoc
     * @param int $depth
     * @return mixed
     */
    public static function decode(string $json, bool $assoc = true, int $depth = 512)
    {
        $result = json_decode($json, $assoc, $depth);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new LLPException('JSON 解码错误: ' . json_last_error_msg());
        }

        return $result;
    }

    /**
     * 编码为 JSON 字符串
     *
     * @param mixed $data
     * @param int $options
     * @return string
     */
    public static function encode($data, int $options = 0): string
    {
        $result = json_encode($data, $options);

        if ($result === false) {
            throw new LLPException('JSON 编码错误: ' . json_last_error_msg());
        }

        return $result;
    }
}
```

#### 5.10.2 Str

```php
<?php

declare(strict_types=1);

namespace PhpLLP\Support;

class Str
{
    /**
     * 字符串是否包含子串
     *
     * @param string $haystack
     * @param string $needle
     * @return bool
     */
    public static function contains(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }

    /**
     * 字符串是否以给定子串开头
     *
     * @param string $haystack
     * @param string $needle
     * @return bool
     */
    public static function startsWith(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) === 0;
    }

    /**
     * 字符串是否以给定子串结尾
     *
     * @param string $haystack
     * @param string $needle
     * @return bool
     */
    public static function endsWith(string $haystack, string $needle): bool
    {
        $len = strlen($needle);
        return $needle === '' || substr($haystack, -$len) === $needle;
    }

    /**
     * 生成随机字符串
     *
     * @param int $length
     * @return string
     */
    public static function random(int $length = 32): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $result;
    }

    /**
     * 将字符串转换为 snake_case
     *
     * @param string $input
     * @return string
     */
    public static function snakeCase(string $input): string
    {
        $pattern = '/[a-z][A-Z]/';
        $result = preg_replace($pattern, '$0', $input);
        return strtolower(str_replace('_', '', $result));
    }

    /**
     * 将字符串转换为 camelCase
     *
     * @param string $input
     * @return string
     */
    public static function camelCase(string $input): string
    {
        $result = ucwords(str_replace(['-', '_'], ' ', $input));
        return ltrim(str_replace(' ', '', $result));
    }
}
```

#### 5.10.3 Arr

```php
<?php

declare(strict_types=1);

namespace PhpLLP\Support;

class Arr
{
    /**
     * 从数组中获取值（支持点号分隔的键名）
     *
     * @param array<string, mixed> $array
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get(array $array, string $key, $default = null)
    {
        if (isset($array[$key])) {
            return $array[$key];
        }

        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }
            $array = $array[$segment];
        }

        return $array;
    }

    /**
     * 设置数组中的值（支持点号分隔的键名）
     *
     * @param array<string, mixed> $array
     * @param string $key
     * @param mixed $value
     */
    public static function set(array &$array, string $key, $value): void
    {
        $keys = explode('.', $key);
        $current = &$array;

        foreach ($keys as $i => $segment) {
            if (count($keys) === 1) {
                break;
            }

            unset($keys[$i]);

            if (!isset($current[$segment]) || !is_array($current[$segment])) {
                $current[$segment] = [];
            }

            $current = &$current[$segment];
        }

        $current[array_shift($keys)] = $value;
    }

    /**
     * 检查键是否存在（支持点号分隔的键名）
     *
     * @param array<string, mixed> $array
     * @param string $key
     * @return bool
     */
    public static function has(array $array, string $key): bool
    {
        if (array_key_exists($key, $array)) {
            return true;
        }

        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return false;
            }
            $array = $array[$segment];
        }

        return true;
    }

    /**
     * 从数组中移除值
     *
     * @param array<string, mixed> $array
     * @param string $key
     */
    public static function forget(array &$array, string $key): void
    {
        if (array_key_exists($key, $array)) {
            unset($array[$key]);
            return;
        }

        $keys = explode('.', $key);
        $current = &$array;

        while (count($keys) > 1) {
            $segment = array_shift($keys);
            if (!isset($current[$segment]) || !is_array($current[$segment])) {
                return;
            }
            $current = &$current[$segment];
        }

        unset($current[array_shift($keys)]);
    }
}
```

### 5.11 异常类定义

```php
<?php

declare(strict_types=1);

namespace PhpLLP\Exception;

class LLPException extends \Exception
{
}
```

```php
<?php

declare(strict_types=1);

namespace PhpLLP\Exception;

class HttpException extends LLPException
{
    /** @var int */
    private $statusCode;

    /** @var string */
    private $responseBody;

    /**
     * @param string $message
     * @param int $statusCode
     * @param string $responseBody
     * @param \Throwable|null $previous
     */
    public function __construct(
        string $message = '',
        int $statusCode = 0,
        string $responseBody = '',
        ?\Throwable $previous = null
    ) {
        $this->statusCode = $statusCode;
        $this->responseBody = $responseBody;
        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getResponseBody(): string
    {
        return $this->responseBody;
    }
}
```

```php
<?php

declare(strict_types=1);

namespace PhpLLP\Exception;

class ConfigException extends LLPException
{
}
```

```php
<?php

declare(strict_types=1);

namespace PhpLLP\Exception;

class ModelException extends LLPException
{
}
```

---

## 6. composer.json 设计

### 6.1 完整配置

```json
{
    "name": "php-llp/php-llp",
    "description": "A pure PHP library for building LLM applications. Zero dependencies. Supports PHP 7.4+ with adaptive mode for 8.1+.",
    "type": "library",
    "license": "MIT",
    "keywords": [
        "php",
        "llm",
        "llp",
        "chatgpt",
        "embeddings",
        "vector-store",
        "rag",
        "anthropic",
        "mistral",
        "ollama",
        "streaming",
        "tool-calling"
    ],
    "require": {
        "php": ">=7.4",
        "ext-curl": "*",
        "ext-json": "*",
        "ext-mbstring": "*"
    },
    "suggest": {
        "ext-pdo": "Required for PostgreSQL / SQLite vector stores (bundled with PHP by default)",
        "ext-pgsql": "Required for PostgreSQL vector store",
        "ext-sqlite": "Required for SQLite vector store (bundled with PHP by default)",
        "ext-redis": "Required for native Redis client; otherwise uses raw socket connection",
        "ext-gd": "Required for Image generation validation",
        "ext-fileinfo": "Recommended for file type detection in Audio module",
        "ext-sockets": "Optional: enables raw socket connections for Redis/Elasticsearch (if ext-redis unavailable)"
    },
    "autoload": {
        "psr-4": {
            "PhpLLP\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "PhpLLP\\Tests\\": "tests/"
        }
    },
    "minimum-stability": "stable",
    "prefer-stable": true,
    "config": {
        "sort-packages": true,
        "allow-plugins": {
            "php-llp/*": true
        }
    },
    "scripts": {
        "test": "phpunit",
        "test-stream": "phpunit --filter StreamTest",
        "test-security": "phpunit --filter SecurityTest"
    }
}
```

### 6.2 依赖说明

#### 必需扩展（require）

| 扩展 | 用途 | 说明 |
|------|------|------|
| `ext-curl` | HTTP 请求 | 所有 API 调用（Chat、Image、Audio、Embeddings）均通过 cURL 实现 |
| `ext-json` | JSON 编解码 | API 请求/响应序列化，PHP 7.4+ 自带 |
| `ext-mbstring` | 多字节字符串处理 | 文本操作、Token 计数、文本截断等 |

> 注: `ext-mbstring` 在 PHP 7.4+ 中通常已默认启用。若不可用，核心功能仍可工作（降级为 `strlen`/`substr`），但中文等多字节文本处理可能异常。

#### 建议扩展（suggest）

| 扩展 | 适用场景 | 回退方案 |
|------|----------|----------|
| `ext-pdo` | PostgreSQL / SQLite 向量存储 | 无回退，需手动安装 |
| `ext-pgsql` | PostgreSQL 向量存储 | 无回退 |
| `ext-sqlite` | SQLite 向量存储 | 无回退 |
| `ext-redis` | Redis 向量存储 | 使用原生 socket 协议实现 RESP 通信 |
| `ext-gd` | 图片格式验证 | 使用 `getimagesize()` 替代 |
| `ext-fileinfo` | 文件类型检测 | 使用 `pathinfo()` + 扩展名 |

### 6.3 PHP 版本兼容策略

```
PHP 7.4          →  兼容模式：常量类代替 enum，strpos 代替 str_contains，gettype 代替 get_debug_type
PHP 8.0          →  启用 str_contains / str_starts_with / get_debug_type
PHP 8.1+         →  可选启用 enum / readonly / Fiber（通过 PhpVersion::is81Plus() 检测）
```

**核心原则**:
- 主实现始终兼容 PHP 7.4，所有 8.1+ 特性通过 `PhpVersion` 工具类条件启用
- 不破坏 API 兼容性，8.1+ 环境下自动获得性能优化和类型安全增强
- 用户无需关心版本差异，代码自动适配

### 6.4 安全声明

> 本包遵循以下安全原则:
>
> 1. **零 eval()**: 所有表达式求值通过安全解析器实现
> 2. **输入过滤**: 所有外部输入经过严格白名单验证
> 3. **SQL 注入防护**: 向量存储使用 PDO Prepared Statement
> 4. **SSRF 防护**: HTTP 客户端支持代理白名单和 URL 验证
> 5. **敏感信息保护**: API Key 不出现在日志和异常消息中
> 6. **依赖扫描**: 虽然本包零第三方依赖，但仍建议定期运行 `composer audit`

---

## 7. 开发路线图

### 7.1 阶段划分

| 阶段 | 内容 | 预估工时 |
|------|------|----------|
| **Phase 1** | 基础设施（HttpClient、异常、Support 类、Contracts） | 1 天 |
| **Phase 2** | Chat 模块（Message、ChatRole、4 个 Provider） | 2 天 |
| **Phase 3** | Embeddings 模块（Document、距离度量、嵌入生成器） | 2 天 |
| **Phase 4** | VectorStore 模块（FileSystem、PostgreSQL、SQLite） | 2 天 |
| **Phase 5** | VectorStore 扩展（Qdrant、Redis、Elasticsearch、Milvus、ChromaDB、AstraDB） | 3 天 |
| **Phase 6** | Tools 模块（FunctionInfo、FunctionBuilder、ToolExecutor） | 2 天 |
| **Phase 7** | Image 和 Audio 模块 | 1 天 |
| **Phase 8** | Question Answering / SemanticSearch | 2 天 |
| **Phase 9** | 统一入口 LLP 类整合 | 1 天 |
| **Phase 10** | 测试、文档、示例 | 3 天 |

### 7.2 优先级排序

1. **P0（核心）**：Phase 1-3，确保基础聊天和嵌入功能可用
2. **P1（重要）**：Phase 4-6，向量存储和工具调用
3. **P2（增强）**：Phase 7-8，图像/音频/RAG
4. **P3（完善）**：Phase 9-10，整合和测试

---

## 8. 测试策略

### 8.1 测试分层

| 层级 | 说明 | 工具 |
|------|------|------|
| Unit | 单个类/方法测试 | PHPUnit 9.x（兼容 PHP 7.4） |
| Integration | 模块间协作测试 | PHPUnit + Mock |
| Feature | API 集成测试（可选，需实际 API Key） | 手动/脚本测试 |

### 8.2 测试覆盖重点

- **距离度量**：CosineDistance 各边界情况
- **向量存储**：CRUD 操作、相似度搜索精度
- **HTTP 客户端**：请求构建、错误处理、流式响应
- **Chat Provider**：请求构建、响应解析、流式解析
- **FunctionBuilder**：反射解析、类型映射

### 8.3 phpunit.xml 示例

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="tests/bootstrap.php"
         colors="true"
         verbose="true">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory>tests/Integration</directory>
        </testsuite>
    </testsuites>
    <coverage>
        <include>
            <directory suffix=".php">src</directory>
        </include>
    </coverage>
</phpunit>
```

---

## 9. 使用示例

### 9.1 基础聊天

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use PhpLLP\LLP;

$llp = new LLP([
    'provider' => 'openai',
    'api_key' => 'your-api-key',
    'model' => 'gpt-4o',
]);

// 简单对话
$response = $llp->chat('你好，请介绍一下自己');
echo $response;

// 多轮对话
$messages = [
    ['role' => 'system', 'content' => '你是一个 helpful 的助手'],
    ['role' => 'user', 'content' => 'PHP 7.4 有哪些新特性？'],
];
$response = $llp->conversation($messages);

// 流式输出
foreach ($llp->chatStream('写一首关于秋天的诗') as $chunk) {
    echo $chunk;
    flush();
}
```

### 9.2 使用 Anthropic

```php
$llp = new LLP([
    'provider' => 'anthropic',
    'api_key' => 'your-api-key',
    'model' => 'claude-3-opus-20240229',
]);

$response = $llp->chat('Explain quantum computing');
```

### 9.3 使用 Ollama（本地模型）

```php
$llp = new LLP([
    'provider' => 'ollama',
    'base_url' => 'http://localhost:11434',
    'model' => 'llama3',
]);

$response = $llp->chat('你好');
```

### 9.4 图像生成

```php
$llp = new LLP([
    'provider' => 'openai',
    'api_key' => 'your-api-key',
]);

$result = $llp->image('a beautiful sunset over the ocean', [
    'size' => '1024x1024',
    'style' => 'vivid',
]);

echo $result['url'];
```

### 9.5 语音转文字

```php
$result = $llp->transcribe('/path/to/audio.mp3', [
    'language' => 'zh',
]);

echo $result['text'];
```

### 9.6 嵌入与向量存储

```php
// 生成嵌入
$embedding = $llp->embed('这是一段测试文本');

// 使用文件系统向量存储
$store = \PhpLLP\VectorStore\VectorStoreFactory::create('filesystem', [
    'vector_store_path' => './vectors.json',
]);

$doc = new \PhpLLP\Embeddings\Document();
$doc->content = '测试文档内容';
$doc->embedding = $embedding;
$doc->sourceType = 'manual';
$doc->sourceName = 'test';

$store->addDocument($doc);

// 相似度搜索
$results = $store->similaritySearch($embedding, 3);
```

### 9.7 RAG 问答

```php
// 1. 准备向量存储（使用 SQLite 持久化存储）
$vectorStore = \PhpLLP\VectorStore\VectorStoreFactory::create('sqlite', [
    'database' => './rag_vectors.sqlite',
]);

// 2. 嵌入文档并存入
$documents = [
    ['content' => 'PHP 是一种流行的编程语言'],
    ['content' => 'Laravel 是 PHP 的一个优秀框架'],
    ['content' => 'PHP 8.0 引入了 JIT 编译器'],
];

foreach ($documents as $data) {
    $embedding = $llp->embed($data['content']);
    $doc = new \PhpLLP\Embeddings\Document();
    $doc->content = $data['content'];
    $doc->embedding = $embedding;
    $vectorStore->addDocument($doc);
}

// 3. 问答
$answer = $llp->ask('PHP 有哪些特点？', [
    'vector_store' => $vectorStore,
]);

echo $answer;
```

### 9.8 工具调用

```php
$calculator = new class {
    /**
     * 计算两个数之和
     *
     * @param int $a
     * @param int $b
     * @return int
     */
    public function add(int $a, int $b): int
    {
        return $a + $b;
    }
};

$result = $llp->toolCall(
    '123 + 456 等于多少？',
    [\PhpLLP\Chat\FunctionCall\FunctionBuilder::build($calculator, 'add')]
);
```

### 9.9 统一入口 run() 方式

```php
$llp = new LLP(['provider' => 'openai', 'api_key' => '...']);

$result = $llp->run([
    'task' => 'chat',
    'messages' => [
        ['role' => 'user', 'content' => '你好'],
    ],
]);
```

---

## 10. 扩展性设计

### 10.1 添加新 AI Provider

只需实现 `ChatInterface` 接口并注册到 `LLP::createChatProvider()` 方法：

```php
class CustomChat implements ChatInterface {
    // 实现接口方法
}

// 在 LLP 类中添加
case 'custom':
    return new CustomChat($config, $this->httpClient);
```

### 10.2 添加新 VectorStore

只需实现 `VectorStoreInterface` 接口并注册到 `LLP::getVectorStore()` 方法：

```php
class CustomVectorStore implements VectorStoreInterface {
    // 实现接口方法
}

// 在 LLP 类中添加
case 'custom':
    return new CustomVectorStore($this->config['custom'] ?? []);
```

### 10.3 添加新 Embedding Provider

只需实现 `EmbeddingInterface` 接口并注册到 `LLP::createEmbeddingProvider()` 方法。

---

## 11. 参考资源

- [LLPhant](https://github.com/Theodo-UK/LLPhant) - 参考项目
- [OpenAI API](https://platform.openai.com/docs) - API 文档
- [Anthropic API](https://docs.anthropic.com/) - API 文档
- [Milvus API](https://docs.milvus.io/) - 向量数据库
- [Elasticsearch](https://www.elastic.co/guide/en/elasticsearch/reference/current/knn-search.html) - kNN 搜索
- [pgvector](https://github.com/pgvector/pgvector) - PostgreSQL 向量扩展
- [Qdrant](https://qdrant.tech/documentation/) - 向量数据库
- [ChromaDB](https://www.trychroma.com/docs) - 向量数据库
- [AstraDB](https://docs.datastax.com/en/astra/astra-db-vector-search) - 向量搜索
- [Redis Vector Search](https://redis.io/docs/stack/search/reference/vectors/) - Redis 向量搜索