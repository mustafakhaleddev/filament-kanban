# Filament Kanban Board

An advanced Kanban Board package for Filament. Drop it into any Resource's List page to replace the table with a fully interactive board.

## Requirements

- PHP 8.3+
- Laravel 13+
- Filament 4+

## Features

- Drag-and-drop cards between columns (SortableJS)
- Enum-based or relationship-based columns
- `KanbanStatusEnum` interface for defining transitions & WIP limits on the enum itself
- Card click action (modal, slide-over, or custom)
- Card footer actions (edit, delete, URL navigation, custom)
- Column header actions (create with pre-filled status)
- Filters dropdown with active count badge & reset
- Search bar with relationship support
- Collapsible columns (persisted in localStorage)
- WIP limits with visual warnings and server-side enforcement
- Column summaries (aggregates)
- Empty state per column
- Drag constraints (client-side + server-side)
- `canMove()` callback for custom authorization
- Resource policy authorization on every move
- Loading indicator
- Custom views (card, column, board)
- Dark mode support
- Accessibility (ARIA roles, labels, keyboard-friendly)
- Publishable Blade views
- Error notifications on failed moves

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

## Quick Start

Add `HasKanbanBoard` to your Resource's List page and define `kanban()`:

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

The board replaces the table. Columns are generated from your enum. The breadcrumb shows "Board" instead of "List".

## KanbanStatusEnum Interface

For full integration, implement `KanbanStatusEnum` on your enum. This lets you define allowed transitions and WIP limits directly on the enum -- no board configuration needed.

```php
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Icons\Heroicon;
use Wezlo\FilamentKanban\Contracts\KanbanStatusEnum;

enum LeadStatus: string implements HasIcon, KanbanStatusEnum
{
    case New = 'new';
    case Contacted = 'contacted';
    case SiteVisit = 'site_visit';
    case Negotiation = 'negotiation';
    case Won = 'won';
    case Lost = 'lost';

    // Required by HasLabel (via KanbanStatusEnum)
    public function getLabel(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Contacted => 'Contacted',
            // ...
        };
    }

    // Required by HasColor (via KanbanStatusEnum)
    public function getColor(): string
    {
        return match ($this) {
            self::New => 'info',
            self::Contacted => 'warning',
            // ...
        };
    }

    // Optional: HasIcon
    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::New => Heroicon::Sparkles,
            // ...
        };
    }

    // Define which statuses each status can transition to.
    // Return null to allow all transitions.
    public function getAllowedTransitions(): ?array
    {
        return match ($this) {
            self::New => [self::Contacted, self::Lost],
            self::Contacted => [self::SiteVisit, self::Lost],
            self::SiteVisit => [self::Negotiation, self::Lost],
            self::Negotiation => [self::Won, self::Lost],
            self::Won => null,  // no constraints
            self::Lost => null,
        };
    }

    // Set max cards per column. Return null for unlimited.
    public function getWipLimit(): ?int
    {
        return match ($this) {
            self::Negotiation => 10,
            default => null,
        };
    }
}
```

The board automatically reads these -- just use `->enumColumn('status', LeadStatus::class)` and transitions + WIP limits are enforced both client-side and server-side.

**Without the interface:** Regular `BackedEnum` with `HasLabel` + `HasColor` still works. You just configure constraints on the board instead.

**Explicit overrides:** Board-level `->dragConstraints()` and `->wipLimits()` override enum values per column.

## Configuration

### Column Source

**Enum-based** (columns from a BackedEnum):
```php
->enumColumn('status', LeadStatus::class)
```

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

Pass any Filament Action to fire when a card is clicked:

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

Clicking opens the modal. Dragging still works -- the package distinguishes clicks from drags using SortableJS events.

### Card Footer Actions

Icon buttons at the bottom of each card. Actions with `->url()` render as links, others use Livewire modals.

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

"+" button per column. The column value is pre-filled into the form.

```php
use Filament\Actions\CreateAction;

->columnHeaderAction(CreateAction::make())
```

### Filters

Renders as a dropdown panel triggered by a filter icon next to the search bar. Shows active filter count as a badge.

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
->filtersColumns(2) // grid columns inside the dropdown
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

State persisted per column in `localStorage`.

### WIP Limits

Define on the enum via `KanbanStatusEnum::getWipLimit()`, or on the board:

```php
->wipLimits(['new' => 5, 'in_progress' => 10])
->defaultWipLimit(20)
```

The count badge turns red when over limit. Moves into over-limit columns are **blocked server-side** with a notification.

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

Define on the enum via `KanbanStatusEnum::getAllowedTransitions()`, or on the board:

```php
->dragConstraints([
    'new' => [LeadStatus::Contacted, LeadStatus::Lost],
    'contacted' => [LeadStatus::SiteVisit, LeadStatus::Lost],
])
```

Enforced both client-side (SortableJS `put` function) and server-side (before DB update).

### Authorization

**Resource policy:** The package checks `Resource::canEdit($record)` before every move. Unauthorized moves show a danger notification.

**canMove callback:** Custom business logic:

```php
->canMove(function ($record, $oldStatus, $newStatus) {
    if ($newStatus === 'won') {
        return auth()->user()->hasRole('project-manager');
    }
    return true;
})
```

**Order of checks:** Resource policy -> Drag constraints -> WIP limits -> canMove callback. First failure blocks the move.

### Move Callback

Run logic after a successful move:

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
