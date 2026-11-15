<?php

declare(strict_types=1);

namespace PhpDep\Analyzer;

interface FileParser
{
    public function parse(string $file): ParsedFile;
}
