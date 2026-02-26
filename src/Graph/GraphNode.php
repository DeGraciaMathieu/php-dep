<?php

declare(strict_types=1);

namespace PhpDep\Graph;

final readonly class GraphNode
{
    public function __construct(
        public string   $fqcn,
        public NodeType $type,
        public ?string  $file = null,
        public ?int     $line = null,
    ) {}

    public function isExternal(): bool
    {
        return $this->type === NodeType::EXTERNAL;
    }

    public function toArray(): array
    {
        return [
            'fqcn' => $this->fqcn,
            'type' => $this->type->value,
            'file' => $this->file,
            'line' => $this->line,
        ];
    }
}
