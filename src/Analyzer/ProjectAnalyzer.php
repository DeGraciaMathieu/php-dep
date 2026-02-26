<?php

declare(strict_types=1);

namespace PhpDep\Analyzer;

use PhpDep\Discovery\FileDiscovery;
use PhpDep\Graph\DependencyGraph;
use PhpDep\Graph\GraphNode;
use PhpDep\Graph\NodeType;
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
     * @param callable(string $file, int $current, int $total): void $progressCallback
     * @return array{graph: DependencyGraph, warnings: AnalysisWarning[], file_count: int}
     */
    public function analyze(
        string         $path,
        AnalyzerConfig $config,
        callable       $progressCallback = null,
    ): array {
        $files    = $this->discovery->discover($path, $config);
        $total    = count($files);
        $graph    = new DependencyGraph();
        $warnings = [];
        $current  = 0;

        foreach ($files as $file) {
            $current++;

            if ($progressCallback !== null) {
                ($progressCallback)($file, $current, $total);
            }

            $result = $this->fileAnalyzer->analyze($file, $config);

            foreach ($result['nodes'] as $node) {
                $graph->addNode($node);
            }

            foreach ($result['edges'] as $edge) {
                // In boundary mode, don't add edge if target is vendor
                if ($config->vendorMode === 'boundary' && $this->isVendorClass($edge->target, $path)) {
                    // Still add as external node + edge — just don't analyze vendor internals
                    $vendorNode = new GraphNode($edge->target, NodeType::EXTERNAL);
                    $graph->addNode($vendorNode);
                }
                $graph->addEdge($edge);
            }

            $warnings = array_merge($warnings, $result['warnings']);

            // Release memory — the AST was already freed inside PhpFileParser::parse()
            unset($result);
        }

        return [
            'graph'      => $graph,
            'warnings'   => $warnings,
            'file_count' => $total,
        ];
    }

    private function isVendorClass(string $fqcn, string $projectPath): bool
    {
        // Heuristic: check if the FQCN is not defined in any project node
        // In practice, vendor detection is done at file discovery level
        // This is a placeholder for future vendor namespace detection
        return false;
    }
}
