<?php

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Schema;
use Flexpik\FilamentStudio\Models\StudioCollection;
use Flexpik\FilamentStudio\Models\StudioDashboard;
use Flexpik\FilamentStudio\Models\StudioField;
use Flexpik\FilamentStudio\Resources\ApiSettingsResource;
use Flexpik\FilamentStudio\Resources\ApiSettingsResource\Pages\EditApiKey;
use Flexpik\FilamentStudio\Resources\CollectionManagerResource\Pages\CreateCollection;
use Flexpik\FilamentStudio\Resources\CollectionManagerResource\Pages\EditCollection;
use Flexpik\FilamentStudio\Resources\CollectionManagerResource\RelationManagers\FieldsRelationManager;
use Flexpik\FilamentStudio\Resources\DashboardResource\Pages\CreateDashboard;
use Flexpik\FilamentStudio\Resources\DashboardResource\Pages\EditDashboard;
use Illuminate\Foundation\Auth\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    actingAs(User::forceCreate(['name' => 'Admin', 'email' => fake()->unique()->safeEmail(), 'password' => bcrypt('password')]));
});

// --- Collection labels ---

it('derives a singular label from a plural collection name in the wizard', function () {
    Livewire::test(CreateCollection::class)
        ->fillForm(['name' => 'qa_tasks'])
        ->assertFormSet(['label' => 'Qa Task', 'label_plural' => 'Qa Tasks', 'slug' => 'qa-tasks']);
});

it('calls fields "field" in the field editor, not "studio field"', function () {
    $collection = StudioCollection::factory()->create();

    Livewire::test(FieldsRelationManager::class, ['ownerRecord' => $collection, 'pageClass' => EditCollection::class])
        ->assertSee('New field')
        ->assertDontSee('studio field', escape: false)
        ->assertDontSee('Studio Field');
});

it('has a single Audit Log on the collection edit page (the tab, not a header action)', function () {
    $collection = StudioCollection::factory()->create();

    Livewire::test(EditCollection::class, ['record' => $collection->getRouteKey()])
        ->assertActionDoesNotExist('auditLog');
});

// --- Fields ---

it('makes fields created outside the field editor filterable by default', function () {
    $collection = StudioCollection::factory()->create();

    $field = StudioField::create([
        'collection_id' => $collection->id, 'column_name' => 'title', 'label' => 'Title',
        'field_type' => 'text', 'eav_cast' => 'text', 'sort_order' => 1,
    ]);

    expect($field->fresh()->is_filterable)->toBeTrue();
});

// --- Dashboards ---

it('fills the dashboard slug from the name on create', function () {
    Livewire::test(CreateDashboard::class)
        ->fillForm(['name' => 'Sales Overview'])
        ->assertFormSet(['slug' => 'sales-overview']);
});

it('keeps a hand-edited dashboard slug when the name changes', function () {
    Livewire::test(CreateDashboard::class)
        ->fillForm(['name' => 'Sales'])
        ->fillForm(['slug' => 'my-own-slug'])
        ->fillForm(['name' => 'Sales Overview'])
        ->assertFormSet(['slug' => 'my-own-slug']);
});

it('does not rewrite an existing dashboard slug when it is renamed', function () {
    $dashboard = StudioDashboard::factory()->create(['name' => 'Old', 'slug' => 'old']);

    Livewire::test(EditDashboard::class, ['record' => $dashboard->getRouteKey()])
        ->fillForm(['name' => 'New name'])
        ->assertFormSet(['slug' => 'old']);
});

it('uses a colour picker for the dashboard colour', function () {
    Livewire::test(CreateDashboard::class)
        ->assertFormFieldExists('color', fn ($field): bool => $field instanceof ColorPicker);
});

// --- API keys ---

it('starts the API key forms with no collection permission rows', function () {
    $page = new EditApiKey;
    $repeaters = collect([
        ApiSettingsResource::form(Schema::make($page)),
        $page->form(Schema::make($page)),
    ])->map(fn (Schema $schema) => collect($schema->getFlatComponents(withHidden: true))
        ->first(fn ($component) => $component instanceof Repeater && $component->getName() === 'permission_entries'));

    // Filament's Repeater starts with one (required) row unless told otherwise.
    expect($repeaters)->each->toBeInstanceOf(Repeater::class)
        ->and($repeaters->map->getDefaultState()->all())->toBe([[], []]);
});
