<?php

declare(strict_types=1);

namespace PhpDep\Graph;

final class DependencyGraph
{
    /** @var array<string, GraphNode> */
    private array $nodes = [];

    /** @var array<string, GraphEdge[]> adjacency list: source FQCN → edges */
    private array $edgesFrom = [];

    /** @var array<string, GraphEdge[]> reverse index: target FQCN → edges */
    private array $edgesTo = [];

    /** @var GraphEdge[] */
    private array $allEdges = [];

    public function addNode(GraphNode $node): void
    {
        // Don't overwrite a known node with an external placeholder
        if (isset($this->nodes[$node->fqcn]) && !$this->nodes[$node->fqcn]->isExternal()) {
            return;
        }
        $this->nodes[$node->fqcn] = $node;
    }

    public function addEdge(GraphEdge $edge): void
    {
        // Ensure both endpoints exist as nodes (external if unknown)
        foreach ([$edge->source, $edge->target] as $fqcn) {
            if (!isset($this->nodes[$fqcn])) {
                $this->nodes[$fqcn] = new GraphNode($fqcn, NodeType::EXTERNAL);
            }
        }

        $this->edgesFrom[$edge->source][] = $edge;
        $this->edgesTo[$edge->target][]   = $edge;
        $this->allEdges[]                  = $edge;
    }

    public function hasNode(string $fqcn): bool
    {
        return isset($this->nodes[$fqcn]);
    }

    public function getNode(string $fqcn): ?GraphNode
    {
        return $this->nodes[$fqcn] ?? null;
    }

    /** @return GraphNode[] */
    public function getAllNodes(): array
    {
        return array_values($this->nodes);
    }

    /** @return GraphEdge[] */
    public function getAllEdges(): array
    {
        return $this->allEdges;
    }

    /** Edges going OUT from $fqcn (what $fqcn depends on). @return GraphEdge[] */
    public function getEdgesFrom(string $fqcn): array
    {
        return $this->edgesFrom[$fqcn] ?? [];
    }

    /** Edges coming IN to $fqcn (what depends on $fqcn). @return GraphEdge[] */
    public function getEdgesTo(string $fqcn): array
    {
        return $this->edgesTo[$fqcn] ?? [];
    }

    /** Direct dependencies of $fqcn (targets of outgoing edges). @return string[] */
    public function dependenciesOf(string $fqcn): array
    {
        return array_values(array_unique(array_map(
            fn(GraphEdge $e) => $e->target,
            $this->getEdgesFrom($fqcn)
        )));
    }

    /** Direct dependants of $fqcn (sources of incoming edges). @return string[] */
    public function dependantsOf(string $fqcn): array
    {
        return array_values(array_unique(array_map(
            fn(GraphEdge $e) => $e->source,
            $this->getEdgesTo($fqcn)
        )));
    }

    public function getNodeCount(): int
    {
        return count($this->nodes);
    }

    public function getEdgeCount(): int
    {
        return count($this->allEdges);
    }

    /** @return GraphNode[] only internal (non-external) nodes */
    public function getInternalNodes(): array
    {
        return array_values(array_filter(
            $this->nodes,
            fn(GraphNode $n) => !$n->isExternal()
        ));
    }
}
