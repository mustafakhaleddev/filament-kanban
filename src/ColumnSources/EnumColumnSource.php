<?php

namespace Wezlo\FilamentKanban\ColumnSources;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Wezlo\FilamentKanban\Contracts\KanbanColumnSource;
use Wezlo\FilamentKanban\Contracts\KanbanStatusEnum;
use Wezlo\FilamentKanban\KanbanColumn;

class EnumColumnSource implements KanbanColumnSource
{
    /**
     * @param  class-string<\BackedEnum>  $enumClass
     */
    public function __construct(
        protected string $attribute,
        protected string $enumClass,
    ) {}

    public function resolveColumns(): array
    {
        return collect($this->enumClass::cases())->map(function (\BackedEnum $case) {
            $icon = $case instanceof HasIcon ? $case->getIcon() : null;

            if (is_object($icon) && method_exists($icon, 'value')) {
                $icon = $icon->value;
            }

            return new KanbanColumn(
                value: (string) $case->value,
                label: $case instanceof HasLabel ? $case->getLabel() : $case->name,
                color: $case instanceof HasColor ? $case->getColor() : 'gray',
                icon: $icon,
            );
        })->all();
    }

    public function getColumnAttribute(): string
    {
        return $this->attribute;
    }

    public function implementsKanbanStatus(): bool
    {
        return is_subclass_of($this->enumClass, KanbanStatusEnum::class);
    }

    /**
     * Resolve drag constraints from the enum's getAllowedTransitions().
     *
     * @return array<string, string[]>
     */
    public function resolveDragConstraints(): array
    {
        if (! $this->implementsKanbanStatus()) {
            return [];
        }

        $constraints = [];

        foreach ($this->enumClass::cases() as $case) {
            $allowed = $case->getAllowedTransitions();

            if ($allowed === null) {
                continue;
            }

            $constraints[(string) $case->value] = array_map(
                fn (\BackedEnum $t) => (string) $t->value,
                $allowed,
            );
        }

        return $constraints;
    }

    /**
     * Resolve WIP limits from the enum's getWipLimit().
     *
     * @return array<string, int>
     */
    public function resolveWipLimits(): array
    {
        if (! $this->implementsKanbanStatus()) {
            return [];
        }

        $limits = [];

        foreach ($this->enumClass::cases() as $case) {
            $limit = $case->getWipLimit();

            if ($limit !== null) {
                $limits[(string) $case->value] = $limit;
            }
        }

        return $limits;
    }
}
