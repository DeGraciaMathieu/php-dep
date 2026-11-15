<?php

declare(strict_types=1);

namespace PhpDep\Cli;

/** The command line of php-dep, read without any console library */
final readonly class Arguments
{
    /**
     * @param string[] $excludeDirs
     */
    private function __construct(
        public string $path,
        public array  $excludeDirs,
        public bool   $includeVendor,
        public ?int   $workers,
        public bool   $help,
        public bool   $version,
    ) {}

    /**
     * @param string[] $args the arguments after the script name
     * @throws \InvalidArgumentException on an unknown option or an invalid value
     */
    public static function parse(array $args): self
    {
        // "analyze" is the only command, and stays optional
        if (($args[0] ?? null) === 'analyze') {
            array_shift($args);
        }

        $paths         = [];
        $excludeDirs   = [];
        $includeVendor = false;
        $workers       = null;
        $help          = false;
        $version       = false;
        $optionsEnded  = false;

        while ($args !== []) {
            $arg = array_shift($args);

            if ($optionsEnded || !str_starts_with($arg, '-') || $arg === '-') {
                $paths[] = $arg;
                continue;
            }

            [$name, $value] = str_contains($arg, '=') ? explode('=', $arg, 2) : [$arg, null];

            // -w4 is -w 4
            if (preg_match('/^-w(\d+)$/', $name, $matches) === 1) {
                [$name, $value] = ['-w', $matches[1]];
            }

            match ($name) {
                '--'                => $optionsEnded = true,
                '-h', '--help'      => $help = true,
                '-V', '--version'   => $version = true,
                '--include-vendor'  => $includeVendor = true,
                '--no-vendor'       => $includeVendor = false,
                '--exclude'         => $excludeDirs[] = $value ?? self::valueOf($name, $args),
                '-w', '--workers'   => $workers = self::workers($value ?? self::valueOf($name, $args)),
                default             => throw new \InvalidArgumentException(sprintf('Unknown option: %s', $name)),
            };
        }

        if (count($paths) > 1) {
            throw new \InvalidArgumentException('Only one path can be analyzed at a time');
        }

        return new self($paths[0] ?? '.', $excludeDirs, $includeVendor, $workers, $help, $version);
    }

    /** @param string[] $args */
    private static function valueOf(string $name, array &$args): string
    {
        if ($args === []) {
            throw new \InvalidArgumentException(sprintf('The %s option needs a value', $name));
        }

        return array_shift($args);
    }

    private static function workers(string $value): int
    {
        $workers = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($workers === false) {
            throw new \InvalidArgumentException('Invalid workers. Use a number of at least 1');
        }

        return $workers;
    }
}
