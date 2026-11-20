<?php

use Flexpik\FilamentStudio\Models\StudioCollection;
use Flexpik\FilamentStudio\Models\StudioField;
use Flexpik\FilamentStudio\Resources\DynamicCollectionResource;
use Flexpik\FilamentStudio\Resources\DynamicCollectionResource\Pages\CreateCollectionRecord;
use Flexpik\FilamentStudio\Resources\DynamicCollectionResource\Pages\EditCollectionRecord;
use Flexpik\FilamentStudio\Services\EavQueryBuilder;
use Illuminate\Foundation\Auth\User;
use Illuminate\Routing\Route;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    DynamicCollectionResource::resetResolvedCollection();

    actingAs(User::forceCreate(['name' => 'Test', 'email' => fake()->unique()->safeEmail(), 'password' => bcrypt('password')]));

    $this->collection = StudioCollection::factory()->create(['name' => 'tasks', 'slug' => 'tasks']);

    StudioField::factory()->required()->create([
        'collection_id' => $this->collection->id,
        'column_name' => 'title',
        'label' => 'Title',
        'field_type' => 'text',
        'eav_cast' => 'text',
        'is_unique' => true,
    ]);

    $route = new Route('GET', 'admin/studio/{collection_slug}', []);
    $route->bind(request());
    $route->setParameter('collection_slug', 'tasks');
    request()->setRouteResolver(fn () => $route);
});

it('rejects a duplicate value for a unique field in the create form', function () {
    EavQueryBuilder::for($this->collection)->create(['title' => 'First task']);

    Livewire::test(CreateCollectionRecord::class, ['collectionSlug' => 'tasks'])
        ->fillForm(['title' => 'First task'])
        ->call('create')
        ->assertHasFormErrors(['title']);
});

it('lets a record be saved with its own unique value but not another record\'s', function () {
    $first = EavQueryBuilder::for($this->collection)->create(['title' => 'First task']);
    EavQueryBuilder::for($this->collection)->create(['title' => 'Second task']);

    $page = Livewire::test(EditCollectionRecord::class, ['collectionSlug' => 'tasks', 'record' => $first->uuid]);

    $page->fillForm(['title' => 'First task'])->call('save')->assertHasNoFormErrors();
    $page->fillForm(['title' => 'Second task'])->call('save')->assertHasFormErrors(['title']);
});

it('only compares against records of the same tenant', function () {
    $other = StudioCollection::factory()->create(['name' => 'tasks', 'slug' => 'tasks-2', 'tenant_id' => 99]);
    $otherField = StudioField::factory()->create([
        'collection_id' => $other->id, 'column_name' => 'title', 'field_type' => 'text', 'eav_cast' => 'text', 'is_unique' => true,
    ]);
    EavQueryBuilder::for($other)->create(['title' => 'Shared']);

    Livewire::test(CreateCollectionRecord::class, ['collectionSlug' => 'tasks'])
        ->fillForm(['title' => 'Shared'])
        ->call('create')
        ->assertHasNoFormErrors();
});
