<?php

declare(strict_types=1);

namespace PhpDep\Analyzer;

final readonly class AnalyzerConfig
{
    public function __construct(
        /** Skip @param/@return/@var/@throws docblock analysis */
        public bool   $skipDocblocks  = false,

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
    ) {}
}
