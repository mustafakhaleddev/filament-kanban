<?php

namespace Wezlo\FilamentKanban\Contracts;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Implement this interface on your BackedEnum to integrate with the Kanban board.
 *
 * Required: getLabel(), getColor() (from Filament contracts)
 * Optional: getIcon() (implement HasIcon), getAllowedTransitions(), getWipLimit()
 */
interface KanbanStatusEnum extends HasColor, HasLabel
{
    /**
     * Return the statuses this status is allowed to transition to.
     * Return null to allow all transitions (no constraints).
     *
     * @return static[]|null
     */
    public function getAllowedTransitions(): ?array;

    /**
     * Return the maximum number of records allowed in this column.
     * Return null for unlimited.
     */
    public function getWipLimit(): ?int;
}
