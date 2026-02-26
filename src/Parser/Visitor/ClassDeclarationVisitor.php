<?php

declare(strict_types=1);

namespace PhpDep\Parser\Visitor;

use PhpDep\Graph\GraphNode;
use PhpDep\Graph\NodeType;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\NodeVisitorAbstract;

final class ClassDeclarationVisitor extends NodeVisitorAbstract
{
    /** @var GraphNode[] */
    private array $nodes = [];

    private string $file;

    public function __construct(string $file)
    {
        $this->file = $file;
    }

    public function enterNode(Node $node): null
    {
        $fqcn     = null;
        $nodeType = null;

        if ($node instanceof Class_ && $node->name !== null) {
            $fqcn     = (string) $node->namespacedName;
            $nodeType = NodeType::CLASS_NODE;
        } elseif ($node instanceof Interface_) {
            $fqcn     = (string) $node->namespacedName;
            $nodeType = NodeType::INTERFACE_NODE;
        } elseif ($node instanceof Trait_) {
            $fqcn     = (string) $node->namespacedName;
            $nodeType = NodeType::TRAIT_NODE;
        } elseif ($node instanceof Enum_) {
            $fqcn     = (string) $node->namespacedName;
            $nodeType = NodeType::ENUM_NODE;
        }

        if ($fqcn !== null && $nodeType !== null) {
            $this->nodes[] = new GraphNode(
                fqcn: $fqcn,
                type: $nodeType,
                file: $this->file,
                line: $node->getStartLine() > 0 ? $node->getStartLine() : null,
            );
        }

        return null;
    }

    /** @return GraphNode[] */
    public function getNodes(): array
    {
        return $this->nodes;
    }
}
