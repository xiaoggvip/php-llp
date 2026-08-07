<?php

declare(strict_types=1);

namespace PhpLLP\Embeddings\Reader;

interface DataReader
{
    /**
     * Read data and return as array of documents
     *
     * @return \PhpLLP\Embeddings\Document[]
     */
    public function read(): array;

    /**
     * Read from a source
     *
     * @param string $source
     * @return \PhpLLP\Embeddings\Document[]
     */
    public function readFrom(string $source): array;
}