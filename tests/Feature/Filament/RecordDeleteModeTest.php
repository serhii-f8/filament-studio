<?php

use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Flexpik\FilamentStudio\Models\StudioCollection;
use Flexpik\FilamentStudio\Models\StudioField;
use Flexpik\FilamentStudio\Models\StudioRecord;
use Flexpik\FilamentStudio\Models\StudioValue;
use Flexpik\FilamentStudio\Resources\DynamicCollectionResource;
use Flexpik\FilamentStudio\Resources\DynamicCollectionResource\Pages\ListCollectionRecords;
use Flexpik\FilamentStudio\Services\EavQueryBuilder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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

it('hides soft-deleted records from the list until the trashed filter asks for them', function () {
    $collection = deleteModeCollection(softDeletes: true);
    $kept = EavQueryBuilder::for($collection)->create(['title' => 'Kept']);
    $trashed = EavQueryBuilder::for($collection)->create(['title' => 'Binned']);
    EavQueryBuilder::for($collection)->delete($trashed->id);

    Livewire::test(ListCollectionRecords::class, ['collectionSlug' => 'notes'])
        ->assertCanSeeTableRecords([$kept])
        ->assertCanNotSeeTableRecords([$trashed])
        ->filterTable('trashed', false)
        ->assertCanSeeTableRecords([$trashed])
        ->assertCanNotSeeTableRecords([$kept])
        ->filterTable('trashed', true)
        ->assertCanSeeTableRecords([$kept, $trashed]);
});

it('offers no trashed filter when the collection has no soft deletes', function () {
    deleteModeCollection(softDeletes: false);

    $table = Livewire::test(ListCollectionRecords::class, ['collectionSlug' => 'notes'])->instance()->getTable();

    expect($table->getFilter('trashed'))->toBeNull();
});

it('restores a soft-deleted record from the trash', function () {
    $collection = deleteModeCollection(softDeletes: true);
    $record = EavQueryBuilder::for($collection)->create(['title' => 'Back']);
    EavQueryBuilder::for($collection)->delete($record->id);

    Livewire::test(ListCollectionRecords::class, ['collectionSlug' => 'notes'])
        ->filterTable('trashed', false)
        ->assertTableActionHidden(DeleteAction::class, $record)
        ->assertTableActionHidden(EditAction::class, $record)
        ->callTableAction(RestoreAction::class, $record)
        ->assertHasNoTableActionErrors();

    expect(StudioRecord::find($record->id))->not->toBeNull()
        ->and(EavQueryBuilder::for($collection)->getRecordData(StudioRecord::find($record->id))['title'])->toBe('Back');
});

it('permanently deletes a soft-deleted record and its values from the trash', function () {
    $collection = deleteModeCollection(softDeletes: true);
    $record = EavQueryBuilder::for($collection)->create(['title' => 'Gone for good']);
    EavQueryBuilder::for($collection)->delete($record->id);

    Livewire::test(ListCollectionRecords::class, ['collectionSlug' => 'notes'])
        ->filterTable('trashed', false)
        ->callTableAction(ForceDeleteAction::class, $record);

    expect(StudioRecord::withTrashed()->whereKey($record->id)->exists())->toBeFalse()
        ->and(StudioValue::where('record_id', $record->id)->exists())->toBeFalse();
});

it('hides restore and force delete on records that are not trashed', function () {
    $collection = deleteModeCollection(softDeletes: true);
    $record = EavQueryBuilder::for($collection)->create(['title' => 'Live']);

    Livewire::test(ListCollectionRecords::class, ['collectionSlug' => 'notes'])
        ->assertTableActionHidden(RestoreAction::class, $record)
        ->assertTableActionHidden(ForceDeleteAction::class, $record)
        ->assertTableActionVisible(DeleteAction::class, $record);
});

it('bulk restores and bulk force deletes trashed records', function () {
    $collection = deleteModeCollection(softDeletes: true);
    $records = collect([1, 2, 3, 4])->map(fn ($i) => EavQueryBuilder::for($collection)->create(['title' => "Note {$i}"]));
    $records->each(fn ($r) => EavQueryBuilder::for($collection)->delete($r->id));
    $records = StudioRecord::onlyTrashed()->whereIn('id', $records->pluck('id'))->get();

    Livewire::test(ListCollectionRecords::class, ['collectionSlug' => 'notes'])
        ->filterTable('trashed', false)
        ->callTableBulkAction(RestoreBulkAction::class, $records->take(2))
        ->callTableBulkAction(ForceDeleteBulkAction::class, $records->skip(2));

    expect(StudioRecord::whereIn('id', $records->take(2)->pluck('id'))->count())->toBe(2)
        ->and(StudioRecord::withTrashed()->whereIn('id', $records->skip(2)->pluck('id'))->count())->toBe(0);
});

it('cannot restore a record from another collection', function () {
    $collection = deleteModeCollection(softDeletes: true);
    $other = StudioCollection::factory()->create(['enable_soft_deletes' => true, 'tenant_id' => $collection->tenant_id]);
    $foreign = StudioRecord::factory()->create(['collection_id' => $other->id, 'tenant_id' => $collection->tenant_id]);
    $foreign->delete();

    expect(fn () => EavQueryBuilder::for($collection)->restore($foreign->id))
        ->toThrow(ModelNotFoundException::class);
});
