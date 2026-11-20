<?php

use Flexpik\FilamentStudio\Models\StudioCollection;
use Flexpik\FilamentStudio\Models\StudioDashboard;
use Flexpik\FilamentStudio\Models\StudioPanel;
use Flexpik\FilamentStudio\Resources\DashboardResource\Pages\EditDashboard;
use Flexpik\FilamentStudio\Resources\DashboardResource\RelationManagers\PanelsRelationManager;
use Illuminate\Foundation\Auth\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    actingAs(User::forceCreate(['name' => 'Test', 'email' => fake()->unique()->safeEmail(), 'password' => bcrypt('password')]));
    $this->dashboard = StudioDashboard::factory()->create();
});

it('confirms the new panel and tells the panels table to refresh', function () {
    $collection = StudioCollection::factory()->create();

    Livewire::test(EditDashboard::class, ['record' => $this->dashboard->getRouteKey()])
        ->callAction('createPanel', [
            'panel_type' => 'label',
            'header_label' => 'Welcome',
            'config' => ['text' => 'Hello'],
        ])
        ->assertHasNoActionErrors()
        ->assertNotified('Panel added')
        ->assertDispatched('studio-panels-updated');

    expect(StudioPanel::where('dashboard_id', $this->dashboard->id)->count())->toBe(1);
});

it('refreshes the panels table when a panel is added from the page header', function () {
    $table = Livewire::test(PanelsRelationManager::class, [
        'ownerRecord' => $this->dashboard,
        'pageClass' => EditDashboard::class,
    ])->assertSee('No studio panels');

    StudioPanel::factory()->create(['dashboard_id' => $this->dashboard->id, 'header_label' => 'Fresh panel']);

    $table->dispatch('studio-panels-updated')->assertSee('Fresh panel');
});
