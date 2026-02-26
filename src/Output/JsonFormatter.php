<?php

declare(strict_types=1);

namespace PhpDep\Output;

use PhpDep\Graph\DependencyGraph;
use PhpDep\Graph\GraphEdge;
use PhpDep\Graph\GraphNode;
use PhpDep\Warning\AnalysisWarning;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

final class JsonFormatter implements FormatterInterface
{
    public function format(
        DependencyGraph $graph,
        array           $warnings,
        OutputInterface $output,
        array           $options = [],
    ): void {
        $internalNodes = $graph->getInternalNodes();
        $allEdges      = $graph->getAllEdges();
        $allNodes      = $graph->getAllNodes();

        // Sort by FQCN
        usort($internalNodes, fn(GraphNode $a, GraphNode $b) => $a->fqcn <=> $b->fqcn);
        usort($allEdges, fn(GraphEdge $a, GraphEdge $b) => $a->source <=> $b->source ?: $a->target <=> $b->target);

        $result = [
            'meta' => [
                'version'        => '1.0',
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

        // Apply --class filter if provided
        if (!empty($options['class'])) {
            $fqcn = $options['class'];
            $result['classes'] = array_values(array_filter(
                $result['classes'],
                fn(array $c) => $c['fqcn'] === $fqcn,
            ));
            $result['edges'] = array_values(array_filter(
                $result['edges'],
                fn(array $e) => $e['source'] === $fqcn || $e['target'] === $fqcn,
            ));
        }

        // Always write JSON regardless of verbosity/quiet mode — use STDOUT directly
        $output->write(
            json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            false,
            OutputInterface::VERBOSITY_QUIET,
        );
    }
}
