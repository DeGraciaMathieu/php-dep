<?php

declare(strict_types=1);

namespace PhpDep\Parser\Visitor;

use PhpDep\Graph\Confidence;
use PhpDep\Graph\EdgeType;
use PhpDep\Graph\GraphEdge;
use PHPStan\PhpDocParser\Ast\PhpDoc\InvalidTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ParamTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ReturnTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ThrowsTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\VarTagValueNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\NullableTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\NodeVisitorAbstract;

final class DocblockVisitor extends NodeVisitorAbstract
{
    /** @var GraphEdge[] */
    private array $edges = [];

    /** @var string[] class FQCN stack */
    private array $classStack = [];

    private string $file;
    private Lexer $lexer;
    private PhpDocParser $parser;

    /** PHP scalars to skip in docblocks */
    private const SCALARS = [
        'int', 'integer', 'float', 'double', 'string', 'bool', 'boolean',
        'array', 'void', 'never', 'mixed', 'null', 'false', 'true', 'object',
        'iterable', 'callable', 'resource', 'self', 'static', 'parent',
        '$this', 'list', 'non-empty-array', 'non-empty-string',
    ];

    public function __construct(string $file)
    {
        $this->file   = $file;
        $this->lexer  = new Lexer();
        $constExprParser = new ConstExprParser();
        $typeParser   = new TypeParser($constExprParser);
        $this->parser = new PhpDocParser($typeParser, $constExprParser);
    }

    public function enterNode(Node $node): null
    {
        if ($node instanceof Class_ && $node->name !== null) {
            $this->classStack[] = (string) $node->namespacedName;
        } elseif ($node instanceof Interface_) {
            $this->classStack[] = (string) $node->namespacedName;
        } elseif ($node instanceof Trait_) {
            $this->classStack[] = (string) $node->namespacedName;
        } elseif ($node instanceof Enum_) {
            $this->classStack[] = (string) $node->namespacedName;
        }

        if (empty($this->classStack)) {
            return null;
        }

        $docComment = $node->getDocComment();
        if ($docComment === null) {
            return null;
        }

        if ($node instanceof ClassMethod || $node instanceof Property) {
            $this->parseDocblock($docComment, end($this->classStack), $node->getStartLine());
        }

        return null;
    }

    public function leaveNode(Node $node): null
    {
        if ($node instanceof Class_ || $node instanceof Interface_ || $node instanceof Trait_ || $node instanceof Enum_) {
            array_pop($this->classStack);
        }

        return null;
    }

    private function parseDocblock(Doc $doc, string $source, int $line): void
    {
        try {
            $tokens   = new TokenIterator($this->lexer->tokenize($doc->getText()));
            $phpDoc   = $this->parser->parse($tokens);
        } catch (\Throwable) {
            return;
        }

        foreach ($phpDoc->getTags() as $tag) {
            $value = $tag->value;

            if ($value instanceof InvalidTagValueNode) {
                continue;
            }

            $edgeType = match ($tag->name) {
                '@param'  => EdgeType::DOCBLOCK_PARAM,
                '@return' => EdgeType::DOCBLOCK_RETURN,
                '@var'    => EdgeType::DOCBLOCK_VAR,
                '@throws' => EdgeType::DOCBLOCK_THROWS,
                default   => null,
            };

            if ($edgeType === null) {
                continue;
            }

            $typeNode = match (true) {
                $value instanceof ParamTagValueNode  => $value->type,
                $value instanceof ReturnTagValueNode => $value->type,
                $value instanceof VarTagValueNode    => $value->type,
                $value instanceof ThrowsTagValueNode => $value->type,
                default                              => null,
            };

            if ($typeNode === null) {
                continue;
            }

            foreach ($this->extractFqcnsFromType($typeNode) as $fqcn) {
                if ($source !== $fqcn) {
                    $this->edges[] = new GraphEdge(
                        source:     $source,
                        target:     $fqcn,
                        type:       $edgeType,
                        confidence: Confidence::HIGH,
                        file:       $this->file,
                        line:       $line > 0 ? $line : null,
                    );
                }
            }
        }
    }

    /** @return string[] */
    private function extractFqcnsFromType(TypeNode $type): array
    {
        if ($type instanceof IdentifierTypeNode) {
            $name = ltrim($type->name, '\\');
            if ($this->isScalar($name)) {
                return [];
            }
            return [$name];
        }

        if ($type instanceof NullableTypeNode) {
            return $this->extractFqcnsFromType($type->type);
        }

        if ($type instanceof UnionTypeNode) {
            $fqcns = [];
            foreach ($type->types as $t) {
                array_push($fqcns, ...$this->extractFqcnsFromType($t));
            }
            return $fqcns;
        }

        // Handle generic types (e.g., array<Foo>, Collection<int, Bar>)
        if (property_exists($type, 'genericTypes')) {
            $fqcns = [];
            foreach ($type->genericTypes as $t) {
                array_push($fqcns, ...$this->extractFqcnsFromType($t));
            }
            return $fqcns;
        }

        return [];
    }

    private function isScalar(string $name): bool
    {
        return in_array(strtolower($name), self::SCALARS, true);
    }

    /** @return GraphEdge[] */
    public function getEdges(): array
    {
        return $this->edges;
    }
}
