<?php

use Flexpik\FilamentStudio\Models\StudioCollection;
use Flexpik\FilamentStudio\Models\StudioDashboard;
use Flexpik\FilamentStudio\Resources\CollectionManagerResource\Pages\EditCollection;
use Flexpik\FilamentStudio\Resources\CollectionManagerResource\RelationManagers\FieldsRelationManager;
use Flexpik\FilamentStudio\Resources\DashboardResource\Pages\EditDashboard;
use Flexpik\FilamentStudio\Resources\DashboardResource\RelationManagers\PanelsRelationManager;
use Illuminate\Foundation\Auth\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/*
 * Toggles entangle their state path with Alpine. When a type change reveals type-specific
 * settings whose paths were never initialised, the browser logs "Livewire Entangle Error".
 * Changing the type must therefore seed every revealed path with the type's defaults.
 */

beforeEach(function () {
    $this->user = User::forceCreate(['name' => 'Test', 'email' => fake()->unique()->safeEmail(), 'password' => bcrypt('password')]);
    actingAs($this->user);
});

it('initialises field type settings with their defaults when the field type changes', function () {
    $collection = StudioCollection::factory()->create();

    $component = Livewire::test(FieldsRelationManager::class, [
        'ownerRecord' => $collection,
        'pageClass' => EditCollection::class,
    ])
        ->mountTableAction('create')
        ->setTableActionData(['field_type' => 'text']);

    $settings = $component->get('mountedActions.0.data.settings');

    expect($settings)->toBeArray()
        ->and($settings)->toHaveKey('live_on_blur')
        ->and($settings['live_on_blur'])->toBeFalse()
        ->and($settings['subtype'])->toBe('default')
        ->and($settings)->toHaveKeys(['prefix', 'suffix', 'mask', 'autocomplete']);
});

it('replaces the previous type settings with the new type defaults and keeps conditions', function () {
    $collection = StudioCollection::factory()->create();

    $component = Livewire::test(FieldsRelationManager::class, [
        'ownerRecord' => $collection,
        'pageClass' => EditCollection::class,
    ])
        ->mountTableAction('create')
        ->setTableActionData(['field_type' => 'text'])
        ->set('mountedActions.0.data.settings.conditions.visible.logic', 'and')
        ->setTableActionData(['field_type' => 'section_header']);

    $settings = $component->get('mountedActions.0.data.settings');

    expect($settings)->not->toHaveKey('live_on_blur')
        ->and($settings['collapsible'])->toBeFalse()
        ->and($settings['collapsed'])->toBeFalse()
        ->and($settings['conditions']['visible']['logic'])->toBe('and');
});

it('initialises panel config defaults when the type changes in the dashboard Add Panel action', function () {
    $dashboard = StudioDashboard::factory()->create();

    $component = Livewire::test(EditDashboard::class, ['record' => $dashboard->getRouteKey()])
        ->mountAction('createPanel')
        ->set('mountedActions.0.data.panel_type', 'list');

    $config = $component->get('mountedActions.0.data.config');

    expect($config)->toBeArray()
        ->and($config)->toHaveKey('enable_inline_edit')
        ->and($config['enable_inline_edit'])->toBeFalse()
        ->and($config)->toHaveKey('collection_id');
});

it('initialises panel config defaults for toggles missing from defaultConfig in the panels relation manager', function () {
    $dashboard = StudioDashboard::factory()->create();

    $component = Livewire::test(PanelsRelationManager::class, [
        'ownerRecord' => $dashboard,
        'pageClass' => EditDashboard::class,
    ])
        ->mountTableAction('create')
        ->set('mountedActions.0.data.panel_type', 'list');

    $config = $component->get('mountedActions.0.data.config');

    expect($config)->toHaveKey('enable_inline_edit')
        ->and($config['enable_inline_edit'])->toBeFalse();
});

it('keeps the explicit defaultConfig values when seeding panel config', function () {
    $dashboard = StudioDashboard::factory()->create();

    $component = Livewire::test(EditDashboard::class, ['record' => $dashboard->getRouteKey()])
        ->mountAction('createPanel')
        ->set('mountedActions.0.data.panel_type', 'metric');

    $config = $component->get('mountedActions.0.data.config');

    expect($config['abbreviate'])->toBeFalse()
        ->and($config['decimal_precision'])->toBe(0)
        ->and($config['conditional_styles'])->toBe([]);
});
