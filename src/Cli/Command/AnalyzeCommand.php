<?php

declare(strict_types=1);

namespace PhpDep\Cli\Command;

use PhpDep\Analyzer\AnalyzerConfig;
use PhpDep\Analyzer\ProjectAnalyzer;
use PhpDep\Analyzer\WorkerPool;
use PhpDep\Output\JsonFormatter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'analyze',
    description: 'Extract the class dependency graph of a PHP project as JSON.',
)]
final class AnalyzeCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument(
                'path',
                InputArgument::OPTIONAL,
                'Path to the PHP project or file to analyze',
                '.',
            )
            ->addOption('exclude',        null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Directories to exclude')
            ->addOption('no-vendor',      null, InputOption::VALUE_NONE,    'Exclude vendor directory (default)')
            ->addOption('include-vendor', null, InputOption::VALUE_NONE,    'Include vendor directory in analysis')
            ->addOption('workers',        'w',  InputOption::VALUE_REQUIRED, 'Number of parallel processes, the CPU count by default')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Stderr for errors, stdout for the JSON
        $stderr = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;

        $workers = $input->getOption('workers') ?? WorkerPool::cpuCount();
        if (filter_var($workers, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            $stderr->writeln('<error>Invalid workers. Use a number of at least 1</error>');
            return Command::INVALID; // exit 2
        }

        // Resolve path
        $path = $input->getArgument('path');
        if (!is_string($path)) {
            $path = '.';
        }
        $resolvedPath = realpath($path);
        if ($resolvedPath === false) {
            $stderr->writeln(sprintf('<error>Path not found: %s</error>', $path));
            return Command::INVALID;
        }

        $excludeVendor = !$input->getOption('include-vendor');
        $config        = new AnalyzerConfig(
            excludeVendor: $excludeVendor,
            excludeDirs:   $input->getOption('exclude'),
            workers:       (int) $workers,
        );

        try {
            $result = (new ProjectAnalyzer())->analyze($resolvedPath, $config);
        } catch (\Throwable $e) {
            $stderr->writeln(sprintf('<error>Analysis failed: %s</error>', $e->getMessage()));
            return Command::FAILURE;
        }

        $warnings = $result['warnings'];

        (new JsonFormatter())->format($result['graph'], $warnings, $output, [
            'path'       => $resolvedPath,
            'file_count' => $result['file_count'],
        ]);

        // Return FAILURE if there were parse errors
        $hasParseErrors = array_filter(
            $warnings,
            fn($w) => $w->type->value === 'parse_error',
        );

        return !empty($hasParseErrors) ? Command::FAILURE : Command::SUCCESS;
    }
}
