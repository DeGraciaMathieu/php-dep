<?php

declare(strict_types=1);

namespace PhpDep\Cli;

use PhpDep\Cli\Command\AnalyzeCommand;
use Symfony\Component\Console\Application as ConsoleApplication;

final class Application extends ConsoleApplication
{
    private const NAME = 'php-dep';
    private const VERSION = '1.0.0';

    public function __construct()
    {
        parent::__construct(self::NAME, self::VERSION);
        $this->add(new AnalyzeCommand());
        $this->setDefaultCommand('analyze');
    }
}
