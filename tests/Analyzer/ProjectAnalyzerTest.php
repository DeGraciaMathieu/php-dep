<?php

declare(strict_types=1);

namespace PhpDep\Tests\Analyzer;

use PhpDep\Analyzer\AnalyzerConfig;
use PhpDep\Analyzer\ProjectAnalyzer;
use PhpDep\Graph\EdgeType;
use PHPUnit\Framework\TestCase;

final class ProjectAnalyzerTest extends TestCase
{
    private string $tmpDir;
    private ProjectAnalyzer $analyzer;

    protected function setUp(): void
    {
        $this->tmpDir   = sys_get_temp_dir() . '/php_dep_proj_' . uniqid();
        $this->analyzer = new ProjectAnalyzer();
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->rmdirRecursive($this->tmpDir);
    }

    private function rmdirRecursive(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->rmdirRecursive($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testAnalyzeEmptyDirectoryReturnsEmptyResult(): void
    {
        $result = $this->analyzer->analyze($this->tmpDir, new AnalyzerConfig());

        self::assertSame(0, $result['graph']->getNodeCount());
        self::assertSame(0, $result['graph']->getEdgeCount());
        self::assertSame([], $result['warnings']);
        self::assertSame(0, $result['file_count']);
    }

    public function testAnalyzeBuildsGraphFromPhpFiles(): void
    {
        file_put_contents($this->tmpDir . '/A.php', '<?php namespace App; class A {}');
        file_put_contents($this->tmpDir . '/B.php', '<?php namespace App; class B extends A {}');

        $result = $this->analyzer->analyze($this->tmpDir, new AnalyzerConfig());
        $graph  = $result['graph'];

        self::assertTrue($graph->hasNode('App\\A'));
        self::assertTrue($graph->hasNode('App\\B'));

        $edgeTypes = array_map(fn($e) => $e->type, $graph->getAllEdges());
        self::assertContains(EdgeType::EXTENDS_CLASS, $edgeTypes);
    }

    public function testAnalyzeReturnsCorrectFileCount(): void
    {
        file_put_contents($this->tmpDir . '/A.php', '<?php class A {}');
        file_put_contents($this->tmpDir . '/B.php', '<?php class B {}');
        file_put_contents($this->tmpDir . '/C.php', '<?php class C {}');

        $result = $this->analyzer->analyze($this->tmpDir, new AnalyzerConfig());

        self::assertSame(3, $result['file_count']);
    }

    public function testProgressCallbackIsCalledForEachFile(): void
    {
        file_put_contents($this->tmpDir . '/A.php', '<?php class A {}');
        file_put_contents($this->tmpDir . '/B.php', '<?php class B {}');

        $calls = [];
        $this->analyzer->analyze(
            $this->tmpDir,
            new AnalyzerConfig(),
            function (string $file, int $current, int $total) use (&$calls): void {
                $calls[] = ['file' => $file, 'current' => $current, 'total' => $total];
            },
        );

        self::assertCount(2, $calls);
        self::assertSame(2, $calls[0]['total']);
        self::assertSame(1, $calls[0]['current']);
        self::assertSame(2, $calls[1]['current']);
    }

    public function testAnalyzeWithNoProgressCallbackDoesNotThrow(): void
    {
        file_put_contents($this->tmpDir . '/A.php', '<?php class A {}');

        $result = $this->analyzer->analyze($this->tmpDir, new AnalyzerConfig());

        self::assertSame(1, $result['file_count']);
    }

    public function testParseErrorsAreCollectedAsWarnings(): void
    {
        file_put_contents($this->tmpDir . '/bad.php', '<?php this is not valid ??? !!!');

        $result = $this->analyzer->analyze($this->tmpDir, new AnalyzerConfig());

        self::assertNotEmpty($result['warnings']);
    }

    public function testMultipleFilesWarningsAreMerged(): void
    {
        file_put_contents($this->tmpDir . '/ok.php', '<?php namespace App; class Ok {}');
        file_put_contents($this->tmpDir . '/bad.php', '<?php ??? invalid');

        $result = $this->analyzer->analyze($this->tmpDir, new AnalyzerConfig());

        self::assertNotEmpty($result['warnings']);
        self::assertTrue($result['graph']->hasNode('App\\Ok'));
    }
}
