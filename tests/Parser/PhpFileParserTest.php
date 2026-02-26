<?php

declare(strict_types=1);

namespace PhpDep\Tests\Parser;

use PhpDep\Graph\EdgeType;
use PhpDep\Graph\NodeType;
use PhpDep\Parser\PhpFileParser;
use PhpDep\Warning\WarningType;
use PHPUnit\Framework\TestCase;

final class PhpFileParserTest extends TestCase
{
    private PhpFileParser $parser;
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->parser  = new PhpFileParser();
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'php_dep_test_') . '.php';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    private function write(string $code): void
    {
        file_put_contents($this->tmpFile, $code);
    }

    // ── Error cases ──────────────────────────────────────────────────────────

    public function testParseNonExistentFileReturnsWarning(): void
    {
        $result = $this->parser->parse('/does/not/exist/file.php');

        self::assertEmpty($result['nodes']);
        self::assertEmpty($result['edges']);
        self::assertCount(1, $result['warnings']);
        self::assertSame(WarningType::PARSE_ERROR, $result['warnings'][0]->type);
    }

    public function testParseInvalidPhpReturnsWarning(): void
    {
        $this->write('<?php this is not valid php ??? @@@ !!!');
        $result = $this->parser->parse($this->tmpFile);

        self::assertEmpty($result['nodes']);
        self::assertEmpty($result['edges']);
        self::assertCount(1, $result['warnings']);
        self::assertSame(WarningType::PARSE_ERROR, $result['warnings'][0]->type);
    }

    public function testParseEmptyPhpFileReturnsNothing(): void
    {
        $this->write('<?php');
        $result = $this->parser->parse($this->tmpFile);

        self::assertEmpty($result['nodes']);
        self::assertEmpty($result['edges']);
        self::assertEmpty($result['warnings']);
    }

    // ── Node extraction ───────────────────────────────────────────────────────

    public function testParseSimpleClass(): void
    {
        $this->write('<?php namespace App; class Foo {}');
        $result = $this->parser->parse($this->tmpFile);

        self::assertCount(1, $result['nodes']);
        self::assertSame('App\\Foo', $result['nodes'][0]->fqcn);
        self::assertSame(NodeType::CLASS_NODE, $result['nodes'][0]->type);
    }

    public function testParseInterface(): void
    {
        $this->write('<?php namespace App; interface IFoo {}');
        $result = $this->parser->parse($this->tmpFile);

        self::assertCount(1, $result['nodes']);
        self::assertSame(NodeType::INTERFACE_NODE, $result['nodes'][0]->type);
    }

    public function testParseTrait(): void
    {
        $this->write('<?php namespace App; trait MyTrait {}');
        $result = $this->parser->parse($this->tmpFile);

        self::assertCount(1, $result['nodes']);
        self::assertSame(NodeType::TRAIT_NODE, $result['nodes'][0]->type);
    }

    public function testParseEnum(): void
    {
        $this->write('<?php namespace App; enum Status: string { case Active = "active"; }');
        $result = $this->parser->parse($this->tmpFile);

        self::assertCount(1, $result['nodes']);
        self::assertSame(NodeType::ENUM_NODE, $result['nodes'][0]->type);
    }

    public function testNodeStoresFilePath(): void
    {
        $this->write('<?php namespace App; class Foo {}');
        $result = $this->parser->parse($this->tmpFile);

        self::assertSame($this->tmpFile, $result['nodes'][0]->file);
    }

    // ── Edge extraction ───────────────────────────────────────────────────────

    public function testParseClassExtends(): void
    {
        $this->write('<?php namespace App; class Base {} class Foo extends Base {}');
        $result = $this->parser->parse($this->tmpFile);

        $extendsEdges = array_values(array_filter(
            $result['edges'],
            fn($e) => $e->type === EdgeType::EXTENDS_CLASS,
        ));

        self::assertNotEmpty($extendsEdges);
        self::assertSame('App\\Foo', $extendsEdges[0]->source);
        self::assertSame('App\\Base', $extendsEdges[0]->target);
    }

    public function testParseClassImplements(): void
    {
        $this->write('<?php namespace App; interface IFoo {} class Foo implements IFoo {}');
        $result = $this->parser->parse($this->tmpFile);

        $implEdges = array_values(array_filter(
            $result['edges'],
            fn($e) => $e->type === EdgeType::IMPLEMENTS_INTERFACE,
        ));

        self::assertNotEmpty($implEdges);
        self::assertSame('App\\Foo', $implEdges[0]->source);
        self::assertSame('App\\IFoo', $implEdges[0]->target);
    }

    public function testParseUsesTrait(): void
    {
        $this->write('<?php namespace App; trait T {} class Foo { use T; }');
        $result = $this->parser->parse($this->tmpFile);

        $traitEdges = array_values(array_filter(
            $result['edges'],
            fn($e) => $e->type === EdgeType::USES_TRAIT,
        ));

        self::assertNotEmpty($traitEdges);
        self::assertSame('App\\T', $traitEdges[0]->target);
    }

    public function testParseInstantiation(): void
    {
        $this->write(<<<'PHP'
            <?php
            namespace App;
            class Bar {}
            class Foo {
                public function run(): void { $b = new Bar(); }
            }
            PHP);

        $result = $this->parser->parse($this->tmpFile);

        $newEdges = array_values(array_filter(
            $result['edges'],
            fn($e) => $e->type === EdgeType::INSTANTIATES,
        ));

        self::assertNotEmpty($newEdges);
        self::assertSame('App\\Bar', $newEdges[0]->target);
    }

    public function testParseMethodParamTypeHint(): void
    {
        $this->write(<<<'PHP'
            <?php
            namespace App;
            class Bar {}
            class Foo {
                public function run(Bar $bar): void {}
            }
            PHP);

        $result = $this->parser->parse($this->tmpFile);

        $paramEdges = array_values(array_filter(
            $result['edges'],
            fn($e) => $e->type === EdgeType::PARAM_TYPE,
        ));

        self::assertNotEmpty($paramEdges);
        self::assertSame('App\\Bar', $paramEdges[0]->target);
    }

    // ── Docblock behaviour ────────────────────────────────────────────────────

    public function testDocblocksProduceEdgesByDefault(): void
    {
        $this->write(<<<'PHP'
            <?php
            namespace App;
            class Bar {}
            class Foo {
                /** @param Bar $b */
                public function run($b): void {}
            }
            PHP);

        $result = $this->parser->parse($this->tmpFile, false);

        $docEdges = array_filter(
            $result['edges'],
            fn($e) => in_array($e->type->value, ['docblock_param', 'docblock_return', 'docblock_var', 'docblock_throws'], true),
        );

        self::assertNotEmpty($docEdges);
    }

    public function testSkipDocblocksOmitsDocblockEdges(): void
    {
        $this->write(<<<'PHP'
            <?php
            namespace App;
            class Bar {}
            class Foo {
                /** @param Bar $b */
                public function run($b): void {}
            }
            PHP);

        $result = $this->parser->parse($this->tmpFile, true);

        $docEdges = array_filter(
            $result['edges'],
            fn($e) => in_array($e->type->value, ['docblock_param', 'docblock_return', 'docblock_var', 'docblock_throws'], true),
        );

        self::assertEmpty($docEdges);
    }
}
