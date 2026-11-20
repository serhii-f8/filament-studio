<?php

use Filament\Actions\Action;
use Flexpik\FilamentStudio\Models\StudioCollection;
use Flexpik\FilamentStudio\Models\StudioField;
use Flexpik\FilamentStudio\Models\StudioRecordVersion;
use Flexpik\FilamentStudio\Resources\DynamicCollectionResource;
use Flexpik\FilamentStudio\Resources\DynamicCollectionResource\Pages\EditCollectionRecord;
use Flexpik\FilamentStudio\Resources\DynamicCollectionResource\Pages\ViewCollectionRecord;
use Flexpik\FilamentStudio\Services\EavQueryBuilder;
use Illuminate\Foundation\Auth\User;
use Illuminate\Routing\Route;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

it('restores the chosen version from the record pages', function (string $page) {
    DynamicCollectionResource::resetResolvedCollection();
    actingAs(User::forceCreate(['name' => 'Test', 'email' => fake()->unique()->safeEmail(), 'password' => bcrypt('password')]));

    $collection = StudioCollection::factory()->create(['name' => 'docs', 'slug' => 'docs', 'enable_versioning' => true]);
    StudioField::factory()->create(['collection_id' => $collection->id, 'column_name' => 'title', 'label' => 'Title', 'field_type' => 'text', 'eav_cast' => 'text']);
    $route = new Route('GET', 'admin/studio/{collection_slug}', []);
    $route->bind(request());
    $route->setParameter('collection_slug', 'docs');
    request()->setRouteResolver(fn () => $route);

    $builder = fn () => EavQueryBuilder::for($collection);
    $record = $builder()->create(['title' => 'Original']);
    $this->travel(1)->seconds();
    $builder()->update($record->id, ['title' => 'Second']);
    $this->travel(1)->seconds();
    $builder()->update($record->id, ['title' => 'Third']);

    $oldest = StudioRecordVersion::where('record_id', $record->id)->orderBy('id')->first();
    expect($oldest->snapshot['title'])->toBe('Original');

    Livewire::test($page, ['collectionSlug' => 'docs', 'record' => $record->uuid])
        ->callAction('restoreVersion', arguments: ['versionId' => $oldest->id]);

    expect($builder()->getRecordData($record->fresh())['title'])->toBe('Original');
})->with([
    'edit page' => EditCollectionRecord::class,
    'view page' => ViewCollectionRecord::class,
]);

it('restores from inside the open version history, as the Restore button does', function (string $page) {
    DynamicCollectionResource::resetResolvedCollection();
    actingAs(User::forceCreate(['name' => 'Test', 'email' => fake()->unique()->safeEmail(), 'password' => bcrypt('password')]));

    $collection = StudioCollection::factory()->create(['name' => 'docs', 'slug' => 'docs', 'enable_versioning' => true]);
    StudioField::factory()->create(['collection_id' => $collection->id, 'column_name' => 'title', 'label' => 'Title', 'field_type' => 'text', 'eav_cast' => 'text']);
    $route = new Route('GET', 'admin/studio/{collection_slug}', []);
    $route->bind(request());
    $route->setParameter('collection_slug', 'docs');
    request()->setRouteResolver(fn () => $route);

    $builder = fn () => EavQueryBuilder::for($collection);
    $record = $builder()->create(['title' => 'Original']);
    $this->travel(1)->seconds();
    $builder()->update($record->id, ['title' => 'Second']);
    $oldest = StudioRecordVersion::where('record_id', $record->id)->orderBy('id')->first();

    Livewire::test($page, ['collectionSlug' => 'docs', 'record' => $record->uuid])
        ->mountAction('versionHistory')
        ->mountAction('restoreVersion', ['versionId' => $oldest->id])
        ->assertActionMounted('restoreVersion')
        ->callMountedAction();

    expect($builder()->getRecordData($record->fresh())['title'])->toBe('Original');
})->with([
    'edit page' => EditCollectionRecord::class,
    'view page' => ViewCollectionRecord::class,
]);

it('opens the version history without a Submit button that does nothing', function (string $page) {
    DynamicCollectionResource::resetResolvedCollection();
    actingAs(User::forceCreate(['name' => 'Test', 'email' => fake()->unique()->safeEmail(), 'password' => bcrypt('password')]));

    $collection = StudioCollection::factory()->create(['name' => 'docs', 'slug' => 'docs', 'enable_versioning' => true]);
    StudioField::factory()->create(['collection_id' => $collection->id, 'column_name' => 'title', 'label' => 'Title', 'field_type' => 'text', 'eav_cast' => 'text']);
    $route = new Route('GET', 'admin/studio/{collection_slug}', []);
    $route->bind(request());
    $route->setParameter('collection_slug', 'docs');
    request()->setRouteResolver(fn () => $route);

    $record = EavQueryBuilder::for($collection)->create(['title' => 'Original']);

    Livewire::test($page, ['collectionSlug' => 'docs', 'record' => $record->uuid])
        ->assertActionExists('versionHistory', fn (Action $action): bool => $action->getModalSubmitAction() === null
            && $action->getModalCancelAction()?->getLabel() === 'Close');
})->with([
    'edit page' => EditCollectionRecord::class,
    'view page' => ViewCollectionRecord::class,
]);
