<?php

declare(strict_types=1);

namespace PhpLLP\Embeddings\Reader;

use PhpLLP\Embeddings\Document;

class CsvReader implements DataReader
{
    /** @var string */
    private $source;

    /** @var string */
    private $delimiter;

    /** @var string */
    private $enclosure;

    /** @var bool */
    private $hasHeader;

    /** @var array<int, string>|null */
    private $headers;

    /** @var array<string, mixed> */
    private $metadata;

    /**
     * @param string $source
     * @param string $delimiter
     * @param string $enclosure
     * @param bool $hasHeader
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        string $source = '',
        string $delimiter = ',',
        string $enclosure = '"',
        bool $hasHeader = true,
        array $metadata = []
    ) {
        $this->source = $source;
        $this->delimiter = $delimiter;
        $this->enclosure = $enclosure;
        $this->hasHeader = $hasHeader;
        $this->metadata = $metadata;
    }

    public function read(): array
    {
        return $this->readFrom($this->source);
    }

    public function readFrom(string $source): array
    {
        $lines = $this->parseCsv($source);
        if (empty($lines)) {
            return [];
        }

        $this->headers = null;
        $startIndex = 0;

        if ($this->hasHeader && !empty($lines)) {
            $this->headers = array_shift($lines);
            $startIndex = 0;
        }

        $documents = [];
        foreach ($lines as $rowIndex => $row) {
            $content = $this->rowToString($row);
            if (empty($content)) {
                continue;
            }

            $rowData = $this->combineWithHeaders($row);
            $metadata = array_merge($this->metadata, [
                'source' => $source,
                'type' => 'csv',
                'row_index' => $startIndex + $rowIndex,
                'row_data' => $rowData,
            ]);

            $documents[] = new Document([
                'content' => $content,
                'metadata' => $metadata,
            ]);
        }

        return $documents;
    }

    /**
     * @param string $source
     * @return array<int, array<int, string>>
     */
    private function parseCsv(string $source): array
    {
        $content = $this->loadContent($source);
        if ($content === '') {
            return [];
        }

        $lines = [];
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        while (($row = fgetcsv($stream, 0, $this->delimiter, $this->enclosure)) !== false) {
            if (count($row) === 1 && $row[0] === null) {
                continue;
            }
            $lines[] = $row;
        }

        fclose($stream);
        return $lines;
    }

    /**
     * @param array<int, string> $row
     * @return string
     */
    private function rowToString(array $row): string
    {
        $parts = [];
        foreach ($row as $value) {
            $parts[] = (string)$value;
        }
        return implode(' | ', $parts);
    }

    /**
     * @param array<int, string> $row
     * @return array<string, string>
     */
    private function combineWithHeaders(array $row): array
    {
        if ($this->headers === null) {
            return ['row' => implode(',', $row)];
        }

        $result = [];
        foreach ($this->headers as $index => $header) {
            $result[$header] = $row[$index] ?? '';
        }
        return $result;
    }

    /**
     * @param string $source
     * @return string
     */
    private function loadContent(string $source): string
    {
        if (file_exists($source)) {
            $content = file_get_contents($source);
            return $content !== false ? $content : '';
        }

        return $source;
    }
}