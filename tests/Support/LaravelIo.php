<?php

declare(strict_types=1);

namespace PhpDep\Tests\Support;

/**
 * The source of laravel.io (github.com/laravelio/laravel.io), a real Laravel application nobody wrote for these tests.
 * It is cloned once into .cache and checked out at fixed commits, so the expected figures never move.
 */
final class LaravelIo
{
    private const REPOSITORY = 'https://github.com/laravelio/laravel.io.git';

    public static function at(string $commit): string
    {
        $clone    = self::clone();
        $checkout = dirname($clone) . "/laravel.io-{$commit}";

        if (!is_dir($checkout)) {
            self::git(['-C', $clone, 'worktree', 'add', '--quiet', '--detach', $checkout, $commit]);
        }

        return $checkout;
    }

    private static function clone(): string
    {
        $clone = dirname(__DIR__, 2) . '/.cache/laravel.io';

        if (!is_dir($clone)) {
            self::git(['clone', '--quiet', '--filter=blob:none', self::REPOSITORY, $clone]);
        }

        return $clone;
    }

    /** @param string[] $arguments */
    private static function git(array $arguments): void
    {
        $process = proc_open(['git', ...$arguments], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $errors  = stream_get_contents($pipes[2]);

        if (proc_close($process) !== 0) {
            throw new \RuntimeException('git ' . implode(' ', $arguments) . " failed:\n{$errors}");
        }
    }
}
