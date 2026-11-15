<?php

declare(strict_types=1);

namespace PhpDep\Analyzer;

final readonly class AnalyzerConfig
{
    public function __construct(
        /** Exclude vendor directory from file discovery */
        public bool   $excludeVendor  = true,

        /** Directories to exclude (in addition to defaults) */
        public array  $excludeDirs    = [],

        /** Processes parsing the files in parallel */
        public int    $workers        = 1,
    ) {}
}
