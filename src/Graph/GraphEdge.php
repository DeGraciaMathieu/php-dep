<?php

declare(strict_types=1);

namespace PhpDep\Graph;

final readonly class GraphEdge
{
    public function __construct(
        public string   $source,
        public string   $target,
        public EdgeType $type,
        public ?string  $file = null,
        public ?int     $line = null,
    ) {}

    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'target' => $this->target,
            'type'   => $this->type->value,
            'file'   => $this->file,
            'line'   => $this->line,
        ];
    }
}
