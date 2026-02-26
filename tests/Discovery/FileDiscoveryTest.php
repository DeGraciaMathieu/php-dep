<?php

declare(strict_types=1);

namespace PhpDep\Tests\Discovery;

use PhpDep\Analyzer\AnalyzerConfig;
use PhpDep\Discovery\FileDiscovery;
use PHPUnit\Framework\TestCase;

final class FileDiscoveryTest extends TestCase
{
    private string $tmpDir;
    private FileDiscovery $discovery;

    protected function setUp(): void
    {
        $this->tmpDir    = sys_get_temp_dir() . '/php_dep_disc_' . uniqid();
        $this->discovery = new FileDiscovery();
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

    // ── Single file ───────────────────────────────────────────────────────────

    public function testDiscoversSinglePhpFilePassedDirectly(): void
    {
        $file = $this->tmpDir . '/Foo.php';
        file_put_contents($file, '<?php class Foo {}');

        $result = $this->discovery->discover($file, new AnalyzerConfig());

        self::assertCount(1, $result);
        self::assertSame(realpath($file), $result[0]);
    }

    public function testNonPhpFilePassedDirectlyReturnsEmpty(): void
    {
        $file = $this->tmpDir . '/readme.txt';
        file_put_contents($file, 'hello');

        $result = $this->discovery->discover($file, new AnalyzerConfig());

        self::assertSame([], $result);
    }

    public function testNonExistentPathReturnsEmpty(): void
    {
        $result = $this->discovery->discover('/does/not/exist/at/all', new AnalyzerConfig());

        self::assertSame([], $result);
    }

    // ── Directory discovery ───────────────────────────────────────────────────

    public function testDiscoversPhpFilesInDirectory(): void
    {
        file_put_contents($this->tmpDir . '/A.php', '<?php class A {}');
        file_put_contents($this->tmpDir . '/B.php', '<?php class B {}');

        $result = $this->discovery->discover($this->tmpDir, new AnalyzerConfig());

        self::assertCount(2, $result);
    }

    public function testIgnoresNonPhpFilesInDirectory(): void
    {
        file_put_contents($this->tmpDir . '/A.php', '<?php class A {}');
        file_put_contents($this->tmpDir . '/style.css', 'body {}');
        file_put_contents($this->tmpDir . '/readme.md', '# Readme');

        $result = $this->discovery->discover($this->tmpDir, new AnalyzerConfig());

        self::assertCount(1, $result);
        self::assertStringEndsWith('A.php', $result[0]);
    }

    public function testDiscoversPhpFilesRecursively(): void
    {
        mkdir($this->tmpDir . '/sub');
        file_put_contents($this->tmpDir . '/A.php', '<?php class A {}');
        file_put_contents($this->tmpDir . '/sub/B.php', '<?php class B {}');

        $result = $this->discovery->discover($this->tmpDir, new AnalyzerConfig());

        self::assertCount(2, $result);
    }

    public function testEmptyDirectoryReturnsEmpty(): void
    {
        $result = $this->discovery->discover($this->tmpDir, new AnalyzerConfig());

        self::assertSame([], $result);
    }

    // ── Exclusions ────────────────────────────────────────────────────────────

    public function testVendorDirIsExcludedByDefault(): void
    {
        file_put_contents($this->tmpDir . '/A.php', '<?php class A {}');
        mkdir($this->tmpDir . '/vendor');
        file_put_contents($this->tmpDir . '/vendor/Lib.php', '<?php class Lib {}');

        $result = $this->discovery->discover($this->tmpDir, new AnalyzerConfig());

        self::assertCount(1, $result);
        self::assertStringContainsString('A.php', $result[0]);
    }

    public function testCustomExcludeDirIsRespected(): void
    {
        file_put_contents($this->tmpDir . '/A.php', '<?php class A {}');
        mkdir($this->tmpDir . '/generated');
        file_put_contents($this->tmpDir . '/generated/B.php', '<?php class B {}');

        $config = new AnalyzerConfig(excludeDirs: ['generated']);
        $result = $this->discovery->discover($this->tmpDir, $config);

        self::assertCount(1, $result);
        self::assertStringContainsString('A.php', $result[0]);
    }

    public function testResultIsSorted(): void
    {
        file_put_contents($this->tmpDir . '/Z.php', '<?php class Z {}');
        file_put_contents($this->tmpDir . '/A.php', '<?php class A {}');
        file_put_contents($this->tmpDir . '/M.php', '<?php class M {}');

        $result = $this->discovery->discover($this->tmpDir, new AnalyzerConfig());

        $sorted = $result;
        sort($sorted);
        self::assertSame($sorted, $result);
    }
}
