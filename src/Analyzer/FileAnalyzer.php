<?php

declare(strict_types=1);

namespace PhpDep\Analyzer;

use PhpDep\Graph\GraphEdge;
use PhpDep\Graph\GraphNode;
use PhpDep\Parser\PhpFileParser;
use PhpDep\Warning\AnalysisWarning;

final class FileAnalyzer
{
    private PhpFileParser $parser;

    public function __construct()
    {
        $this->parser = new PhpFileParser();
    }

    /**
     * Analyze a single PHP file.
     *
     * @return array{nodes: GraphNode[], edges: GraphEdge[], warnings: AnalysisWarning[]}
     */
    public function analyze(string $file, AnalyzerConfig $config): array
    {
        return $this->parser->parse($file, $config->skipDocblocks);
    }
}
