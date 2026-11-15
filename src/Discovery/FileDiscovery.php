<?php

declare(strict_types=1);

namespace PhpDep\Discovery;

final class FileDiscovery
{
    /** Directories always excluded from discovery */
    private const DEFAULT_EXCLUDE_DIRS = [
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
     * Uses git ls-files if the path is inside a git repository, so that ignored files are left out,
     * and RecursiveDirectoryIterator otherwise. The exclusions apply the same way to both,
     * on the paths relative to the analyzed directory.
     *
     * @param string[] $excludeDirs directories to exclude, in addition to the defaults
     * @return string[] absolute file paths, sorted
     */
    public function discover(string $path, array $excludeDirs = [], bool $excludeVendor = true): array
    {
        $path = realpath($path);

        if ($path === false) {
            return [];
        }

        if (is_file($path)) {
            return str_ends_with($path, '.php') ? [$path] : [];
        }

        $excluded      = array_merge(self::DEFAULT_EXCLUDE_DIRS, $excludeVendor ? ['vendor'] : [], $excludeDirs);
        $relativePaths = $this->gitFiles($path) ?? $this->filesystemFiles($path, $excluded);

        $files = [];
        foreach ($relativePaths as $relativePath) {
            if (!$this->isExcluded($relativePath, $excluded)) {
                $files[] = $path . DIRECTORY_SEPARATOR . $relativePath;
            }
        }

        sort($files);

        return $files;
    }

    /**
     * PHP files of the directory known to git, tracked or untracked but not ignored.
     * Returns null outside a git repository, or when git knows none of them, for instance in an ignored directory.
     *
     * @return string[]|null paths relative to $path
     */
    private function gitFiles(string $path): ?array
    {
        $cmd      = sprintf('git -C %s ls-files --cached --others --exclude-standard -- "*.php" 2>/dev/null', escapeshellarg($path));
        $output   = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);

        // A tracked file deleted from the working tree is still listed
        $files = array_filter($output, fn(string $relativePath) => is_file($path . DIRECTORY_SEPARATOR . $relativePath));

        return $exitCode !== 0 || $files === [] ? null : array_values($files);
    }

    /**
     * @param string[] $excluded
     * @return string[] paths relative to $path
     */
    private function filesystemFiles(string $path, array $excluded): array
    {
        $prefixLength = strlen($path) + 1;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
                // Excluded directories are not walked through at all
                fn(\SplFileInfo $file, string $key, \RecursiveDirectoryIterator $iterator) => !$iterator->hasChildren()
                    || !$this->isExcluded(substr($file->getPathname(), $prefixLength) . DIRECTORY_SEPARATOR, $excluded),
            ),
        );

        $files = [];
        foreach ($iterator as $fileInfo) {
            /** @var \SplFileInfo $fileInfo */
            if ($fileInfo->getExtension() === 'php') {
                $files[] = substr($fileInfo->getPathname(), $prefixLength);
            }
        }

        return $files;
    }

    /**
     * Whether a path relative to the analyzed directory lies in one of the excluded directories, at any depth.
     *
     * @param string[] $excluded
     */
    private function isExcluded(string $relativePath, array $excluded): bool
    {
        $relativePath = DIRECTORY_SEPARATOR . $relativePath;

        foreach ($excluded as $dir) {
            if (str_contains($relativePath, DIRECTORY_SEPARATOR . trim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
                return true;
            }
        }

        return false;
    }
}
