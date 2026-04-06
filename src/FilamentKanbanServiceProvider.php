<?php

namespace Wezlo\FilamentKanban;

use Filament\Support\Assets\AlpineComponent;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentKanbanServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-kanban';

    public function configurePackage(Package $package): void
    {
        $package->name(static::$name)
            ->hasConfigFile()
            ->hasViews();
    }

    public function packageBooted(): void
    {
        FilamentAsset::register([
            Css::make('filament-kanban', __DIR__.'/../resources/dist/filament-kanban.css'),
            AlpineComponent::make('filament-kanban', __DIR__.'/../resources/dist/filament-kanban.js'),
        ], package: 'wezlo/filament-kanban');
    }
}
