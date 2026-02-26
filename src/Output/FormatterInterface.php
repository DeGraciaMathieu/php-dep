<?php

declare(strict_types=1);

namespace PhpDep\Output;

use PhpDep\Graph\DependencyGraph;
use PhpDep\Warning\AnalysisWarning;
use Symfony\Component\Console\Output\OutputInterface;

interface FormatterInterface
{
    /**
     * Format and write the analysis results to the output.
     *
     * @param AnalysisWarning[] $warnings
     */
    public function format(
        DependencyGraph $graph,
        array           $warnings,
        OutputInterface $output,
        array           $options = [],
    ): void;
}
