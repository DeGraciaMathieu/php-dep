<?php

declare(strict_types=1);

namespace PhpDep\Tests\Parser\Visitor;

use PhpDep\Graph\EdgeType;
use PhpDep\Parser\Visitor\DocblockVisitor;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

final class DocblockVisitorTest extends TestCase
{
    private function traverse(string $code, string $file = '/src/Test.php'): DocblockVisitor
    {
        $parser    = (new ParserFactory())->createForNewestSupportedVersion();
        $stmts     = $parser->parse($code);
        $traverser = new NodeTraverser();
        $visitor   = new DocblockVisitor($file);

        $traverser->addVisitor(new NameResolver());
        $traverser->addVisitor($visitor);
        $traverser->traverse($stmts);

        return $visitor;
    }

    private function edgesOfType(DocblockVisitor $visitor, EdgeType $type): array
    {
        return array_values(array_filter(
            $visitor->getEdges(),
            fn($e) => $e->type === $type,
        ));
    }

    // ── Tag extraction ────────────────────────────────────────────────────────

    public function testDocblockParamProducesEdge(): void
    {
        $code = <<<'PHP'
            <?php
            namespace App;
            class Bar {}
            class Foo {
                /** @param Bar $b */
                public function run($b): void {}
            }
            PHP;

        $visitor = $this->traverse($code);
        $edges   = $this->edgesOfType($visitor, EdgeType::DOCBLOCK_PARAM);

        self::assertCount(1, $edges);
        self::assertSame('App\\Foo', $edges[0]->source);
        self::assertSame('Bar', $edges[0]->target);
    }

    public function testDocblockReturnProducesEdge(): void
    {
        $code = <<<'PHP'
            <?php
            namespace App;
            class Bar {}
            class Foo {
                /** @return Bar */
                public function get() {}
            }
            PHP;

        $visitor = $this->traverse($code);
        $edges   = $this->edgesOfType($visitor, EdgeType::DOCBLOCK_RETURN);

        self::assertCount(1, $edges);
        self::assertSame('Bar', $edges[0]->target);
    }

    public function testDocblockVarProducesEdge(): void
    {
        $code = <<<'PHP'
            <?php
            namespace App;
            class Bar {}
            class Foo {
                /** @var Bar */
                private $bar;
            }
            PHP;

        $visitor = $this->traverse($code);
        $edges   = $this->edgesOfType($visitor, EdgeType::DOCBLOCK_VAR);

        self::assertCount(1, $edges);
        self::assertSame('Bar', $edges[0]->target);
    }

    public function testDocblockThrowsProducesEdge(): void
    {
        $code = <<<'PHP'
            <?php
            namespace App;
            class MyException extends \Exception {}
            class Foo {
                /** @throws MyException */
                public function run(): void {}
            }
            PHP;

        $visitor = $this->traverse($code);
        $edges   = $this->edgesOfType($visitor, EdgeType::DOCBLOCK_THROWS);

        self::assertCount(1, $edges);
        self::assertSame('MyException', $edges[0]->target);
    }

    // ── Scalar filtering ──────────────────────────────────────────────────────

    public function testScalarParamTypeProducesNoEdge(): void
    {
        $code = <<<'PHP'
            <?php
            namespace App;
            class Foo {
                /** @param int $n */
                public function run($n): void {}
            }
            PHP;

        $visitor = $this->traverse($code);

        self::assertEmpty($visitor->getEdges());
    }

    public function testScalarReturnTypeProducesNoEdge(): void
    {
        $code = <<<'PHP'
            <?php
            namespace App;
            class Foo {
                /** @return string */
                public function name(): string {}
            }
            PHP;

        $visitor = $this->traverse($code);

        self::assertEmpty($visitor->getEdges());
    }

    // ── Nullable & union ──────────────────────────────────────────────────────

    public function testNullableDocblockClassTypeProducesEdge(): void
    {
        $code = <<<'PHP'
            <?php
            namespace App;
            class Bar {}
            class Foo {
                /** @param Bar|null $b */
                public function run($b): void {}
            }
            PHP;

        $visitor = $this->traverse($code);
        $edges   = $this->edgesOfType($visitor, EdgeType::DOCBLOCK_PARAM);

        self::assertCount(1, $edges);
        self::assertSame('Bar', $edges[0]->target);
    }

    public function testUnionDocblockTypeExtractsBothClasses(): void
    {
        $code = <<<'PHP'
            <?php
            namespace App;
            class Foo {
                /** @return Bar|Baz */
                public function get() {}
            }
            PHP;

        $visitor = $this->traverse($code);
        $edges   = $this->edgesOfType($visitor, EdgeType::DOCBLOCK_RETURN);

        self::assertCount(2, $edges);
        $targets = array_map(fn($e) => $e->target, $edges);
        self::assertContains('Bar', $targets);
        self::assertContains('Baz', $targets);
    }

    public function testUnionWithScalarsExtractsOnlyClasses(): void
    {
        $code = <<<'PHP'
            <?php
            namespace App;
            class Foo {
                /** @param MyClass|int|string $val */
                public function run($val): void {}
            }
            PHP;

        $visitor = $this->traverse($code);
        $edges   = $this->edgesOfType($visitor, EdgeType::DOCBLOCK_PARAM);

        self::assertCount(1, $edges);
        self::assertSame('MyClass', $edges[0]->target);
    }

    // ── Context isolation ─────────────────────────────────────────────────────

    public function testNoEdgesProducedOutsideClassContext(): void
    {
        $code = <<<'PHP'
            <?php
            /** @param Bar $b */
            function foo($b): void {}
            PHP;

        $visitor = $this->traverse($code);

        self::assertEmpty($visitor->getEdges());
    }

    public function testSelfReferentialDocblockEdgeIsSkipped(): void
    {
        $code = <<<'PHP'
            <?php
            namespace App;
            class Foo {
                /** @return Foo */
                public function self(): Foo {}
            }
            PHP;

        $visitor = $this->traverse($code);

        foreach ($visitor->getEdges() as $edge) {
            self::assertNotSame($edge->source, $edge->target);
        }
    }
}
