<?php

declare(strict_types=1);

namespace PhpDep\Tests\Output;

use PhpDep\Graph\Confidence;
use PhpDep\Graph\DependencyGraph;
use PhpDep\Graph\EdgeType;
use PhpDep\Graph\GraphEdge;
use PhpDep\Graph\GraphNode;
use PhpDep\Graph\NodeType;
use PhpDep\Output\TextFormatter;
use PhpDep\Warning\AnalysisWarning;
use PhpDep\Warning\WarningType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

final class TextFormatterTest extends TestCase
{
    private TextFormatter $formatter;
    private BufferedOutput $output;

    protected function setUp(): void
    {
        $this->formatter = new TextFormatter();
        $this->output    = new BufferedOutput();
    }

    private function format(DependencyGraph $graph, array $warnings = [], array $options = []): string
    {
        $this->formatter->format($graph, $warnings, $this->output, $options);
        return $this->output->fetch();
    }

    // ── Summary ───────────────────────────────────────────────────────────────

    public function testSummaryContainsFileCount(): void
    {
        $result = $this->format(new DependencyGraph(), options: ['file_count' => 7]);
        self::assertStringContainsString('7 file(s)', $result);
    }

    public function testSummaryContainsClassCount(): void
    {
        $graph = new DependencyGraph();
        $graph->addNode(new GraphNode('App\\A', NodeType::CLASS_NODE));
        $graph->addNode(new GraphNode('App\\B', NodeType::CLASS_NODE));

        $result = $this->format($graph);
        self::assertStringContainsString('2 class(es)', $result);
    }

    public function testSummaryContainsEdgeCount(): void
    {
        $graph = new DependencyGraph();
        $graph->addNode(new GraphNode('App\\A', NodeType::CLASS_NODE));
        $graph->addNode(new GraphNode('App\\B', NodeType::CLASS_NODE));
        $graph->addEdge(new GraphEdge('App\\A', 'App\\B', EdgeType::EXTENDS_CLASS, Confidence::CERTAIN));

        $result = $this->format($graph);
        self::assertStringContainsString('1 edge(s)', $result);
    }

    public function testSummaryContainsWarningCount(): void
    {
        $warning = new AnalysisWarning(WarningType::PARSE_ERROR, '/f.php', null, 'err');
        $result  = $this->format(new DependencyGraph(), [$warning]);

        self::assertStringContainsString('1 warning(s)', $result);
    }

    // ── Warnings ──────────────────────────────────────────────────────────────

    public function testWarningHintAppearsWhenNotVerbose(): void
    {
        $warning = new AnalysisWarning(WarningType::PARSE_ERROR, '/f.php', null, 'err');
        $result  = $this->format(new DependencyGraph(), [$warning]);

        self::assertStringContainsString('Run with -v', $result);
    }

    public function testWarningsShownAndHintHiddenWhenVerbose(): void
    {
        $warning = new AnalysisWarning(WarningType::PARSE_ERROR, '/path/to/file.php', 5, 'Oops');
        $result  = $this->format(new DependencyGraph(), [$warning], ['verbose' => true]);

        self::assertStringContainsString('/path/to/file.php', $result);
        self::assertStringContainsString('Oops', $result);
        self::assertStringNotContainsString('Run with -v', $result);
    }

    public function testWarningWithLineNumberIsIncluded(): void
    {
        $warning = new AnalysisWarning(WarningType::DYNAMIC_INSTANTIATION, '/src/A.php', 42, 'dynamic new');
        $result  = $this->format(new DependencyGraph(), [$warning], ['verbose' => true]);

        self::assertStringContainsString(':42', $result);
    }

    // ── Class filter ──────────────────────────────────────────────────────────

    public function testClassFilterShowsNotFoundMessageForMissingClass(): void
    {
        $result = $this->format(new DependencyGraph(), options: ['class' => 'App\\Missing']);

        self::assertStringContainsString('Class not found', $result);
        self::assertStringContainsString('App\\Missing', $result);
    }

    public function testClassFilterShowsDependencies(): void
    {
        $graph = new DependencyGraph();
        $graph->addNode(new GraphNode('App\\A', NodeType::CLASS_NODE));
        $graph->addNode(new GraphNode('App\\B', NodeType::CLASS_NODE));
        $graph->addEdge(new GraphEdge('App\\A', 'App\\B', EdgeType::EXTENDS_CLASS, Confidence::CERTAIN));

        $result = $this->format($graph, options: ['class' => 'App\\A']);

        self::assertStringContainsString('Dependencies', $result);
        self::assertStringContainsString('App\\B', $result);
    }

    public function testClassFilterShowsDependants(): void
    {
        $graph = new DependencyGraph();
        $graph->addNode(new GraphNode('App\\A', NodeType::CLASS_NODE));
        $graph->addNode(new GraphNode('App\\B', NodeType::CLASS_NODE));
        $graph->addEdge(new GraphEdge('App\\A', 'App\\B', EdgeType::EXTENDS_CLASS, Confidence::CERTAIN));

        $result = $this->format($graph, options: ['class' => 'App\\B']);

        self::assertStringContainsString('Used by', $result);
        self::assertStringContainsString('App\\A', $result);
    }

    public function testNoRelationsMessageForIsolatedClass(): void
    {
        $graph = new DependencyGraph();
        $graph->addNode(new GraphNode('App\\Alone', NodeType::CLASS_NODE));

        $result = $this->format($graph, options: ['class' => 'App\\Alone']);

        self::assertStringContainsString('No relations', $result);
    }

    // ── All-classes table ─────────────────────────────────────────────────────

    public function testAllClassesTableContainsNodeFqcn(): void
    {
        $graph = new DependencyGraph();
        $graph->addNode(new GraphNode('App\\MyClass', NodeType::CLASS_NODE));

        $result = $this->format($graph);

        self::assertStringContainsString('MyClass', $result);
    }

    public function testExternalNodesAreNotListedInTable(): void
    {
        $graph = new DependencyGraph();
        $graph->addNode(new GraphNode('App\\A', NodeType::CLASS_NODE));
        $graph->addNode(new GraphNode('Ext\\Lib', NodeType::EXTERNAL));

        $result = $this->format($graph);

        // Summary must count only internal node
        self::assertStringContainsString('1 class(es)', $result);
    }

    public function testLimitReducesTableRows(): void
    {
        $graph = new DependencyGraph();
        for ($i = 1; $i <= 5; $i++) {
            $graph->addNode(new GraphNode("App\\Class{$i}", NodeType::CLASS_NODE));
        }

        $result = $this->format($graph, options: ['limit' => 2]);

        // Class1 and Class2 appear in the table; Class5 does not
        self::assertStringContainsString('Class1', $result);
        self::assertStringContainsString('Class2', $result);
        self::assertStringNotContainsString('Class5', $result);
    }

    public function testSortByDepsPlacesMostDependentFirst(): void
    {
        $graph = new DependencyGraph();
        $graph->addNode(new GraphNode('App\\A', NodeType::CLASS_NODE));
        $graph->addNode(new GraphNode('App\\B', NodeType::CLASS_NODE));
        $graph->addNode(new GraphNode('App\\C', NodeType::CLASS_NODE));
        // A has 2 outgoing edges, B and C have none
        $graph->addEdge(new GraphEdge('App\\A', 'App\\B', EdgeType::PARAM_TYPE, Confidence::HIGH));
        $graph->addEdge(new GraphEdge('App\\A', 'App\\C', EdgeType::PARAM_TYPE, Confidence::HIGH));

        $result = $this->format($graph, options: ['sort' => 'deps']);

        // App\A should appear before App\B in the rendered table
        self::assertGreaterThan(strpos($result, 'App\\A'), strpos($result, 'App\\B'));
    }

    public function testSortByFanInPlacesMostUsedFirst(): void
    {
        $graph = new DependencyGraph();
        $graph->addNode(new GraphNode('App\\A', NodeType::CLASS_NODE));
        $graph->addNode(new GraphNode('App\\B', NodeType::CLASS_NODE));
        $graph->addNode(new GraphNode('App\\C', NodeType::CLASS_NODE));
        // B is depended on by A and C
        $graph->addEdge(new GraphEdge('App\\A', 'App\\B', EdgeType::PARAM_TYPE, Confidence::HIGH));
        $graph->addEdge(new GraphEdge('App\\C', 'App\\B', EdgeType::PARAM_TYPE, Confidence::HIGH));

        $result = $this->format($graph, options: ['sort' => 'fanin']);

        // App\B has 2 fan-in; should appear before App\A
        self::assertGreaterThan(strpos($result, 'App\\B'), strpos($result, 'App\\A'));
    }

    public function testDefaultSortIsAlphabetical(): void
    {
        $graph = new DependencyGraph();
        $graph->addNode(new GraphNode('App\\Z', NodeType::CLASS_NODE));
        $graph->addNode(new GraphNode('App\\A', NodeType::CLASS_NODE));

        $result = $this->format($graph);

        self::assertLessThan(strpos($result, 'App\\Z'), strpos($result, 'App\\A'));
    }
}
