<?php

declare(strict_types=1);

namespace PhpDep\Tests\Output;

use PhpDep\Graph\Confidence;
use PhpDep\Graph\DependencyGraph;
use PhpDep\Graph\EdgeType;
use PhpDep\Graph\GraphEdge;
use PhpDep\Graph\GraphNode;
use PhpDep\Graph\NodeType;
use PhpDep\Output\JsonFormatter;
use PhpDep\Warning\AnalysisWarning;
use PhpDep\Warning\WarningType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

final class JsonFormatterTest extends TestCase
{
    private JsonFormatter $formatter;
    private BufferedOutput $output;

    protected function setUp(): void
    {
        $this->formatter = new JsonFormatter();
        $this->output    = new BufferedOutput();
    }

    private function formatAndDecode(DependencyGraph $graph, array $warnings = [], array $options = []): array
    {
        $this->formatter->format($graph, $warnings, $this->output, $options);
        $json = $this->output->fetch();
        self::assertJson($json);
        return json_decode($json, true);
    }

    // ── Structure ─────────────────────────────────────────────────────────────

    public function testOutputIsValidJson(): void
    {
        $this->formatter->format(new DependencyGraph(), [], $this->output);
        self::assertJson($this->output->fetch());
    }

    public function testEmptyGraphHasExpectedTopLevelKeys(): void
    {
        $data = $this->formatAndDecode(new DependencyGraph());

        self::assertArrayHasKey('meta', $data);
        self::assertArrayHasKey('classes', $data);
        self::assertArrayHasKey('edges', $data);
        self::assertArrayHasKey('warnings', $data);
    }

    public function testMetaVersion(): void
    {
        $data = $this->formatAndDecode(new DependencyGraph());
        self::assertSame('1.0', $data['meta']['version']);
    }

    public function testMetaCountsOnEmptyGraph(): void
    {
        $data = $this->formatAndDecode(new DependencyGraph());

        self::assertSame(0, $data['meta']['class_count']);
        self::assertSame(0, $data['meta']['node_count']);
        self::assertSame(0, $data['meta']['edge_count']);
        self::assertSame(0, $data['meta']['warning_count']);
    }

    public function testMetaFileCountAndPathFromOptions(): void
    {
        $data = $this->formatAndDecode(
            new DependencyGraph(),
            options: ['file_count' => 42, 'path' => '/src'],
        );

        self::assertSame(42, $data['meta']['file_count']);
        self::assertSame('/src', $data['meta']['analyzed_path']);
    }

    // ── Classes ───────────────────────────────────────────────────────────────

    public function testOnlyInternalNodesAppearInClasses(): void
    {
        $graph = new DependencyGraph();
        $graph->addNode(new GraphNode('App\\Foo', NodeType::CLASS_NODE, '/Foo.php', 1));
        $graph->addNode(new GraphNode('Ext\\Bar', NodeType::EXTERNAL));

        $data = $this->formatAndDecode($graph);

        self::assertCount(1, $data['classes']);
        self::assertSame('App\\Foo', $data['classes'][0]['fqcn']);
    }

    public function testClassesAreSortedByFqcn(): void
    {
        $graph = new DependencyGraph();
        $graph->addNode(new GraphNode('App\\Z', NodeType::CLASS_NODE));
        $graph->addNode(new GraphNode('App\\A', NodeType::CLASS_NODE));

        $data = $this->formatAndDecode($graph);

        self::assertSame('App\\A', $data['classes'][0]['fqcn']);
        self::assertSame('App\\Z', $data['classes'][1]['fqcn']);
    }

    public function testClassEntryContainsDependenciesAndDependants(): void
    {
        $graph = new DependencyGraph();
        $graph->addNode(new GraphNode('App\\A', NodeType::CLASS_NODE));
        $graph->addNode(new GraphNode('App\\B', NodeType::CLASS_NODE));
        $graph->addEdge(new GraphEdge('App\\A', 'App\\B', EdgeType::EXTENDS_CLASS, Confidence::CERTAIN));

        $data  = $this->formatAndDecode($graph);
        $classA = $this->findClass($data['classes'], 'App\\A');
        $classB = $this->findClass($data['classes'], 'App\\B');

        self::assertContains('App\\B', $classA['dependencies']);
        self::assertEmpty($classA['dependants']);
        self::assertContains('App\\A', $classB['dependants']);
        self::assertEmpty($classB['dependencies']);
    }

    // ── Edges ─────────────────────────────────────────────────────────────────

    public function testEdgesAppearInOutput(): void
    {
        $graph = new DependencyGraph();
        $graph->addNode(new GraphNode('App\\A', NodeType::CLASS_NODE));
        $graph->addNode(new GraphNode('App\\B', NodeType::CLASS_NODE));
        $graph->addEdge(new GraphEdge('App\\A', 'App\\B', EdgeType::EXTENDS_CLASS, Confidence::CERTAIN));

        $data = $this->formatAndDecode($graph);

        self::assertCount(1, $data['edges']);
        self::assertSame('App\\A', $data['edges'][0]['source']);
        self::assertSame('App\\B', $data['edges'][0]['target']);
        self::assertSame('extends', $data['edges'][0]['type']);
        self::assertSame('certain', $data['edges'][0]['confidence']);
    }

    // ── Warnings ──────────────────────────────────────────────────────────────

    public function testWarningsAppearInOutput(): void
    {
        $warning = new AnalysisWarning(WarningType::PARSE_ERROR, '/file.php', 5, 'Unexpected token');
        $data    = $this->formatAndDecode(new DependencyGraph(), [$warning]);

        self::assertCount(1, $data['warnings']);
        self::assertSame(1, $data['meta']['warning_count']);
        self::assertSame('parse_error', $data['warnings'][0]['type']);
        self::assertSame('/file.php', $data['warnings'][0]['file']);
    }

    // ── Class filter ──────────────────────────────────────────────────────────

    public function testClassFilterLimitsClassesOutput(): void
    {
        $graph = new DependencyGraph();
        $graph->addNode(new GraphNode('App\\A', NodeType::CLASS_NODE));
        $graph->addNode(new GraphNode('App\\B', NodeType::CLASS_NODE));

        $data = $this->formatAndDecode($graph, options: ['class' => 'App\\A']);

        self::assertCount(1, $data['classes']);
        self::assertSame('App\\A', $data['classes'][0]['fqcn']);
    }

    public function testClassFilterLimitsEdgesOutput(): void
    {
        $graph = new DependencyGraph();
        $graph->addNode(new GraphNode('App\\A', NodeType::CLASS_NODE));
        $graph->addNode(new GraphNode('App\\B', NodeType::CLASS_NODE));
        $graph->addNode(new GraphNode('App\\C', NodeType::CLASS_NODE));
        $graph->addEdge(new GraphEdge('App\\A', 'App\\B', EdgeType::EXTENDS_CLASS, Confidence::CERTAIN));
        $graph->addEdge(new GraphEdge('App\\B', 'App\\C', EdgeType::EXTENDS_CLASS, Confidence::CERTAIN));

        $data = $this->formatAndDecode($graph, options: ['class' => 'App\\A']);

        // Only the edge touching App\A should appear
        self::assertCount(1, $data['edges']);
        self::assertSame('App\\A', $data['edges'][0]['source']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function findClass(array $classes, string $fqcn): array
    {
        foreach ($classes as $c) {
            if ($c['fqcn'] === $fqcn) {
                return $c;
            }
        }
        self::fail("Class '{$fqcn}' not found in output");
    }
}
