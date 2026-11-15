<?php

declare(strict_types=1);

namespace PhpDep\Parser;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\NodeVisitorAbstract;

/**
 * Tracks the class being traversed, for the visitors that attribute what they find to it.
 * Must be added to the traverser before them: it enters a class first and leaves it last.
 */
final class ClassScope extends NodeVisitorAbstract
{
    /** @var string[] class FQCN stack */
    private array $stack = [];

    public function current(): ?string
    {
        return $this->stack === [] ? null : end($this->stack);
    }

    public function enterNode(Node $node): null
    {
        if ($this->declaresNamedClass($node)) {
            $this->stack[] = (string) $node->namespacedName;
        }

        return null;
    }

    public function leaveNode(Node $node): null
    {
        if ($this->declaresNamedClass($node)) {
            array_pop($this->stack);
        }

        return null;
    }

    /** An anonymous class belongs to the class that declares it */
    private function declaresNamedClass(Node $node): bool
    {
        return $node instanceof ClassLike && $node->name !== null;
    }
}
