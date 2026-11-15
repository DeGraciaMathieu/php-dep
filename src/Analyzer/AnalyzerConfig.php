<?php

declare(strict_types=1);

namespace PhpDep\Analyzer;

final readonly class AnalyzerConfig
{
    public function __construct(
        /** Exclude vendor directory from file discovery */
        public bool   $excludeVendor  = true,

        /**
         * How to handle vendor classes:
         * - 'boundary' : vendor classes are EXTERNAL leaf nodes (default)
         * - 'source-only': vendor classes referenced but not analyzed
         * - 'full'    : vendor files are also analyzed
         */
        public string $vendorMode     = 'boundary',

        /** Directories to exclude (in addition to defaults) */
        public array  $excludeDirs    = [],

        /** Only include files matching these glob patterns (empty = all) */
        public array  $includePatterns = [],

        /** Processes parsing the files in parallel */
        public int    $workers        = 1,
    ) {}
}
