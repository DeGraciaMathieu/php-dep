<?php

declare(strict_types=1);

namespace PhpDep\Tests\Graph;

use PhpDep\Graph\Confidence;
use PhpDep\Graph\DependencyGraph;
use PhpDep\Graph\EdgeType;
use PhpDep\Graph\GraphEdge;
use PhpDep\Graph\GraphNode;
use PhpDep\Graph\NodeType;
use PHPUnit\Framework\TestCase;

final class DependencyGraphTest extends TestCase
{
    private DependencyGraph $graph;

    protected function setUp(): void
    {
        $this->graph = new DependencyGraph();
    }

    public function testAddAndHasNode(): void
    {
        $node = new GraphNode('App\\Foo', NodeType::CLASS_NODE);
        $this->graph->addNode($node);

        self::assertTrue($this->graph->hasNode('App\\Foo'));
        self::assertFalse($this->graph->hasNode('App\\Bar'));
    }

    public function testGetNodeReturnsNullForMissing(): void
    {
        self::assertNull($this->graph->getNode('App\\Missing'));
    }

    public function testGetNodeReturnsAddedNode(): void
    {
        $node = new GraphNode('App\\Foo', NodeType::CLASS_NODE);
        $this->graph->addNode($node);

        self::assertSame($node, $this->graph->getNode('App\\Foo'));
    }

    public function testExternalNodeDoesNotOverwriteInternalNode(): void
    {
        $internal = new GraphNode('App\\Foo', NodeType::CLASS_NODE);
        $external = new GraphNode('App\\Foo', NodeType::EXTERNAL);

        $this->graph->addNode($internal);
        $this->graph->addNode($external);

        self::assertSame($internal, $this->graph->getNode('App\\Foo'));
        self::assertFalse($this->graph->getNode('App\\Foo')->isExternal());
    }

    public function testInternalNodeOverwritesExistingExternalNode(): void
    {
        $external = new GraphNode('App\\Foo', NodeType::EXTERNAL);
        $internal = new GraphNode('App\\Foo', NodeType::CLASS_NODE);

        $this->graph->addNode($external);
        $this->graph->addNode($internal);

        self::assertSame($internal, $this->graph->getNode('App\\Foo'));
    }

    public function testAddEdgeCreatesExternalNodesForUnknownEndpoints(): void
    {
        $edge = new GraphEdge('App\\A', 'App\\B', EdgeType::INSTANTIATES, Confidence::HIGH);
        $this->graph->addEdge($edge);

        self::assertTrue($this->graph->hasNode('App\\A'));
        self::assertTrue($this->graph->hasNode('App\\B'));
        self::assertTrue($this->graph->getNode('App\\A')->isExternal());
        self::assertTrue($this->graph->getNode('App\\B')->isExternal());
    }

    public function testAddEdgeDoesNotOverwriteExistingInternalNode(): void
    {
        $nodeA = new GraphNode('App\\A', NodeType::CLASS_NODE);
        $this->graph->addNode($nodeA);

        $this->graph->addEdge(new GraphEdge('App\\A', 'App\\B', EdgeType::INSTANTIATES, Confidence::HIGH));

        self::assertFalse($this->graph->getNode('App\\A')->isExternal());
    }

    public function testGetEdgesFrom(): void
    {
        $edge = new GraphEdge('App\\A', 'App\\B', EdgeType::EXTENDS_CLASS, Confidence::CERTAIN);
        $this->graph->addEdge($edge);

        self::assertCount(1, $this->graph->getEdgesFrom('App\\A'));
        self::assertSame($edge, $this->graph->getEdgesFrom('App\\A')[0]);
        self::assertCount(0, $this->graph->getEdgesFrom('App\\B'));
    }

    public function testGetEdgesTo(): void
    {
        $edge = new GraphEdge('App\\A', 'App\\B', EdgeType::EXTENDS_CLASS, Confidence::CERTAIN);
        $this->graph->addEdge($edge);

        self::assertCount(1, $this->graph->getEdgesTo('App\\B'));
        self::assertSame($edge, $this->graph->getEdgesTo('App\\B')[0]);
        self::assertCount(0, $this->graph->getEdgesTo('App\\A'));
    }

    public function testGetEdgesFromReturnsEmptyArrayForUnknownNode(): void
    {
        self::assertSame([], $this->graph->getEdgesFrom('App\\Unknown'));
    }

    public function testGetEdgesToReturnsEmptyArrayForUnknownNode(): void
    {
        self::assertSame([], $this->graph->getEdgesTo('App\\Unknown'));
    }

    public function testDependenciesOfReturnsUniqueTargets(): void
    {
        // Two edges to same target → only one dependency
        $this->graph->addEdge(new GraphEdge('App\\A', 'App\\B', EdgeType::PARAM_TYPE, Confidence::HIGH));
        $this->graph->addEdge(new GraphEdge('App\\A', 'App\\B', EdgeType::RETURN_TYPE, Confidence::HIGH));

        self::assertSame(['App\\B'], $this->graph->dependenciesOf('App\\A'));
    }

    public function testDependenciesOfReturnsEmptyForUnknownClass(): void
    {
        self::assertSame([], $this->graph->dependenciesOf('App\\Unknown'));
    }

    public function testDependantsOfReturnsUniqueSources(): void
    {
        $this->graph->addEdge(new GraphEdge('App\\A', 'App\\B', EdgeType::PARAM_TYPE, Confidence::HIGH));
        $this->graph->addEdge(new GraphEdge('App\\A', 'App\\B', EdgeType::RETURN_TYPE, Confidence::HIGH));

        self::assertSame(['App\\A'], $this->graph->dependantsOf('App\\B'));
    }

    public function testDependantsOfReturnsEmptyForUnknownClass(): void
    {
        self::assertSame([], $this->graph->dependantsOf('App\\Unknown'));
    }

    public function testDependenciesOfMultipleTargets(): void
    {
        $this->graph->addEdge(new GraphEdge('App\\A', 'App\\B', EdgeType::EXTENDS_CLASS, Confidence::CERTAIN));
        $this->graph->addEdge(new GraphEdge('App\\A', 'App\\C', EdgeType::IMPLEMENTS_INTERFACE, Confidence::CERTAIN));

        $deps = $this->graph->dependenciesOf('App\\A');
        sort($deps);
        self::assertSame(['App\\B', 'App\\C'], $deps);
    }

    public function testGetAllNodes(): void
    {
        $this->graph->addNode(new GraphNode('App\\A', NodeType::CLASS_NODE));
        $this->graph->addNode(new GraphNode('App\\B', NodeType::INTERFACE_NODE));

        self::assertCount(2, $this->graph->getAllNodes());
    }

    public function testGetInternalNodesExcludesExternal(): void
    {
        $this->graph->addNode(new GraphNode('App\\A', NodeType::CLASS_NODE));
        $this->graph->addNode(new GraphNode('Ext\\B', NodeType::EXTERNAL));

        $internal = $this->graph->getInternalNodes();
        self::assertCount(1, $internal);
        self::assertSame('App\\A', $internal[0]->fqcn);
    }

    public function testGetInternalNodesReturnsAllNonExternalTypes(): void
    {
        $this->graph->addNode(new GraphNode('App\\A', NodeType::CLASS_NODE));
        $this->graph->addNode(new GraphNode('App\\B', NodeType::INTERFACE_NODE));
        $this->graph->addNode(new GraphNode('App\\C', NodeType::TRAIT_NODE));
        $this->graph->addNode(new GraphNode('App\\D', NodeType::ENUM_NODE));
        $this->graph->addNode(new GraphNode('Ext\\E', NodeType::EXTERNAL));

        self::assertCount(4, $this->graph->getInternalNodes());
    }

    public function testGetNodeCount(): void
    {
        self::assertSame(0, $this->graph->getNodeCount());
        $this->graph->addNode(new GraphNode('App\\A', NodeType::CLASS_NODE));
        $this->graph->addNode(new GraphNode('App\\B', NodeType::CLASS_NODE));
        self::assertSame(2, $this->graph->getNodeCount());
    }

    public function testGetEdgeCount(): void
    {
        self::assertSame(0, $this->graph->getEdgeCount());
        $this->graph->addEdge(new GraphEdge('App\\A', 'App\\B', EdgeType::EXTENDS_CLASS, Confidence::CERTAIN));
        $this->graph->addEdge(new GraphEdge('App\\A', 'App\\C', EdgeType::PARAM_TYPE, Confidence::HIGH));
        self::assertSame(2, $this->graph->getEdgeCount());
    }

    public function testGetAllEdgesPreservesInsertionOrder(): void
    {
        $edge1 = new GraphEdge('App\\A', 'App\\B', EdgeType::EXTENDS_CLASS, Confidence::CERTAIN);
        $edge2 = new GraphEdge('App\\A', 'App\\C', EdgeType::PARAM_TYPE, Confidence::HIGH);
        $this->graph->addEdge($edge1);
        $this->graph->addEdge($edge2);

        self::assertSame([$edge1, $edge2], $this->graph->getAllEdges());
    }
}
