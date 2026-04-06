<?php

namespace Wezlo\FilamentKanban\ColumnSources;

use Wezlo\FilamentKanban\Contracts\KanbanColumnSource;
use Wezlo\FilamentKanban\KanbanColumn;

class RelationshipColumnSource implements KanbanColumnSource
{
    public function __construct(
        protected string $relationship,
        protected string $titleAttribute,
        protected string $relatedModel,
        protected ?string $orderAttribute = null,
        protected ?string $colorAttribute = null,
        protected string $foreignKey = '',
    ) {}

    public function resolveColumns(): array
    {
        $query = $this->relatedModel::query();

        if ($this->orderAttribute) {
            $query->orderBy($this->orderAttribute);
        }

        return $query->get()->map(function ($model) {
            return new KanbanColumn(
                value: (string) $model->getKey(),
                label: $model->{$this->titleAttribute},
                color: $this->colorAttribute ? ($model->{$this->colorAttribute} ?? 'gray') : 'gray',
            );
        })->all();
    }

    public function getColumnAttribute(): string
    {
        return $this->foreignKey ?: ($this->relationship.'_id');
    }
}
