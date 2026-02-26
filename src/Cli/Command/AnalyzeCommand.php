<?php

declare(strict_types=1);

namespace PhpDep\Cli\Command;

use PhpDep\Analyzer\AnalyzerConfig;
use PhpDep\Analyzer\ProjectAnalyzer;
use PhpDep\Output\JsonFormatter;
use PhpDep\Output\TextFormatter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'analyze',
    description: 'Analyze PHP class dependencies in a project directory.',
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
            ->addOption('format',         'f', InputOption::VALUE_REQUIRED, 'Output format: text|json',      'text')
            ->addOption('depth',          'd', InputOption::VALUE_REQUIRED, 'Max dependency depth to show')
            ->addOption('class',          'c', InputOption::VALUE_REQUIRED, 'Focus on a specific class FQCN')
            ->addOption('exclude',        null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Directories to exclude')
            ->addOption('no-vendor',      null, InputOption::VALUE_NONE,    'Exclude vendor directory (default)')
            ->addOption('include-vendor', null, InputOption::VALUE_NONE,    'Include vendor directory in analysis')
            ->addOption('skip-docblocks', null, InputOption::VALUE_NONE,    'Skip @param/@return/@throws docblock analysis')
            ->addOption('limit',          'l', InputOption::VALUE_REQUIRED, 'Limit number of classes shown')
            ->addOption('sort',           's', InputOption::VALUE_REQUIRED, 'Sort order: alpha|deps|fanin',  'alpha')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Stderr for progress/warnings, stdout for results
        $stderr = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;

        // Validate format
        $format = $input->getOption('format');
        if (!in_array($format, ['text', 'json'], true)) {
            $stderr->writeln('<error>Invalid format. Use: text or json</error>');
            return Command::INVALID; // exit 3
        }

        // Validate sort
        $sort = $input->getOption('sort');
        if (!in_array($sort, ['alpha', 'deps', 'fanin'], true)) {
            $stderr->writeln('<error>Invalid sort. Use: alpha, deps, or fanin</error>');
            return Command::INVALID;
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

        // Build config
        $excludeVendor = !$input->getOption('include-vendor');
        $config        = new AnalyzerConfig(
            skipDocblocks:   (bool) $input->getOption('skip-docblocks'),
            excludeVendor:   $excludeVendor,
            vendorMode:      $excludeVendor ? 'boundary' : 'full',
            excludeDirs:     $input->getOption('exclude'),
        );

        // Progress bar (only in non-quiet, text mode, to stderr)
        $quiet       = $input->getOption('quiet');
        $progressBar = null;

        if (!$quiet && $format === 'text') {
            $progressBar = new ProgressBar($stderr);
            $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — <info>%message%</info>');
            $progressBar->setMessage('Starting…');
        }

        $analyzer = new ProjectAnalyzer();

        $progressCallback = null;
        if ($progressBar !== null) {
            $progressCallback = function (string $file, int $current, int $total) use ($progressBar): void {
                if ($current === 1) {
                    $progressBar->start($total);
                }
                $progressBar->setProgress($current);
                $progressBar->setMessage(basename($file));
            };
        }

        try {
            $result = $analyzer->analyze($resolvedPath, $config, $progressCallback);
        } catch (\Throwable $e) {
            if ($progressBar !== null) {
                $progressBar->finish();
                $stderr->writeln('');
            }
            $stderr->writeln(sprintf('<error>Analysis failed: %s</error>', $e->getMessage()));
            return Command::FAILURE;
        }

        if ($progressBar !== null) {
            $progressBar->finish();
            $stderr->writeln('');
        }

        $graph    = $result['graph'];
        $warnings = $result['warnings'];

        // Build options for formatter
        $formatterOptions = [
            'path'       => $resolvedPath,
            'file_count' => $result['file_count'],
            'class'      => $input->getOption('class'),
            'depth'      => $input->getOption('depth') !== null ? (int) $input->getOption('depth') : null,
            'limit'      => $input->getOption('limit') !== null ? (int) $input->getOption('limit') : null,
            'sort'       => $sort,
            'verbose'    => $output->isVerbose(),
        ];

        // Select formatter and write to stdout
        $formatter = match ($format) {
            'json'  => new JsonFormatter(),
            default => new TextFormatter(),
        };

        $formatter->format($graph, $warnings, $output, $formatterOptions);

        // Print warnings summary to stderr (not quiet mode)
        if (!$quiet && !empty($warnings) && $format !== 'json') {
            $stderr->writeln(sprintf(
                '<fg=yellow>%d warning(s) found. Run with -v to see details.</>',
                count($warnings),
            ));
        }

        // Return FAILURE if there were parse errors
        $hasParseErrors = array_filter(
            $warnings,
            fn($w) => $w->type->value === 'parse_error',
        );

        return !empty($hasParseErrors) ? Command::FAILURE : Command::SUCCESS;
    }
}
