<?php

declare(strict_types=1);

namespace PhpDep\Cli;

use PhpDep\Analyzer\AnalyzerConfig;
use PhpDep\Analyzer\ProjectAnalyzer;
use PhpDep\Analyzer\WorkerPool;
use PhpDep\Output\JsonFormatter;
use PhpDep\Parser\PhpFileParser;
use PhpDep\Warning\WarningType;

final class Application
{
    private const VERSION = '3.1.0';

    public const SUCCESS = 0;
    public const FAILURE = 1;
    public const INVALID = 2;
    /** The graph is written, but some files could not be parsed: see the parse_error warnings */
    public const PARTIAL = 3;

    private const HELP = <<<'TXT'
        Extract the class dependency graph of a PHP project as JSON.

        Usage:
          php-dep [analyze] [<path>] [options]

        Arguments:
          path                  Path to the PHP project or file to analyze [default: "."]

        Options:
              --exclude=DIR     Directory to exclude, can be repeated
              --include-vendor  Include the vendor directory in the analysis
          -w, --workers=N       Number of parallel processes, the CPU count by default
          -h, --help            Display this help
          -V, --version         Display the version

        TXT;

    /**
     * @param string[] $args   the arguments after the script name
     * @param resource $stdout receives the JSON
     * @param resource $stderr receives the errors
     */
    public function run(array $args, $stdout, $stderr): int
    {
        try {
            $arguments = Arguments::parse($args);
        } catch (\InvalidArgumentException $e) {
            fwrite($stderr, $e->getMessage() . "\n");
            return self::INVALID;
        }

        if ($arguments->help) {
            fwrite($stdout, self::HELP);
            return self::SUCCESS;
        }

        if ($arguments->version) {
            fwrite($stdout, sprintf("php-dep %s\n", self::VERSION));
            return self::SUCCESS;
        }

        $resolvedPath = realpath($arguments->path);
        if ($resolvedPath === false) {
            fwrite($stderr, sprintf("Path not found: %s\n", $arguments->path));
            return self::INVALID;
        }

        $config = new AnalyzerConfig(
            excludeVendor: !$arguments->includeVendor,
            excludeDirs:   $arguments->excludeDirs,
            workers:       $arguments->workers ?? WorkerPool::cpuCount(),
        );

        try {
            $result = (new ProjectAnalyzer(new PhpFileParser()))->analyze($resolvedPath, $config);
        } catch (\Throwable $e) {
            fwrite($stderr, sprintf("Analysis failed: %s\n", $e->getMessage()));
            return self::FAILURE;
        }

        $warnings = $result['warnings'];

        fwrite($stdout, (new JsonFormatter())->format($result['graph'], $warnings, [
            'path'       => $resolvedPath,
            'file_count' => $result['file_count'],
        ]));

        $hasParseErrors = array_filter(
            $warnings,
            fn($w) => $w->type === WarningType::PARSE_ERROR,
        );

        return $hasParseErrors !== [] ? self::PARTIAL : self::SUCCESS;
    }
}
