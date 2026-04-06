<?php

namespace Wezlo\FilamentKanban\Contracts;

use Wezlo\FilamentKanban\KanbanColumn;

interface KanbanColumnSource
{
    /**
     * @return array<int, KanbanColumn>
     */
    public function resolveColumns(): array;

    /**
     * The model attribute (column name or FK) that maps records to columns.
     */
    public function getColumnAttribute(): string;
}
