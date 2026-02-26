<?php

declare(strict_types=1);

namespace PhpDep\Warning;

final readonly class AnalysisWarning
{
    public function __construct(
        public WarningType $type,
        public string      $file,
        public ?int        $line,
        public string      $message,
    ) {}

    public function toArray(): array
    {
        return [
            'type'    => $this->type->value,
            'file'    => $this->file,
            'line'    => $this->line,
            'message' => $this->message,
        ];
    }
}
