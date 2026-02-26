<?php

declare(strict_types=1);

namespace PhpDep\Tests\Parser\Visitor;

use PhpDep\Graph\EdgeType;
use PhpDep\Parser\TypeResolver;
use PhpDep\Parser\Visitor\RelationshipVisitor;
use PhpDep\Warning\WarningType;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

final class RelationshipVisitorTest extends TestCase
{
    private function traverse(string $code, string $file = '/src/Test.php'): RelationshipVisitor
    {
        $parser    = (new ParserFactory())->createForNewestSupportedVersion();
        $stmts     = $parser->parse($code);
        $traverser = new NodeTraverser();
        $visitor   = new RelationshipVisitor($file, new TypeResolver());

        $traverser->addVisitor(new NameResolver());
        $traverser->addVisitor($visitor);
        $traverser->traverse($stmts);

        return $visitor;
    }

    private function edgesOfType(RelationshipVisitor $visitor, EdgeType $type): array
    {
        return array_values(array_filter(
            $visitor->getEdges(),
            fn($e) => $e->type === $type,
        ));
    }

    // ── Structural relations ──────────────────────────────────────────────────

    public function testExtractsExtendsEdge(): void
    {
        $visitor = $this->traverse('<?php namespace App; class Base {} class Child extends Base {}');
        $edges   = $this->edgesOfType($visitor, EdgeType::EXTENDS_CLASS);

        self::assertCount(1, $edges);
        self::assertSame('App\\Child', $edges[0]->source);
        self::assertSame('App\\Base', $edges[0]->target);
    }

    public function testExtractsImplementsEdge(): void
    {
        $visitor = $this->traverse('<?php namespace App; interface IFoo {} class Foo implements IFoo {}');
        $edges   = $this->edgesOfType($visitor, EdgeType::IMPLEMENTS_INTERFACE);

        self::assertCount(1, $edges);
        self::assertSame('App\\Foo', $edges[0]->source);
        self::assertSame('App\\IFoo', $edges[0]->target);
    }

    public function testExtractsUsesTraitEdge(): void
    {
        $visitor = $this->traverse('<?php namespace App; trait T {} class Foo { use T; }');
        $edges   = $this->edgesOfType($visitor, EdgeType::USES_TRAIT);

        self::assertCount(1, $edges);
        self::assertSame('App\\Foo', $edges[0]->source);
        self::assertSame('App\\T', $edges[0]->target);
    }

    public function testInterfaceExtendsInterface(): void
    {
        $visitor = $this->traverse('<?php namespace App; interface A {} interface B extends A {}');
        $edges   = $this->edgesOfType($visitor, EdgeType::EXTENDS_CLASS);

        self::assertCount(1, $edges);
        self::assertSame('App\\B', $edges[0]->source);
        self::assertSame('App\\A', $edges[0]->target);
    }

    public function testEnumImplementsInterface(): void
    {
        $visitor = $this->traverse('<?php namespace App; interface Countable {} enum E: int implements Countable { case A = 1; }');
        $edges   = $this->edgesOfType($visitor, EdgeType::IMPLEMENTS_INTERFACE);

        self::assertCount(1, $edges);
        self::assertSame('App\\E', $edges[0]->source);
        self::assertSame('App\\Countable', $edges[0]->target);
    }

    // ── Type hints ────────────────────────────────────────────────────────────

    public function testExtractsParamTypeEdge(): void
    {
        $code = <<<'PHP'
            <?php
            namespace App;
            class Bar {}
            class Foo {
                public function run(Bar $b): void {}
            }
            PHP;

        $visitor = $this->traverse($code);
        $edges   = $this->edgesOfType($visitor, EdgeType::PARAM_TYPE);

        self::assertCount(1, $edges);
        self::assertSame('App\\Foo', $edges[0]->source);
        self::assertSame('App\\Bar', $edges[0]->target);
    }

    public function testExtractsReturnTypeEdge(): void
    {
        $code = <<<'PHP'
            <?php
            namespace App;
            class Bar {}
            class Foo {
                public function make(): Bar {}
            }
            PHP;

        $visitor = $this->traverse($code);
        $edges   = $this->edgesOfType($visitor, EdgeType::RETURN_TYPE);

        self::assertCount(1, $edges);
        self::assertSame('App\\Bar', $edges[0]->target);
    }

    public function testExtractsPropertyTypeEdge(): void
    {
        $code = <<<'PHP'
            <?php
            namespace App;
            class Bar {}
            class Foo {
                private Bar $bar;
            }
            PHP;

        $visitor = $this->traverse($code);
        $edges   = $this->edgesOfType($visitor, EdgeType::PROPERTY_TYPE);

        self::assertCount(1, $edges);
        self::assertSame('App\\Bar', $edges[0]->target);
    }

    // ── Instantiation & calls ─────────────────────────────────────────────────

    public function testExtractsInstantiationEdge(): void
    {
        $code = <<<'PHP'
            <?php
            namespace App;
            class Bar {}
            class Foo {
                public function run(): void { $b = new Bar(); }
            }
            PHP;

        $visitor = $this->traverse($code);
        $edges   = $this->edgesOfType($visitor, EdgeType::INSTANTIATES);

        self::assertCount(1, $edges);
        self::assertSame('App\\Bar', $edges[0]->target);
    }

    public function testDynamicInstantiationProducesWarning(): void
    {
        $code = <<<'PHP'
            <?php
            namespace App;
            class Foo {
                public function run(string $cls): void { $b = new $cls(); }
            }
            PHP;

        $visitor = $this->traverse($code);

        self::assertEmpty($this->edgesOfType($visitor, EdgeType::INSTANTIATES));
        self::assertCount(1, $visitor->getWarnings());
        self::assertSame(WarningType::DYNAMIC_INSTANTIATION, $visitor->getWarnings()[0]->type);
    }

    public function testExtractsStaticCallEdge(): void
    {
        $code = <<<'PHP'
            <?php
            namespace App;
            class Builder {}
            class Foo {
                public function run(): void { Builder::create(); }
            }
            PHP;

        $visitor = $this->traverse($code);
        $edges   = $this->edgesOfType($visitor, EdgeType::STATIC_CALL);

        self::assertCount(1, $edges);
        self::assertSame('App\\Builder', $edges[0]->target);
    }

    public function testExtractsInstanceofEdge(): void
    {
        $code = <<<'PHP'
            <?php
            namespace App;
            class Bar {}
            class Foo {
                public function check(object $x): bool { return $x instanceof Bar; }
            }
            PHP;

        $visitor = $this->traverse($code);
        $edges   = $this->edgesOfType($visitor, EdgeType::INSTANCEOF_CHECK);

        self::assertCount(1, $edges);
        self::assertSame('App\\Bar', $edges[0]->target);
    }

    public function testExtractsCatchesEdge(): void
    {
        $code = <<<'PHP'
            <?php
            namespace App;
            class MyException extends \Exception {}
            class Foo {
                public function run(): void {
                    try { throw new MyException(); }
                    catch (MyException $e) {}
                }
            }
            PHP;

        $visitor = $this->traverse($code);
        $edges   = $this->edgesOfType($visitor, EdgeType::CATCHES);

        self::assertCount(1, $edges);
        self::assertSame('App\\MyException', $edges[0]->target);
    }

    // ── Self-reference guards ─────────────────────────────────────────────────

    public function testSelfKeywordDoesNotProduceSelfReferentialEdge(): void
    {
        $code = <<<'PHP'
            <?php
            namespace App;
            class Foo {
                public static function create(): self { return new self(); }
            }
            PHP;

        $visitor = $this->traverse($code);

        $selfEdges = array_filter(
            $visitor->getEdges(),
            fn($e) => $e->source === $e->target,
        );

        self::assertCount(0, $selfEdges);
    }

    // ── Context isolation ─────────────────────────────────────────────────────

    public function testNoEdgesProducedOutsideClassContext(): void
    {
        // A top-level function — no class context
        $visitor = $this->traverse('<?php function foo(\\DateTimeInterface $dt): void {}');

        self::assertEmpty($visitor->getEdges());
    }

    public function testFilePathIsStoredOnEdge(): void
    {
        $code = '<?php namespace App; class Base {} class Child extends Base {}';
        $visitor = $this->traverse($code, '/custom/path.php');
        $edges   = $visitor->getEdges();

        self::assertNotEmpty($edges);
        self::assertSame('/custom/path.php', $edges[0]->file);
    }
}
