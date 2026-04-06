# Filament Kanban Board

An advanced Kanban Board package for Filament v5. Drop it into any Resource's List page to replace the table with a fully interactive board.

## Features

- Drag-and-drop cards between columns (SortableJS)
- Enum-based or relationship-based columns
- Card click action (modal, slide-over, or custom)
- Card footer actions (edit, delete, custom)
- Column header actions (create with pre-filled status)
- Filters dropdown with active count badge
- Search bar
- Collapsible columns (persisted in localStorage)
- WIP limits with visual warnings
- Column summaries (aggregates)
- Empty state per column
- Drag constraints (restrict allowed moves)
- `canMove()` callback for custom authorization
- Server-side authorization (resource policy check)
- Loading indicator
- Custom views (card, column, board)
- Dark mode support
- Accessibility (ARIA attributes)
- Publishable Blade views

## Installation

```bash
composer require wezlo/filament-kanban
```

Register the plugin in your Panel Provider:

```php
use Wezlo\FilamentKanban\FilamentKanbanPlugin;

->plugins([
    FilamentKanbanPlugin::make(),
])
```

## Usage

Add the `HasKanbanBoard` trait to your Resource's List page and define the `kanban()` method:

```php
use Wezlo\FilamentKanban\Concerns\HasKanbanBoard;
use Wezlo\FilamentKanban\KanbanBoard;

class ListLeads extends ListRecords
{
    use HasKanbanBoard;

    protected static string $resource = LeadResource::class;

    public function kanban(KanbanBoard $kanban): KanbanBoard
    {
        return $kanban
            ->enumColumn('status', LeadStatus::class)
            ->cardTitle(fn ($record) => $record->title)
            ->cardDescription(fn ($record) => $record->assignee?->name);
    }
}
```

That's it. The board replaces the table with columns from your enum.

## Configuration

### Column Source

**Enum-based** (columns from a BackedEnum):
```php
->enumColumn('status', LeadStatus::class)
```

The enum should implement `HasLabel`, `HasColor`, and optionally `HasIcon` for automatic column styling.

**Relationship-based** (columns from a related model):
```php
->relationshipColumn('stage', 'name', Stage::class, orderAttribute: 'sort_order')
```

### Card Content

```php
->cardTitle(fn ($record) => $record->title)
->cardDescription(fn ($record) => $record->assignee?->name)
->cardBadges(fn ($record) => [
    ['label' => $record->priority->getLabel(), 'color' => $record->priority->getColor()],
])
```

### Card Click Action

Open a modal/slide-over when clicking a card:

```php
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;

->cardAction(
    Action::make('view')
        ->slideOver()
        ->schema([
            TextEntry::make('title'),
            TextEntry::make('status')->badge(),
        ])
        ->fillForm(fn ($record) => $record->toArray())
        ->modalSubmitAction(false)
        ->modalCancelActionLabel('Close')
)
```

### Card Footer Actions

```php
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

->cardFooterActions([
    Action::make('edit')
        ->icon(Heroicon::PencilSquare)
        ->color('gray')
        ->url(fn ($record) => LeadResource::getUrl('edit', ['record' => $record])),
    Action::make('delete')
        ->icon(Heroicon::Trash)
        ->color('danger')
        ->requiresConfirmation()
        ->action(fn ($record) => $record->delete()),
])
```

### Column Header Action

Add a "+" button per column to create records with the status pre-filled:

```php
use Filament\Actions\CreateAction;

->columnHeaderAction(CreateAction::make())
```

### Filters

```php
use Filament\Forms\Components\Select;

->filters([
    Select::make('priority')
        ->options(LeadPriority::class)
        ->placeholder('All Priorities'),
    Select::make('assigned_to')
        ->relationship('assignee', 'name')
        ->placeholder('All Assignees'),
])
->filtersColumns(2) // grid columns for the filter panel
```

### Search

```php
->searchable(['title', 'client.user.name'])
```

Supports dot notation for relationship columns.

### Collapsible Columns

```php
->collapsible()
```

State is persisted per column in `localStorage`.

### WIP Limits

```php
->wipLimits(['new' => 5, 'in_progress' => 10])
->defaultWipLimit(20)
```

The count badge turns red when a column exceeds its limit. Moves into over-limit columns are blocked server-side.

### Column Summaries

```php
->columnSummary(function ($records, $column) {
    $total = $records->sum('estimated_budget');
    return $total > 0 ? 'SAR ' . number_format($total, 0) : null;
})
```

### Empty State

```php
->emptyState('No leads', 'Drag leads here or create a new one')
```

### Drag Constraints

Restrict which columns a card can be moved to:

```php
->dragConstraints([
    'new' => [LeadStatus::Contacted, LeadStatus::Lost],
    'contacted' => [LeadStatus::SiteVisit, LeadStatus::Lost],
])
```

Enforced both client-side (SortableJS) and server-side.

### Authorization (canMove)

Custom callback to control whether a move is allowed:

```php
->canMove(function ($record, $oldStatus, $newStatus) {
    // Only project managers can move to 'won'
    if ($newStatus === 'won') {
        return auth()->user()->hasRole('project-manager');
    }
    return true;
})
```

The package also checks the Resource's `canEdit()` policy before any move.

### Move Callback

Run logic after a card is moved:

```php
->onRecordMoved(function ($record, $fromValue, $toValue) {
    activity()->performedOn($record)->log("Moved from {$fromValue} to {$toValue}");
})
```

### Query Customization

```php
->modifyQueryUsing(fn ($query) => $query->where('company_id', auth()->user()->company_id))
->recordsPerColumn(50)
->excludeColumns([LeadStatus::Lost])
```

### Column Appearance

```php
->columnWidth('320px')
->columnColor(fn ($column) => $column->color ?? 'gray')
```

### Custom Views

Override any view:

```php
->cardView('leads.kanban.card')     // receives $record, $board, $column
->columnView('leads.kanban.column') // receives $column, $board
->boardView('leads.kanban.board')
```

Or publish all views:

```bash
php artisan vendor:publish --tag=filament-kanban-views
```

### Loading Indicator

Enabled by default. Shows a spinner overlay during Livewire updates.

```php
->loading(false) // disable
```

## Custom Theme

If you have a custom Filament theme, add the package views to your `@source` directive:

```css
@source '../../../../vendor/wezlo/filament-kanban/resources/views/**/*';
```

## Testing

```bash
php artisan test --filter=KanbanBoard
```

## License

MIT
