<?php

declare(strict_types=1);

namespace PhpDep\Analyzer;

final class WorkerPool
{
    public function __construct(
        private readonly int $workers,
    ) {}

    /** Number of logical CPUs, 1 when it cannot be told */
    public static function cpuCount(): int
    {
        $count = (int) @shell_exec('nproc 2>/dev/null || sysctl -n hw.ncpu 2>/dev/null');

        return max(1, $count);
    }

    /**
     * Apply $task to every item, spread over forked processes when pcntl is available.
     * Results keep the order of the items, so the output does not depend on the number of workers.
     *
     * @template T
     * @param string[] $items
     * @param callable(string): T $task
     * @return T[]
     */
    public function map(array $items, callable $task): array
    {
        $items   = array_values($items);
        $workers = min($this->workers, count($items));

        if ($workers <= 1 || !function_exists('pcntl_fork')) {
            return array_map($task, $items);
        }

        // Round-robin, so that big files sorted together end up on different workers
        $chunks = [];
        foreach ($items as $index => $item) {
            $chunks[$index % $workers][$index] = $item;
        }

        $children = [];
        foreach ($chunks as $chunk) {
            $resultFile = tempnam(sys_get_temp_dir(), 'php-dep-');
            $pid        = pcntl_fork();

            if ($pid === -1) {
                throw new \RuntimeException('Unable to fork a worker');
            }

            if ($pid === 0) {
                $results = [];
                foreach ($chunk as $index => $item) {
                    $results[$index] = $task($item);
                }
                file_put_contents($resultFile, serialize($results));

                // Leave without the shutdown functions and destructors inherited from the parent
                posix_kill(getmypid(), SIGKILL);
            }

            $children[$pid] = $resultFile;
        }

        $results = [];
        foreach ($children as $pid => $resultFile) {
            pcntl_waitpid($pid, $status);

            $content = file_get_contents($resultFile);
            unlink($resultFile);

            if ($content === false || $content === '') {
                throw new \RuntimeException('A worker stopped without its results');
            }

            $results += unserialize($content);
        }

        ksort($results);

        return $results;
    }
}
