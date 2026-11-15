<?php

declare(strict_types=1);

namespace PhpDep\Output;

use PhpDep\Graph\DependencyGraph;
use PhpDep\Graph\GraphEdge;
use PhpDep\Graph\GraphNode;
use PhpDep\Warning\AnalysisWarning;

final class JsonFormatter
{
    /** @param AnalysisWarning[] $warnings */
    public function format(
        DependencyGraph $graph,
        array           $warnings,
        array           $options = [],
    ): string {
        $internalNodes = $graph->getInternalNodes();
        $allEdges      = $graph->getAllEdges();
        $allNodes      = $graph->getAllNodes();

        // Sort by FQCN
        usort($internalNodes, fn(GraphNode $a, GraphNode $b) => $a->fqcn <=> $b->fqcn);
        usort($allEdges, fn(GraphEdge $a, GraphEdge $b) => $a->source <=> $b->source ?: $a->target <=> $b->target);

        $result = [
            'meta' => [
                'version'        => '2.0',
                'generated_at'   => date('c'),
                'analyzed_path'  => $options['path'] ?? null,
                'file_count'     => $options['file_count'] ?? 0,
                'class_count'    => count($internalNodes),
                'node_count'     => count($allNodes),
                'edge_count'     => count($allEdges),
                'warning_count'  => count($warnings),
            ],
            'classes' => array_map(
                fn(GraphNode $n) => array_merge($n->toArray(), [
                    'dependencies' => $graph->dependenciesOf($n->fqcn),
                    'dependants'   => $graph->dependantsOf($n->fqcn),
                ]),
                $internalNodes,
            ),
            'edges'    => array_map(fn(GraphEdge $e) => $e->toArray(), $allEdges),
            'warnings' => array_map(fn(AnalysisWarning $w) => $w->toArray(), $warnings),
        ];

        return json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
