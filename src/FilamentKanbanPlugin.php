<?php

namespace Wezlo\FilamentKanban;

use Filament\Contracts\Plugin;
use Filament\Panel;

class FilamentKanbanPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'filament-kanban';
    }

    public function register(Panel $panel): void {}

    public function boot(Panel $panel): void {}
}
