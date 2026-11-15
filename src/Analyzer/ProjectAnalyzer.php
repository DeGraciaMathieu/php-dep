<?php

declare(strict_types=1);

namespace PhpDep\Analyzer;

use PhpDep\Discovery\FileDiscovery;
use PhpDep\Graph\DependencyGraph;
use PhpDep\Parser\PhpFileParser;
use PhpDep\Warning\AnalysisWarning;

final class ProjectAnalyzer
{
    private FileDiscovery $discovery;
    private PhpFileParser $parser;

    public function __construct()
    {
        $this->discovery = new FileDiscovery();
        $this->parser    = new PhpFileParser();
    }

    /**
     * Analyze an entire project directory.
     *
     * @return array{graph: DependencyGraph, warnings: AnalysisWarning[], file_count: int}
     */
    public function analyze(string $path, AnalyzerConfig $config): array
    {
        $files   = $this->discovery->discover($path, $config->excludeDirs, $config->excludeVendor);
        $results = (new WorkerPool($config->workers))->map(
            $files,
            fn(string $file) => $this->parser->parse($file),
        );

        $graph    = new DependencyGraph();
        $warnings = [];

        // Every declared class first, so that an edge finds its target whatever the order of the files
        foreach ($results as $parsed) {
            foreach ($parsed->nodes as $node) {
                $graph->addNode($node);
            }
        }

        foreach ($results as $parsed) {
            foreach ($parsed->edges as $edge) {
                $graph->addEdge($edge);
            }

            array_push($warnings, ...$parsed->warnings);
        }

        return [
            'graph'      => $graph,
            'warnings'   => $warnings,
            'file_count' => count($files),
        ];
    }
}
