<?php

declare(strict_types=1);

namespace PhpDep\Output;

use PhpDep\Graph\Confidence;
use PhpDep\Graph\DependencyGraph;
use PhpDep\Graph\EdgeType;
use PhpDep\Graph\GraphEdge;
use PhpDep\Graph\GraphNode;
use PhpDep\Warning\AnalysisWarning;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableStyle;
use Symfony\Component\Console\Output\OutputInterface;

final class TextFormatter implements FormatterInterface
{
    /** Confidence → color tag */
    private const CONFIDENCE_COLORS = [
        Confidence::CERTAIN->value => 'green',
        Confidence::HIGH->value    => 'cyan',
        Confidence::MEDIUM->value  => 'yellow',
        Confidence::LOW->value     => 'red',
    ];

    public function format(
        DependencyGraph $graph,
        array           $warnings,
        OutputInterface $output,
        array           $options = [],
    ): void {
        $classFilter = $options['class'] ?? null;
        $depthLimit  = $options['depth'] ?? null;
        $sort        = $options['sort'] ?? 'alpha';

        $internalNodes = $graph->getInternalNodes();

        if ($classFilter !== null) {
            $this->formatSingleClass($graph, $classFilter, $output, $options);
        } else {
            $this->formatAllClasses($graph, $internalNodes, $output, $sort, $options);
        }

        $this->formatSummary($graph, $warnings, $output, $options);

        if (!empty($warnings) && ($options['verbose'] ?? false)) {
            $this->formatWarnings($warnings, $output);
        }
    }

    private function formatSingleClass(DependencyGraph $graph, string $fqcn, OutputInterface $output, array $options): void
    {
        $node = $graph->getNode($fqcn);
        if ($node === null) {
            $output->writeln("<error>Class not found: {$fqcn}</error>");
            return;
        }

        $output->writeln('');
        $output->writeln(sprintf('<info>Analysis: <comment>%s</comment></info>', $fqcn));
        $output->writeln(str_repeat('─', 80));

        // Dependencies (outgoing edges)
        $outEdges = $graph->getEdgesFrom($fqcn);
        if (!empty($outEdges)) {
            $output->writeln('');
            $output->writeln('<info>Dependencies (uses):</info>');
            $this->renderEdgeTable($outEdges, $output, 'target');
        }

        // Dependants (incoming edges)
        $inEdges = $graph->getEdgesTo($fqcn);
        if (!empty($inEdges)) {
            $output->writeln('');
            $output->writeln('<info>Used by (dependants):</info>');
            $this->renderEdgeTable($inEdges, $output, 'source');
        }

        if (empty($outEdges) && empty($inEdges)) {
            $output->writeln('<comment>No relations found for this class.</comment>');
        }
    }

    private function formatAllClasses(DependencyGraph $graph, array $internalNodes, OutputInterface $output, string $sort, array $options): void
    {
        // Sort
        usort($internalNodes, match ($sort) {
            'deps'    => fn(GraphNode $a, GraphNode $b) => count($graph->dependenciesOf($b->fqcn)) <=> count($graph->dependenciesOf($a->fqcn)),
            'fanin'   => fn(GraphNode $a, GraphNode $b) => count($graph->dependantsOf($b->fqcn)) <=> count($graph->dependantsOf($a->fqcn)),
            default   => fn(GraphNode $a, GraphNode $b) => $a->fqcn <=> $b->fqcn,
        });

        // Apply limit
        $limit = $options['limit'] ?? null;
        if ($limit !== null) {
            $internalNodes = array_slice($internalNodes, 0, (int) $limit);
        }

        $output->writeln('');

        $table = new Table($output);
        $table->setStyle('box');
        $table->setHeaders(['Class', 'Type', 'Deps out', 'Deps in', 'File']);

        foreach ($internalNodes as $node) {
            $depsOut = count($graph->dependenciesOf($node->fqcn));
            $depsIn  = count($graph->dependantsOf($node->fqcn));
            $file    = $node->file ? basename($node->file) : '—';

            $table->addRow([
                '<comment>' . $this->shortFqcn($node->fqcn) . '</comment>',
                $node->type->value,
                $depsOut > 0 ? "<fg=cyan>{$depsOut}</>" : '0',
                $depsIn  > 0 ? "<fg=green>{$depsIn}</>" : '0',
                $file,
            ]);
        }

        $table->render();
    }

    private function renderEdgeTable(array $edges, OutputInterface $output, string $endField): void
    {
        $table = new Table($output);
        $table->setStyle('compact');
        $table->setHeaders(['Target', 'Relation', 'Confidence', 'Line']);

        // Deduplicate by target+type
        $seen = [];
        foreach ($edges as $edge) {
            /** @var GraphEdge $edge */
            $key = $edge->$endField . ':' . $edge->type->value;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $color      = self::CONFIDENCE_COLORS[$edge->confidence->value] ?? 'white';
            $confidence = "<fg={$color}>{$edge->confidence->value}</>";

            $table->addRow([
                $edge->$endField,
                $edge->type->value,
                $confidence,
                $edge->line ?? '—',
            ]);
        }

        $table->render();
    }

    private function formatSummary(DependencyGraph $graph, array $warnings, OutputInterface $output, array $options): void
    {
        $output->writeln('');
        $output->writeln(str_repeat('─', 80));

        $fileCount  = $options['file_count'] ?? '?';
        $nodeCount  = count($graph->getInternalNodes());
        $edgeCount  = $graph->getEdgeCount();
        $warnCount  = count($warnings);

        $output->writeln(sprintf(
            '<info>Summary:</info> %d file(s) · <comment>%d</comment> class(es)/interface(s)/trait(s) · <comment>%d</comment> edge(s) · <comment>%d</comment> warning(s)',
            $fileCount,
            $nodeCount,
            $edgeCount,
            $warnCount,
        ));

        if ($warnCount > 0 && !($options['verbose'] ?? false)) {
            $output->writeln('<comment>Run with -v to see warnings.</comment>');
        }
    }

    private function formatWarnings(array $warnings, OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('<fg=yellow>Warnings:</>');

        foreach ($warnings as $warning) {
            /** @var AnalysisWarning $warning */
            $line = $warning->line !== null ? ":{$warning->line}" : '';
            $output->writeln(sprintf(
                '  <fg=yellow>[%s]</> %s%s — %s',
                $warning->type->value,
                $warning->file,
                $line,
                $warning->message,
            ));
        }
    }

    private function shortFqcn(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);
        if (count($parts) <= 3) {
            return $fqcn;
        }
        // Show last 3 parts with leading backslash to indicate truncation
        return '…\\' . implode('\\', array_slice($parts, -3));
    }
}
