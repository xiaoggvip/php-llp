# phpLLP

[![PHP Version](https://img.shields.io/badge/php-%3E%3D7.4-8892BF.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

一个轻量级、零依赖的 PHP LLM 应用开发库。

## 特性

- **零外部依赖**: 仅使用 PHP 原生功能（cURL、JSON、反射等）
- **PHP 7.4+ 兼容**: 通过 `PhpVersion` 类处理版本差异
- **多 Provider 支持**: OpenAI、Anthropic、Mistral、Ollama
- **完整功能**: 聊天、图像生成、语音转文字、嵌入生成、向量存储
- **Function Calling**: 内置工具调用和函数执行引擎
- **RAG 流程**: 检索增强生成，支持语义搜索和问答
- **多向量存储**: 文件系统、SQLite、PostgreSQL、Qdrant、Redis、Elasticsearch、Milvus、ChromaDB、AstraDB
- **流式响应**: 支持 SSE 流式输出

## 安装

```bash
composer require php-llp/php-llp
```

## 快速开始

### 聊天对话

```php
use PhpLLP\LLP;

$llp = new LLP([
    'provider' => 'openai',
    'api_key' => 'your-api-key',
    'model' => 'gpt-4o',
]);

// 简单对话
$response = $llp->chat('你好，请介绍一下自己');

// 多轮对话
$messages = [
    ['role' => 'system', 'content' => '你是一个有用的助手'],
    ['role' => 'user', 'content' => '什么是PHP？'],
];
$response = $llp->conversation($messages);
```

### 图像生成

```php
$result = $llp->image('A beautiful sunset over the ocean', [
    'size' => '1024x1024',
    'style' => 'vivid',
]);
```

### 语音转文字

```php
$result = $llp->transcribe('/path/to/audio.mp3', [
    'language' => 'zh',
]);
```

### 嵌入生成

```php
$embedding = $llp->embed('你好世界');
// 返回向量数组，可用于语义搜索和相似度计算
```

### 向量存储 + RAG

```php
use PhpLLP\VectorStore\VectorStoreFactory;
use PhpLLP\Embeddings\Document;

// 创建向量存储
$store = VectorStoreFactory::create('filesystem', [
    'collection' => 'my_knowledge',
    'path' => './storage',
]);

// 添加文档
$doc = new Document([
    'content' => 'PHP是一种编程语言',
    'embedding' => [0.1, 0.9, 0.3],
    'metadata' => ['category' => 'tech'],
]);
$store->addDocument($doc);

// 语义搜索
$results = $store->similaritySearch($queryVector, 5, 0.5);

// RAG 问答
$answer = $llp->ask('什么是PHP？', [
    'vector_store' => 'filesystem',
]);
```

### 工具调用

```php
use PhpLLP\Tools\Builtin\Calculator;
use PhpLLP\Tools\Builtin\WebPageFetcher;

$llp->registerTool(new Calculator());
$llp->registerTool(new WebPageFetcher());

// LLM 自动调用工具
$result = $llp->chat('计算 (15 + 27) * 3 的结果，并获取 https://example.com 的内容');
```

## 架构

```
┌─────────────────────────────────────────────────┐
│                    LLP 统一入口                  │
├─────────┬──────────┬──────────┬─────────────────┤
│  Chat   │  Image   │  Audio   │  Embeddings     │
├─────────┴──────────┴──────────┴─────────────────┤
│              VectorStore 向量存储               │
├─────────────────────────────────────────────────┤
│  FileSystem │ SQLite │ Qdrant │ Redis │ Milvus... │
└─────────────────────────────────────────────────┘
```

## 目录结构

```
src/
├── Chat/              # 聊天模块
│   ├── Provider/      # LLM Provider 实现
│   ├── FunctionCall/  # 工具调用子模块
│   └── Enum/          # 枚举常量
├── Image/             # 图像模块
├── Audio/             # 音频模块
├── Embeddings/        # 嵌入模块
│   ├── Generator/     # 嵌入生成器
│   ├── Distances/     # 距离计算
│   ├── Splitter/      # 文档分割
│   ├── Formatter/     # 格式化
│   └── Reader/        # 数据读取
├── VectorStore/       # 向量存储
├── Query/             # 查询/RAG 模块
├── Tools/             # 工具模块
├── Http/              # HTTP 客户端
├── Contracts/         # 接口定义
├── Support/           # 工具类
├── Exception/         # 异常类
└── LLP.php            # 统一入口类
```

## 支持的 Provider

| 类型 | Provider | 说明 |
|------|----------|------|
| Chat | OpenAI, Anthropic, Mistral, Ollama | 同步/流式响应 |
| Image | OpenAI (DALL-E 3) | 图像生成 |
| Audio | Whisper | 语音转文字 |
| Embedding | OpenAI, Mistral, Ollama | 文本嵌入 |

## 支持的向量存储

| 存储 | 类型 | 说明 |
|------|------|------|
| FileSystem | 本地 | JSON 文件存储 |
| SQLite | 本地 | PDO + 本地余弦计算 |
| PostgreSQL | 远程 | pgvector 支持 |
| Qdrant | 远程 | REST API |
| Redis | 远程 | RediSearch |
| Elasticsearch | 远程 | NDJSON bulk API |
| Milvus | 远程 | REST v2.4 |
| ChromaDB | 远程 | 多租户 REST API |
| AstraDB | 远程 | Serverless 向量数据库 |

## 要求

- PHP >= 7.4
- cURL 扩展
- JSON 扩展
- mbstring 扩展（可选，用于多字节字符串处理）

## License

MIT License. See [LICENSE](LICENSE) for details.