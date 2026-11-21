<?php

use Flexpik\FilamentStudio\Models\StudioCollection;
use Flexpik\FilamentStudio\Models\StudioField;
use Flexpik\FilamentStudio\Resources\DynamicCollectionResource;
use Flexpik\FilamentStudio\Resources\DynamicCollectionResource\Pages\ListCollectionRecords;
use Flexpik\FilamentStudio\Resources\DynamicCollectionResource\Pages\ViewCollectionRecord;
use Flexpik\FilamentStudio\Services\EavQueryBuilder;
use Illuminate\Foundation\Auth\User;
use Illuminate\Routing\Route;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    DynamicCollectionResource::resetResolvedCollection();
    actingAs(User::forceCreate(['name' => 'Admin', 'email' => fake()->unique()->safeEmail(), 'password' => bcrypt('password')]));

    $this->collection = StudioCollection::factory()->create(['name' => 'products', 'slug' => 'products', 'tenant_id' => null]);
    StudioField::factory()->create(['collection_id' => $this->collection->id, 'column_name' => 'name', 'label' => 'Name', 'field_type' => 'text', 'eav_cast' => 'text', 'sort_order' => 1]);
    StudioField::factory()->create(['collection_id' => $this->collection->id, 'column_name' => 'price', 'label' => 'Price', 'field_type' => 'decimal', 'eav_cast' => 'decimal', 'sort_order' => 2, 'settings' => ['scale' => 6, 'prefix' => '$']]);
    StudioField::factory()->create(['collection_id' => $this->collection->id, 'column_name' => 'ratio', 'label' => 'Ratio', 'field_type' => 'decimal', 'eav_cast' => 'decimal', 'sort_order' => 3, 'settings' => ['scale' => 2]]);

    $route = new Route('GET', 'admin/studio/{collection_slug}', []);
    $route->bind(request());
    $route->setParameter('collection_slug', 'products');
    request()->setRouteResolver(fn () => $route);
});

it('shows decimals without storage-scale padding in the record list', function () {
    EavQueryBuilder::for($this->collection)->create(['name' => 'Pro', 'price' => 99, 'ratio' => 0.5]);
    EavQueryBuilder::for($this->collection)->create(['name' => 'Kit', 'price' => 12.345678, 'ratio' => 1.239]);

    Livewire::test(ListCollectionRecords::class, ['collectionSlug' => 'products'])
        ->assertSee('$99')
        ->assertDontSee('99.000000')
        ->assertDontSee('99.00')
        ->assertSee('0.5')
        ->assertDontSee('0.50')
        ->assertSee('$12.345678')
        // Never more places than the field's scale.
        ->assertSee('1.24');
});

it('shows decimals the same way on the record view page', function () {
    $record = EavQueryBuilder::for($this->collection)->create(['name' => 'Pro', 'price' => 99, 'ratio' => 0.5]);

    Livewire::test(ViewCollectionRecord::class, ['collectionSlug' => 'products', 'record' => $record->uuid])
        ->assertSee('$99')
        ->assertDontSee('99.000000');
});
