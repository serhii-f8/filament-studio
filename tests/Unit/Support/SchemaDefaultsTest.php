<?php

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Flexpik\FilamentStudio\FieldTypes\FieldTypeRegistry;
use Flexpik\FilamentStudio\Panels\PanelTypeRegistry;
use Flexpik\FilamentStudio\Support\SchemaDefaults;

it('initialises every field to its default, or null when it has none', function () {
    expect(SchemaDefaults::from([
        Toggle::make('enabled')->default(true),
        TextInput::make('prefix'),
        TextInput::make('columns')->default(2),
    ]))->toBe(['enabled' => true, 'prefix' => null, 'columns' => 2]);
});

it('descends into layout components, honouring their state path', function () {
    expect(SchemaDefaults::from([
        Grid::make()->schema([Toggle::make('flat')->default(false)]),
        Grid::make()->statePath('nested')->schema([TextInput::make('inner')->default('x')]),
    ]))->toBe(['flat' => false, 'nested' => ['inner' => 'x']]);
});

it('falls back to null when a default needs a mounted container', function () {
    expect(SchemaDefaults::from([
        TextInput::make('needs_container')->default(fn (TextInput $component) => $component->getContainer()->getStatePath()),
    ]))->toBe(['needs_container' => null]);
});

it('resolves defaults for every registered field and panel type', function () {
    foreach (app(FieldTypeRegistry::class)->all() as $type) {
        expect(SchemaDefaults::from($type::settingsSchema()))->toBeArray();
    }

    foreach (app(PanelTypeRegistry::class)->all() as $type) {
        expect($type::initialConfig())->toBeArray();
    }
});
