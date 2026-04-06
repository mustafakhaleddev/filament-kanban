<?php

namespace Wezlo\FilamentKanban\ColumnSources;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Wezlo\FilamentKanban\Contracts\KanbanColumnSource;
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
}
