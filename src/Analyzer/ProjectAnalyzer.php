<?php

declare(strict_types=1);

namespace PhpDep\Analyzer;

use PhpDep\Discovery\FileDiscovery;
use PhpDep\Graph\DependencyGraph;
use PhpDep\Warning\AnalysisWarning;

final class ProjectAnalyzer
{
    private FileDiscovery $discovery;
    private FileAnalyzer  $fileAnalyzer;

    public function __construct()
    {
        $this->discovery    = new FileDiscovery();
        $this->fileAnalyzer = new FileAnalyzer();
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
            fn(string $file) => $this->fileAnalyzer->analyze($file, $config),
        );

        $graph    = new DependencyGraph();
        $warnings = [];

        foreach ($results as $result) {
            foreach ($result['nodes'] as $node) {
                $graph->addNode($node);
            }

            foreach ($result['edges'] as $edge) {
                $graph->addEdge($edge);
            }

            array_push($warnings, ...$result['warnings']);
        }

        return [
            'graph'      => $graph,
            'warnings'   => $warnings,
            'file_count' => count($files),
        ];
    }
}
