<?php

declare(strict_types=1);

namespace PhpDep\Api;

use PhpDep\Analyzer\AnalyzerConfig;
use PhpDep\Analyzer\ProjectAnalyzer;
use PhpDep\Analyzer\WorkerPool;
use PhpDep\Parser\PhpFileParser;

/**
 * The entry point for a tool that uses php-dep as a library.
 * This class and Analysis are covered by the version of php-dep, like the JSON of the command line;
 * every other class is internal and may change in any release.
 */
final class PhpDep
{
    /**
     * @param string   $path    the directory or file to analyze
     * @param int|null $workers processes parsing the files in parallel, the CPU count when null
     * @throws \InvalidArgumentException when the path does not exist
     */
    public static function analyze(string $path, ?int $workers = null): Analysis
    {
        if (realpath($path) === false) {
            throw new \InvalidArgumentException(sprintf('Path not found: %s', $path));
        }

        $config = new AnalyzerConfig(workers: $workers ?? WorkerPool::cpuCount());
        $result = (new ProjectAnalyzer(new PhpFileParser()))->analyze($path, $config);

        return Analysis::of($result['graph'], $result['warnings']);
    }
}
