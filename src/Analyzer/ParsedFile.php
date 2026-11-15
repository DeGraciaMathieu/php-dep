<?php

declare(strict_types=1);

namespace PhpDep\Analyzer;

use PhpDep\Graph\GraphEdge;
use PhpDep\Graph\GraphNode;
use PhpDep\Warning\AnalysisWarning;

/** What a single file declares and uses */
final readonly class ParsedFile
{
    /**
     * @param GraphNode[]       $nodes
     * @param GraphEdge[]       $edges
     * @param AnalysisWarning[] $warnings
     */
    public function __construct(
        public array $nodes = [],
        public array $edges = [],
        public array $warnings = [],
    ) {}
}
