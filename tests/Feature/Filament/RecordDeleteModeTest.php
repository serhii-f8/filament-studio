<?php

use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Flexpik\FilamentStudio\Models\StudioCollection;
use Flexpik\FilamentStudio\Models\StudioField;
use Flexpik\FilamentStudio\Models\StudioRecord;
use Flexpik\FilamentStudio\Models\StudioValue;
use Flexpik\FilamentStudio\Resources\DynamicCollectionResource;
use Flexpik\FilamentStudio\Resources\DynamicCollectionResource\Pages\ListCollectionRecords;
use Flexpik\FilamentStudio\Services\EavQueryBuilder;
use Illuminate\Foundation\Auth\User;
use Illuminate\Routing\Route;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

function deleteModeCollection(bool $softDeletes): StudioCollection
{
    DynamicCollectionResource::resetResolvedCollection();

    $collection = StudioCollection::factory()->create(['name' => 'notes', 'slug' => 'notes', 'enable_soft_deletes' => $softDeletes]);
    StudioField::factory()->create([
        'collection_id' => $collection->id, 'column_name' => 'title', 'label' => 'Title',
        'field_type' => 'text', 'eav_cast' => 'text', 'is_hidden_in_table' => false,
    ]);

    $route = new Route('GET', 'admin/studio/{collection_slug}', []);
    $route->bind(request());
    $route->setParameter('collection_slug', 'notes');
    request()->setRouteResolver(fn () => $route);

    return $collection;
}

beforeEach(function () {
    actingAs(User::forceCreate(['name' => 'Test', 'email' => fake()->unique()->safeEmail(), 'password' => bcrypt('password')]));
});

it('permanently deletes a record and its values when the collection has no soft deletes', function () {
    $collection = deleteModeCollection(softDeletes: false);
    $record = EavQueryBuilder::for($collection)->create(['title' => 'Gone']);

    Livewire::test(ListCollectionRecords::class, ['collectionSlug' => 'notes'])
        ->callTableAction(DeleteAction::class, $record);

    expect(StudioRecord::withTrashed()->whereKey($record->id)->exists())->toBeFalse()
        ->and(StudioValue::where('record_id', $record->id)->exists())->toBeFalse();
});

it('soft deletes a record when the collection has soft deletes', function () {
    $collection = deleteModeCollection(softDeletes: true);
    $record = EavQueryBuilder::for($collection)->create(['title' => 'Kept']);

    Livewire::test(ListCollectionRecords::class, ['collectionSlug' => 'notes'])
        ->callTableAction(DeleteAction::class, $record);

    expect(StudioRecord::withTrashed()->find($record->id)->deleted_at)->not->toBeNull()
        ->and(StudioValue::where('record_id', $record->id)->exists())->toBeTrue();
});

it('honours the delete mode for bulk deletes', function () {
    $collection = deleteModeCollection(softDeletes: false);
    $records = collect([1, 2])->map(fn ($i) => EavQueryBuilder::for($collection)->create(['title' => "Note {$i}"]));

    Livewire::test(ListCollectionRecords::class, ['collectionSlug' => 'notes'])
        ->callTableBulkAction(DeleteBulkAction::class, $records);

    expect(StudioRecord::withTrashed()->whereIn('id', $records->pluck('id'))->count())->toBe(0);
});
