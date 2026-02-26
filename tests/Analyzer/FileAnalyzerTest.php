<?php

declare(strict_types=1);

namespace PhpDep\Tests\Analyzer;

use PhpDep\Analyzer\AnalyzerConfig;
use PhpDep\Analyzer\FileAnalyzer;
use PhpDep\Graph\NodeType;
use PhpDep\Warning\WarningType;
use PHPUnit\Framework\TestCase;

final class FileAnalyzerTest extends TestCase
{
    private FileAnalyzer $analyzer;
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->analyzer = new FileAnalyzer();
        $this->tmpFile  = tempnam(sys_get_temp_dir(), 'php_dep_fa_') . '.php';
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

    public function testAnalyzeReturnsNodesForSimpleClass(): void
    {
        $this->write('<?php namespace App; class Foo {}');

        $result = $this->analyzer->analyze($this->tmpFile, new AnalyzerConfig());

        self::assertCount(1, $result['nodes']);
        self::assertSame('App\\Foo', $result['nodes'][0]->fqcn);
        self::assertSame(NodeType::CLASS_NODE, $result['nodes'][0]->type);
        self::assertEmpty($result['warnings']);
    }

    public function testAnalyzeReturnsWarningForNonExistentFile(): void
    {
        $result = $this->analyzer->analyze('/no/such/file.php', new AnalyzerConfig());

        self::assertEmpty($result['nodes']);
        self::assertEmpty($result['edges']);
        self::assertCount(1, $result['warnings']);
        self::assertSame(WarningType::PARSE_ERROR, $result['warnings'][0]->type);
    }

    public function testAnalyzeRespectsSkipDocblocksFlag(): void
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

        $withDocblocks    = $this->analyzer->analyze($this->tmpFile, new AnalyzerConfig(skipDocblocks: false));
        $withoutDocblocks = $this->analyzer->analyze($this->tmpFile, new AnalyzerConfig(skipDocblocks: true));

        $isDocblockEdge = fn($e) => str_starts_with($e->type->value, 'docblock_');

        self::assertNotEmpty(array_filter($withDocblocks['edges'], $isDocblockEdge));
        self::assertEmpty(array_filter($withoutDocblocks['edges'], $isDocblockEdge));
    }

    public function testAnalyzeExtractsEdges(): void
    {
        $this->write(<<<'PHP'
            <?php
            namespace App;
            class Base {}
            class Child extends Base {}
            PHP);

        $result = $this->analyzer->analyze($this->tmpFile, new AnalyzerConfig());

        $edgeTypes = array_map(fn($e) => $e->type->value, $result['edges']);
        self::assertContains('extends', $edgeTypes);
    }
}
