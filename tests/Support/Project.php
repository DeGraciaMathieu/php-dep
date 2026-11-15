<?php

declare(strict_types=1);

namespace PhpDep\Tests\Support;

/**
 * A throwaway project written to a temporary directory, removed when the object goes away.
 */
final class Project
{
    private function __construct(
        public readonly string $root,
    ) {}

    /**
     * @param array<string, string> $files path relative to the root => content
     */
    public static function with(array $files): self
    {
        $root = sys_get_temp_dir() . '/php_dep_project_' . uniqid();

        foreach ($files as $path => $content) {
            $file = $root . '/' . $path;
            if (!is_dir(dirname($file))) {
                mkdir(dirname($file), 0777, true);
            }
            file_put_contents($file, $content);
        }

        return new self($root);
    }

    public function __destruct()
    {
        $this->remove($this->root);
    }

    private function remove(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->remove($path) : unlink($path);
        }

        rmdir($dir);
    }
}
