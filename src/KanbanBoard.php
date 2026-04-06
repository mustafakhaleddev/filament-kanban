<?php

namespace Wezlo\FilamentKanban;

use Closure;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;
use Wezlo\FilamentKanban\ColumnSources\EnumColumnSource;
use Wezlo\FilamentKanban\ColumnSources\RelationshipColumnSource;
use Wezlo\FilamentKanban\Contracts\KanbanColumnSource;
use Wezlo\FilamentKanban\Contracts\KanbanStatusEnum;

class KanbanBoard
{
    protected ?KanbanColumnSource $columnSource = null;

    protected ?Closure $cardTitle = null;

    protected ?Closure $cardDescription = null;

    protected ?Closure $cardBadges = null;

    protected ?string $cardView = null;

    protected ?string $columnView = null;

    protected ?string $boardView = null;

    protected ?string $orderColumn = null;

    protected ?Closure $queryModifier = null;

    protected ?Closure $onRecordMoved = null;

    protected ?int $recordsPerColumn = null;

    protected array $excludedColumns = [];

    protected string $columnWidth = '280px';

    protected bool $searchable = false;

    protected array $searchableColumns = [];

    protected ?Closure $recordUrlResolver = null;

    protected ?Closure $columnColorResolver = null;

    protected ?Action $cardClickAction = null;

    protected array $filters = [];

    protected int $filtersColumns = 4;

    // --- New feature properties ---

    protected ?string $emptyStateHeading = null;

    protected ?string $emptyStateDescription = null;

    protected ?string $emptyStateIcon = null;

    protected ?Action $columnHeaderAction = null;

    protected array $cardFooterActions = [];

    protected bool $collapsible = false;

    protected array $wipLimits = [];

    protected ?int $defaultWipLimit = null;

    protected ?Closure $columnSummary = null;

    protected bool $loading = true;

    protected array $dragConstraints = [];

    protected ?Closure $canMove = null;

    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * @param  class-string<\BackedEnum&KanbanStatusEnum>|class-string<\BackedEnum>  $enumClass
     */
    public function enumColumn(string $attribute, string $enumClass): static
    {
        $this->columnSource = new EnumColumnSource($attribute, $enumClass);

        return $this;
    }

    public function relationshipColumn(
        string $relationship,
        string $titleAttribute,
        string $relatedModel,
        ?string $orderAttribute = null,
        ?string $colorAttribute = null,
        string $foreignKey = '',
    ): static {
        $this->columnSource = new RelationshipColumnSource(
            $relationship,
            $titleAttribute,
            $relatedModel,
            $orderAttribute,
            $colorAttribute,
            $foreignKey,
        );

        return $this;
    }

    public function cardTitle(Closure $callback): static
    {
        $this->cardTitle = $callback;

        return $this;
    }

    public function cardDescription(Closure $callback): static
    {
        $this->cardDescription = $callback;

        return $this;
    }

    public function cardBadges(Closure $callback): static
    {
        $this->cardBadges = $callback;

        return $this;
    }

    public function cardView(string $view): static
    {
        $this->cardView = $view;

        return $this;
    }

    public function columnView(string $view): static
    {
        $this->columnView = $view;

        return $this;
    }

    public function boardView(string $view): static
    {
        $this->boardView = $view;

        return $this;
    }

    public function orderColumn(string $column): static
    {
        $this->orderColumn = $column;

        return $this;
    }

    public function modifyQueryUsing(Closure $callback): static
    {
        $this->queryModifier = $callback;

        return $this;
    }

    public function onRecordMoved(Closure $callback): static
    {
        $this->onRecordMoved = $callback;

        return $this;
    }

    public function recordsPerColumn(int $limit): static
    {
        $this->recordsPerColumn = $limit;

        return $this;
    }

    public function excludeColumns(array $values): static
    {
        $this->excludedColumns = array_map(function ($value) {
            return $value instanceof \BackedEnum ? (string) $value->value : (string) $value;
        }, $values);

        return $this;
    }

    public function columnWidth(string $width): static
    {
        $this->columnWidth = $width;

        return $this;
    }

    public function searchable(array $columns = []): static
    {
        $this->searchable = true;
        $this->searchableColumns = $columns;

        return $this;
    }

    public function recordUrl(Closure $callback): static
    {
        $this->recordUrlResolver = $callback;

        return $this;
    }

    public function columnColor(Closure $callback): static
    {
        $this->columnColorResolver = $callback;

        return $this;
    }

    public function cardAction(Action $action): static
    {
        $this->cardClickAction = $action;

        return $this;
    }

    public function filters(array $filters): static
    {
        $this->filters = $filters;

        return $this;
    }

    public function filtersColumns(int $columns): static
    {
        $this->filtersColumns = $columns;

        return $this;
    }

    // --- Empty state ---

    public function emptyState(?string $heading = 'No records', ?string $description = null, ?string $icon = 'heroicon-o-rectangle-stack'): static
    {
        $this->emptyStateHeading = $heading;
        $this->emptyStateDescription = $description;
        $this->emptyStateIcon = $icon;

        return $this;
    }

    // --- Column header action ---

    public function columnHeaderAction(Action $action): static
    {
        $this->columnHeaderAction = $action;

        return $this;
    }

    // --- Card footer actions ---

    public function cardFooterActions(array $actions): static
    {
        $this->cardFooterActions = $actions;

        return $this;
    }

    // --- Collapsible columns ---

    public function collapsible(bool $collapsible = true): static
    {
        $this->collapsible = $collapsible;

        return $this;
    }

    // --- WIP limits ---

    /**
     * Set WIP limits per column value, or a default for all.
     * e.g. wipLimits(['new' => 10, 'contacted' => 5]) or defaultWipLimit(10)
     */
    public function wipLimits(array $limits): static
    {
        $this->wipLimits = array_map(fn ($v) => (int) $v, $limits);

        return $this;
    }

    public function defaultWipLimit(int $limit): static
    {
        $this->defaultWipLimit = $limit;

        return $this;
    }

    // --- Column summary ---

    /**
     * Closure receives ($records, $column) and returns a string to display in the column header.
     */
    public function columnSummary(Closure $callback): static
    {
        $this->columnSummary = $callback;

        return $this;
    }

    // --- Loading indicator ---

    public function loading(bool $loading = true): static
    {
        $this->loading = $loading;

        return $this;
    }

    // --- Drag constraints ---

    /**
     * Map of column value => array of allowed target column values.
     * e.g. ['new' => ['contacted'], 'contacted' => ['site_visit', 'lost']]
     */
    public function dragConstraints(array $constraints): static
    {
        $this->dragConstraints = collect($constraints)->mapWithKeys(function ($targets, $source) {
            $sourceKey = $source instanceof \BackedEnum ? (string) $source->value : (string) $source;
            $targetValues = array_map(fn ($t) => $t instanceof \BackedEnum ? (string) $t->value : (string) $t, $targets);

            return [$sourceKey => $targetValues];
        })->all();

        return $this;
    }

    /**
     * Closure receives ($record, $oldStatus, $newStatus) and returns bool.
     * Return false to prevent the move.
     */
    public function canMove(Closure $callback): static
    {
        $this->canMove = $callback;

        return $this;
    }

    // --- Resolvers ---

    public function resolveCardTitle($record): string
    {
        if ($this->cardTitle) {
            return (string) ($this->cardTitle)($record);
        }

        return (string) $record->getKey();
    }

    public function resolveCardDescription($record): ?string
    {
        if ($this->cardDescription) {
            return ($this->cardDescription)($record);
        }

        return null;
    }

    /**
     * @return array<int, array{label: string, color: string}>|null
     */
    public function resolveCardBadges($record): ?array
    {
        if ($this->cardBadges) {
            return ($this->cardBadges)($record);
        }

        return null;
    }

    public function getRecordUrl($record): ?string
    {
        if ($this->recordUrlResolver) {
            return ($this->recordUrlResolver)($record);
        }

        return null;
    }

    public function resolveColumnColor(KanbanColumn $column): string
    {
        if ($this->columnColorResolver) {
            return ($this->columnColorResolver)($column);
        }

        return $column->color;
    }

    public function resolveColumnSummary(KanbanColumn $column): ?string
    {
        if ($this->columnSummary) {
            return ($this->columnSummary)($column->records, $column);
        }

        return null;
    }

    /**
     * Get WIP limit for a column, checking explicit limits, enum limits, then default.
     */
    public function getWipLimit(string $columnValue): ?int
    {
        // Explicit per-column limit takes priority
        if (isset($this->wipLimits[$columnValue])) {
            return $this->wipLimits[$columnValue];
        }

        // Check enum-defined limit
        if ($this->columnSource instanceof EnumColumnSource && $this->columnSource->implementsKanbanStatus()) {
            $enumLimits = $this->columnSource->resolveWipLimits();
            if (isset($enumLimits[$columnValue])) {
                return $enumLimits[$columnValue];
            }
        }

        return $this->defaultWipLimit;
    }

    public function isOverWipLimit(KanbanColumn $column): bool
    {
        $limit = $this->getWipLimit($column->value);

        return $limit !== null && $column->count > $limit;
    }

    // --- Getters ---

    public function getColumnSource(): KanbanColumnSource
    {
        return $this->columnSource;
    }

    public function getCardView(): string
    {
        return $this->cardView ?? config('filament-kanban.card_view', 'filament-kanban::components.card');
    }

    public function getColumnView(): string
    {
        return $this->columnView ?? 'filament-kanban::components.column';
    }

    public function getBoardView(): string
    {
        return $this->boardView ?? 'filament-kanban::components.board';
    }

    public function getOrderColumn(): ?string
    {
        return $this->orderColumn ?? config('filament-kanban.default_order_column');
    }

    public function getQueryModifier(): ?Closure
    {
        return $this->queryModifier;
    }

    public function getOnRecordMoved(): ?Closure
    {
        return $this->onRecordMoved;
    }

    public function getRecordsPerColumn(): ?int
    {
        return $this->recordsPerColumn ?? config('filament-kanban.records_per_column');
    }

    public function getExcludedColumns(): array
    {
        return $this->excludedColumns;
    }

    public function getColumnWidth(): string
    {
        return $this->columnWidth ?? config('filament-kanban.column_width', '280px');
    }

    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    public function getSearchableColumns(): array
    {
        return $this->searchableColumns;
    }

    public function getCardClickAction(): ?Action
    {
        return $this->cardClickAction;
    }

    public function hasCardClickAction(): bool
    {
        return $this->cardClickAction !== null;
    }

    public function getFilters(): array
    {
        return $this->filters;
    }

    public function hasFilters(): bool
    {
        return ! empty($this->filters);
    }

    public function getFiltersColumns(): int
    {
        return $this->filtersColumns;
    }

    public function getEmptyStateHeading(): ?string
    {
        return $this->emptyStateHeading;
    }

    public function getEmptyStateDescription(): ?string
    {
        return $this->emptyStateDescription;
    }

    public function getEmptyStateIcon(): ?string
    {
        return $this->emptyStateIcon;
    }

    public function hasEmptyState(): bool
    {
        return $this->emptyStateHeading !== null;
    }

    public function getColumnHeaderAction(): ?Action
    {
        return $this->columnHeaderAction;
    }

    public function hasColumnHeaderAction(): bool
    {
        return $this->columnHeaderAction !== null;
    }

    public function getCardFooterActions(): array
    {
        return $this->cardFooterActions;
    }

    public function hasCardFooterActions(): bool
    {
        return ! empty($this->cardFooterActions);
    }

    public function isCollapsible(): bool
    {
        return $this->collapsible;
    }

    public function hasWipLimits(): bool
    {
        if (! empty($this->wipLimits) || $this->defaultWipLimit !== null) {
            return true;
        }

        if ($this->columnSource instanceof EnumColumnSource && $this->columnSource->implementsKanbanStatus()) {
            return ! empty($this->columnSource->resolveWipLimits());
        }

        return false;
    }

    public function hasColumnSummary(): bool
    {
        return $this->columnSummary !== null;
    }

    public function hasLoading(): bool
    {
        return $this->loading;
    }

    /**
     * Get drag constraints, merging explicit ones with enum-defined transitions.
     * Explicit constraints take priority per column.
     */
    public function getDragConstraints(): array
    {
        $constraints = $this->dragConstraints;

        // Merge enum-defined transitions (explicit constraints take priority)
        if ($this->columnSource instanceof EnumColumnSource && $this->columnSource->implementsKanbanStatus()) {
            $enumConstraints = $this->columnSource->resolveDragConstraints();
            $constraints = array_merge($enumConstraints, $constraints);
        }

        return $constraints;
    }

    public function hasDragConstraints(): bool
    {
        return ! empty($this->getDragConstraints());
    }

    public function getCanMove(): ?Closure
    {
        return $this->canMove;
    }

    /**
     * Check if a move is allowed by all server-side guards.
     */
    public function isMoveAllowed(Model $record, string $oldValue, string $newValue): bool
    {
        // Check drag constraints (merged: explicit + enum-defined)
        $constraints = $this->getDragConstraints();
        if (! empty($constraints)) {
            $allowed = $constraints[$oldValue] ?? null;
            if ($allowed !== null && ! in_array($newValue, $allowed, true)) {
                return false;
            }
        }

        // Check WIP limit on target column
        $wipLimit = $this->getWipLimit($newValue);
        if ($wipLimit !== null) {
            $source = $this->getColumnSource();
            $count = $record->newQuery()
                ->where($source->getColumnAttribute(), $newValue)
                ->count();

            if ($count >= $wipLimit) {
                return false;
            }
        }

        // Check custom canMove callback
        if ($this->canMove) {
            return (bool) ($this->canMove)($record, $oldValue, $newValue);
        }

        return true;
    }
}
