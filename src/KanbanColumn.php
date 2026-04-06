<?php

namespace Wezlo\FilamentKanban;

use Illuminate\Support\Collection;

class KanbanColumn
{
    public Collection $records;

    public int $count = 0;

    public function __construct(
        public string $value,
        public string $label,
        public string $color = 'gray',
        public mixed $icon = null,
    ) {
        $this->records = collect();
    }
}
