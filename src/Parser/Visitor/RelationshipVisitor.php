<?php

declare(strict_types=1);

namespace PhpDep\Parser\Visitor;

use PhpDep\Graph\Confidence;
use PhpDep\Graph\EdgeType;
use PhpDep\Graph\GraphEdge;
use PhpDep\Parser\ClassScope;
use PhpDep\Parser\TypeResolver;
use PhpDep\Warning\AnalysisWarning;
use PhpDep\Warning\WarningType;
use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\NodeVisitorAbstract;

final class RelationshipVisitor extends NodeVisitorAbstract
{
    /** @var GraphEdge[] */
    private array $edges = [];

    /** @var AnalysisWarning[] */
    private array $warnings = [];

    private TypeResolver $typeResolver;
    private ClassScope $scope;
    private string $file;

    public function __construct(string $file, TypeResolver $typeResolver, ClassScope $scope)
    {
        $this->file         = $file;
        $this->typeResolver = $typeResolver;
        $this->scope        = $scope;
    }

    public function enterNode(Node $node): null
    {
        $source = $this->scope->current();

        if ($source === null) {
            return null;
        }

        // An anonymous class gives its relations to the class that declares it
        if ($node instanceof Class_) {
            $this->extractClassRelations($source, $node);
        } elseif ($node instanceof Interface_) {
            $this->extractInterfaceRelations($source, $node);
        } elseif ($node instanceof Enum_) {
            $this->extractEnumRelations($source, $node);
        }

        // Trait use statements
        if ($node instanceof TraitUse) {
            foreach ($node->traits as $traitName) {
                $this->addEdge($source, (string) $traitName, EdgeType::USES_TRAIT, Confidence::CERTAIN, $node->getStartLine());
            }
        }

        // Methods, closures and arrow functions type hints
        if ($node instanceof FunctionLike) {
            $this->extractFunctionTypeHints($source, $node);
        }

        // Property type hints
        if ($node instanceof Property) {
            foreach ($this->typeResolver->resolve($node->type) as $fqcn) {
                $this->addEdge($source, $fqcn, EdgeType::PROPERTY_TYPE, Confidence::CERTAIN, $node->getStartLine());
            }
        }

        // Typed class constants (PHP 8.3)
        if ($node instanceof ClassConst) {
            foreach ($this->typeResolver->resolve($node->type) as $fqcn) {
                $this->addEdge($source, $fqcn, EdgeType::CONSTANT_TYPE, Confidence::CERTAIN, $node->getStartLine());
            }
        }

        // #[Foo]
        if ($node instanceof Attribute) {
            $this->addEdge($source, (string) $node->name, EdgeType::ATTRIBUTE, Confidence::CERTAIN, $node->getStartLine());
        }

        // new Foo()
        if ($node instanceof New_) {
            if ($node->class instanceof Name) {
                $this->addEdge($source, (string) $node->class, EdgeType::INSTANTIATES, Confidence::CERTAIN, $node->getStartLine());
            } elseif (!$node->class instanceof Class_) {
                $this->warnings[] = new AnalysisWarning(
                    WarningType::DYNAMIC_INSTANTIATION,
                    $this->file,
                    $node->getStartLine(),
                    'Dynamic instantiation: new $variable()',
                );
            }
        }

        // Foo::method() / Foo::$prop / Foo::CONST
        if ($node instanceof StaticCall && $node->class instanceof Name) {
            $this->addEdge($source, (string) $node->class, EdgeType::STATIC_CALL, Confidence::CERTAIN, $node->getStartLine());
        }

        if ($node instanceof StaticPropertyFetch && $node->class instanceof Name) {
            $this->addEdge($source, (string) $node->class, EdgeType::STATIC_PROPERTY, Confidence::CERTAIN, $node->getStartLine());
        }

        if ($node instanceof ClassConstFetch && $node->class instanceof Name) {
            $className = (string) $node->class;
            if (!in_array(strtolower($className), ['self', 'static', 'parent'], true)) {
                $this->addEdge($source, $className, EdgeType::CONST_ACCESS, Confidence::CERTAIN, $node->getStartLine());
            }
        }

        // instanceof Foo
        if ($node instanceof Instanceof_ && $node->class instanceof Name) {
            $this->addEdge($source, (string) $node->class, EdgeType::INSTANCEOF_CHECK, Confidence::MEDIUM, $node->getStartLine());
        }

        // catch (FooException $e)
        if ($node instanceof Catch_) {
            foreach ($node->types as $exceptionType) {
                $this->addEdge($source, (string) $exceptionType, EdgeType::CATCHES, Confidence::CERTAIN, $node->getStartLine());
            }
        }

        return null;
    }

    private function extractClassRelations(string $source, Class_ $node): void
    {
        if ($node->extends !== null) {
            $this->addEdge($source, (string) $node->extends, EdgeType::EXTENDS_CLASS, Confidence::CERTAIN, $node->getStartLine());
        }

        foreach ($node->implements as $iface) {
            $this->addEdge($source, (string) $iface, EdgeType::IMPLEMENTS_INTERFACE, Confidence::CERTAIN, $node->getStartLine());
        }
    }

    private function extractInterfaceRelations(string $source, Interface_ $node): void
    {
        foreach ($node->extends as $parent) {
            $this->addEdge($source, (string) $parent, EdgeType::EXTENDS_CLASS, Confidence::CERTAIN, $node->getStartLine());
        }
    }

    private function extractEnumRelations(string $source, Enum_ $node): void
    {
        foreach ($node->implements as $iface) {
            $this->addEdge($source, (string) $iface, EdgeType::IMPLEMENTS_INTERFACE, Confidence::CERTAIN, $node->getStartLine());
        }
    }

    private function extractFunctionTypeHints(string $source, FunctionLike $node): void
    {
        foreach ($node->getParams() as $param) {
            foreach ($this->typeResolver->resolve($param->type) as $fqcn) {
                $this->addEdge($source, $fqcn, EdgeType::PARAM_TYPE, Confidence::CERTAIN, $param->getStartLine());
            }
        }

        foreach ($this->typeResolver->resolve($node->getReturnType()) as $fqcn) {
            $this->addEdge($source, $fqcn, EdgeType::RETURN_TYPE, Confidence::CERTAIN, $node->getStartLine());
        }
    }

    private function addEdge(string $source, string $target, EdgeType $type, Confidence $confidence, int $line): void
    {
        // Skip self-referential edges
        if ($source === $target) {
            return;
        }

        // Resolve self/static/parent to current class
        $target = match (strtolower($target)) {
            'self', 'static' => $source,
            'parent'         => $source, // we don't have parent FQCN here; skip
            default          => $target,
        };

        if ($source === $target) {
            return;
        }

        $this->edges[] = new GraphEdge(
            source:     $source,
            target:     $target,
            type:       $type,
            confidence: $confidence,
            file:       $this->file,
            line:       $line > 0 ? $line : null,
        );
    }

    /** @return GraphEdge[] */
    public function getEdges(): array
    {
        return $this->edges;
    }

    /** @return AnalysisWarning[] */
    public function getWarnings(): array
    {
        return $this->warnings;
    }
}
