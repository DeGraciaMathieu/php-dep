<?php

declare(strict_types=1);

namespace PhpDep\Tests\Parser\Visitor;

use PhpDep\Graph\NodeType;
use PhpDep\Parser\Visitor\ClassDeclarationVisitor;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

final class ClassDeclarationVisitorTest extends TestCase
{
    private function traverse(string $code, string $file = '/src/Test.php'): ClassDeclarationVisitor
    {
        $parser    = (new ParserFactory())->createForNewestSupportedVersion();
        $stmts     = $parser->parse($code);
        $traverser = new NodeTraverser();
        $visitor   = new ClassDeclarationVisitor($file);

        $traverser->addVisitor(new NameResolver());
        $traverser->addVisitor($visitor);
        $traverser->traverse($stmts);

        return $visitor;
    }

    public function testExtractsClass(): void
    {
        $visitor = $this->traverse('<?php namespace App; class Foo {}');
        $nodes   = $visitor->getNodes();

        self::assertCount(1, $nodes);
        self::assertSame('App\\Foo', $nodes[0]->fqcn);
        self::assertSame(NodeType::CLASS_NODE, $nodes[0]->type);
    }

    public function testExtractsInterface(): void
    {
        $visitor = $this->traverse('<?php namespace App; interface IFoo {}');
        $nodes   = $visitor->getNodes();

        self::assertCount(1, $nodes);
        self::assertSame('App\\IFoo', $nodes[0]->fqcn);
        self::assertSame(NodeType::INTERFACE_NODE, $nodes[0]->type);
    }

    public function testExtractsTrait(): void
    {
        $visitor = $this->traverse('<?php namespace App; trait MyTrait {}');
        $nodes   = $visitor->getNodes();

        self::assertCount(1, $nodes);
        self::assertSame('App\\MyTrait', $nodes[0]->fqcn);
        self::assertSame(NodeType::TRAIT_NODE, $nodes[0]->type);
    }

    public function testExtractsEnum(): void
    {
        $visitor = $this->traverse('<?php namespace App; enum Status: string { case Active = "active"; }');
        $nodes   = $visitor->getNodes();

        self::assertCount(1, $nodes);
        self::assertSame('App\\Status', $nodes[0]->fqcn);
        self::assertSame(NodeType::ENUM_NODE, $nodes[0]->type);
    }

    public function testSkipsAnonymousClass(): void
    {
        $visitor = $this->traverse('<?php $obj = new class {};');

        self::assertEmpty($visitor->getNodes());
    }

    public function testStoresFilePath(): void
    {
        $visitor = $this->traverse('<?php namespace App; class Foo {}', '/my/path/Foo.php');
        $nodes   = $visitor->getNodes();

        self::assertSame('/my/path/Foo.php', $nodes[0]->file);
    }

    public function testStoresLineNumber(): void
    {
        $visitor = $this->traverse("<?php\nnamespace App;\nclass Foo {}");
        $nodes   = $visitor->getNodes();

        self::assertNotNull($nodes[0]->line);
        self::assertGreaterThan(0, $nodes[0]->line);
    }

    public function testMultipleDeclarationsInOneFile(): void
    {
        $code = <<<'PHP'
            <?php
            namespace App;
            class A {}
            interface B {}
            trait C {}
            PHP;

        $visitor = $this->traverse($code);
        $nodes   = $visitor->getNodes();

        self::assertCount(3, $nodes);

        $fqcns = array_map(fn($n) => $n->fqcn, $nodes);
        self::assertContains('App\\A', $fqcns);
        self::assertContains('App\\B', $fqcns);
        self::assertContains('App\\C', $fqcns);
    }

    public function testClassWithoutNamespaceUsesShortName(): void
    {
        $visitor = $this->traverse('<?php class Global_ {}');
        $nodes   = $visitor->getNodes();

        self::assertCount(1, $nodes);
        self::assertSame('Global_', $nodes[0]->fqcn);
    }
}
