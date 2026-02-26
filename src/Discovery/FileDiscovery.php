<?php

declare(strict_types=1);

namespace PhpDep\Discovery;

use PhpDep\Analyzer\AnalyzerConfig;

final class FileDiscovery
{
    /** Directories always excluded from discovery */
    private const DEFAULT_EXCLUDE_DIRS = [
        'vendor',
        'node_modules',
        '.git',
        '.svn',
        '.hg',
        'storage',
        'bootstrap/cache',
        'public/build',
        '.idea',
        '.vscode',
    ];

    /**
     * Discover PHP files in the given path.
     *
     * Uses git ls-files if the path is inside a git repo, falls back to
     * RecursiveDirectoryIterator otherwise.
     *
     * @return string[] absolute file paths
     */
    public function discover(string $path, AnalyzerConfig $config): array
    {
        $path = realpath($path) ?: $path;

        $files = $this->tryGitDiscovery($path)
              ?? $this->filesystemDiscovery($path, $config);

        // Apply exclude filters
        $excludeDirs = array_merge(
            $config->excludeVendor ? ['vendor'] : [],
            $config->excludeDirs,
        );

        $files = array_filter($files, function (string $file) use ($excludeDirs): bool {
            foreach ($excludeDirs as $dir) {
                if (str_contains($file, DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR)
                    || str_ends_with($file, DIRECTORY_SEPARATOR . $dir)) {
                    return false;
                }
            }
            return true;
        });

        return array_values($files);
    }

    /**
     * Try to discover files via `git ls-files`.
     * Returns null if path is not a git repository.
     *
     * @return string[]|null
     */
    private function tryGitDiscovery(string $path): ?array
    {
        // Check if git is available and path is a git repo
        $gitDir = $path . '/.git';
        if (!is_dir($gitDir)) {
            // Try to find .git in parent dirs (for subdirectory analysis)
            $check = $path;
            $found = false;
            for ($i = 0; $i < 5; $i++) {
                $parent = dirname($check);
                if ($parent === $check) {
                    break;
                }
                if (is_dir($parent . '/.git')) {
                    $found = true;
                    break;
                }
                $check = $parent;
            }
            if (!$found) {
                return null;
            }
        }

        // Run git ls-files to get tracked PHP files
        $cmd    = sprintf('git -C %s ls-files --cached --others --exclude-standard "*.php" 2>/dev/null', escapeshellarg($path));
        $output = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0 || empty($output)) {
            return null;
        }

        $files = [];
        foreach ($output as $relativePath) {
            $absolute = $path . DIRECTORY_SEPARATOR . $relativePath;
            if (is_file($absolute)) {
                $files[] = $absolute;
            }
        }

        return empty($files) ? null : $files;
    }

    /**
     * Discover PHP files using RecursiveDirectoryIterator.
     *
     * @return string[]
     */
    private function filesystemDiscovery(string $path, AnalyzerConfig $config): array
    {
        if (!is_dir($path)) {
            if (is_file($path) && str_ends_with($path, '.php')) {
                return [$path];
            }
            return [];
        }

        $excludeDirs = array_merge(self::DEFAULT_EXCLUDE_DIRS, $config->excludeDirs);
        $files       = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
                function (\SplFileInfo $file, string $key, \RecursiveDirectoryIterator $iterator) use ($excludeDirs): bool {
                    if ($iterator->hasChildren()) {
                        $dirName = $file->getFilename();
                        foreach ($excludeDirs as $excluded) {
                            if ($dirName === $excluded) {
                                return false;
                            }
                        }
                    }
                    return true;
                }
            ),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $fileInfo) {
            /** @var \SplFileInfo $fileInfo */
            if ($fileInfo->isFile() && $fileInfo->getExtension() === 'php') {
                $files[] = $fileInfo->getRealPath();
            }
        }

        sort($files);

        return $files;
    }
}
