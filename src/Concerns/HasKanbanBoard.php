<?php

namespace Wezlo\FilamentKanban\Concerns;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Wezlo\FilamentKanban\KanbanBoard;
use Wezlo\FilamentKanban\KanbanColumn;

trait HasKanbanBoard
{
    public ?string $kanbanSearch = null;

    public array $kanbanFilters = [];

    abstract public function kanban(KanbanBoard $kanban): KanbanBoard;

    public function getBreadcrumb(): string
    {
        return __('Board');
    }

    public function content(Schema $schema): Schema
    {
        $boardView = $this->getKanbanBoard()->getBoardView();

        return $schema->components([
            View::make($boardView),
        ]);
    }

    /**
     * Named form schema for kanban filters, rendered inside board.blade.php.
     */
    public function kanbanFiltersForm(Schema $schema): Schema
    {
        $board = $this->getKanbanBoard();

        return $schema
            ->columns($board->getFiltersColumns())
            ->schema($board->getFilters())
            ->statePath('kanbanFilters')
            ->model(static::getResource()::getModel())
            ->live();
    }

    public function getKanbanBoard(): KanbanBoard
    {
        return $this->kanban(KanbanBoard::make());
    }

    /**
     * Filament action: fired when a column header "+" is clicked.
     */
    public function kanbanColumnAction(): Action
    {
        $board = $this->getKanbanBoard();
        $action = $board->getColumnHeaderAction();

        if (! $action) {
            return Action::make('kanbanColumn')->hidden();
        }

        $source = $board->getColumnSource();

        return $action
            ->name('kanbanColumn')
            ->fillForm(function (array $arguments) use ($source): array {
                return [$source->getColumnAttribute() => $arguments['column'] ?? null];
            });
    }

    /**
     * Filament action: fired when a card footer action button is clicked.
     */
    public function kanbanCardFooterAction(): Action
    {
        return Action::make('kanbanCardFooter')
            ->record(function (array $arguments): Model {
                $resource = static::getResource();

                return $resource::getModel()::findOrFail($arguments['record']);
            })
            ->action(function (Model $record, array $arguments): void {
                $board = $this->getKanbanBoard();
                $actions = $board->getCardFooterActions();
                $actionName = $arguments['actionName'] ?? null;

                foreach ($actions as $footerAction) {
                    if ($footerAction->getName() === $actionName) {
                        $footerAction->record($record)->call();

                        return;
                    }
                }
            });
    }

    /**
     * Filament action: fired when a card is clicked (if cardAction is configured).
     */
    public function kanbanCardClickAction(): Action
    {
        $board = $this->getKanbanBoard();
        $resource = static::getResource();
        $action = $board->getCardClickAction();

        if (! $action) {
            return Action::make('kanbanCardClick')->hidden();
        }

        return $action
            ->name('kanbanCardClick')
            ->record(function (array $arguments) use ($resource): Model {
                return $resource::getModel()::findOrFail($arguments['record']);
            });
    }

    /**
     * @return array<int, KanbanColumn>
     */
    public function getKanbanColumns(): array
    {
        $board = $this->getKanbanBoard();
        $source = $board->getColumnSource();

        $columns = $source->resolveColumns();

        $excluded = $board->getExcludedColumns();

        if (! empty($excluded)) {
            $columns = array_values(array_filter(
                $columns,
                fn (KanbanColumn $column) => ! in_array($column->value, $excluded, true),
            ));
        }

        $query = $this->getKanbanQuery($board);

        foreach ($columns as $column) {
            $columnQuery = clone $query;
            $columnQuery->where($source->getColumnAttribute(), $column->value);

            if ($orderColumn = $board->getOrderColumn()) {
                $columnQuery->orderBy($orderColumn);
            }

            if ($limit = $board->getRecordsPerColumn()) {
                $columnQuery->limit($limit);
            }

            $column->records = $columnQuery->get();
            $column->count = $column->records->count();
        }

        return $columns;
    }

    protected function getKanbanQuery(KanbanBoard $board): Builder
    {
        $query = static::getResource()::getEloquentQuery();

        if ($modifier = $board->getQueryModifier()) {
            $query = $modifier($query);
        }

        foreach ($this->kanbanFilters as $attribute => $value) {
            if (filled($value)) {
                $query->where($attribute, $value);
            }
        }

        if (filled($this->kanbanSearch) && ! empty($board->getSearchableColumns())) {
            $search = $this->kanbanSearch;
            $searchableColumns = $board->getSearchableColumns();

            $query->where(function (Builder $q) use ($searchableColumns, $search) {
                foreach ($searchableColumns as $col) {
                    if (str_contains($col, '.')) {
                        $parts = explode('.', $col);
                        $attribute = array_pop($parts);
                        $relation = implode('.', $parts);

                        $q->orWhereHas($relation, function (Builder $relQuery) use ($attribute, $search) {
                            $relQuery->where($attribute, 'like', "%{$search}%");
                        });
                    } else {
                        $q->orWhere($col, 'like', "%{$search}%");
                    }
                }
            });
        }

        return $query;
    }

    public function moveRecord(string $recordId, string $newColumnValue, array $orderedIds): void
    {
        $board = $this->getKanbanBoard();
        $source = $board->getColumnSource();
        $modelClass = static::getResource()::getModel();

        $record = $modelClass::findOrFail($recordId);

        // Authorization: check resource policy
        if (! static::getResource()::canEdit($record)) {
            Notification::make()
                ->title(__('You are not authorized to move this record.'))
                ->danger()
                ->send();

            return;
        }

        $rawOldValue = $record->{$source->getColumnAttribute()};
        $oldValue = $rawOldValue instanceof \BackedEnum
            ? (string) $rawOldValue->value
            : (string) $rawOldValue;

        // Server-side guard: drag constraints + WIP limits + canMove callback
        if (! $board->isMoveAllowed($record, $oldValue, $newColumnValue)) {
            Notification::make()
                ->title(__('This move is not allowed.'))
                ->danger()
                ->send();

            return;
        }

        try {
            DB::transaction(function () use ($record, $source, $newColumnValue, $orderedIds, $board, $modelClass, $oldValue) {
                $attribute = $source->getColumnAttribute();

                $record->{$attribute} = $newColumnValue;
                $record->save();

                if ($orderColumn = $board->getOrderColumn()) {
                    foreach ($orderedIds as $position => $id) {
                        $modelClass::where('id', $id)->update([$orderColumn => $position]);
                    }
                }

                if ($callback = $board->getOnRecordMoved()) {
                    $callback($record->fresh(), $oldValue, $newColumnValue);
                }
            });
        } catch (\Throwable $e) {
            report($e);

            Notification::make()
                ->title(__('Failed to move record.'))
                ->danger()
                ->send();
        }
    }
}
