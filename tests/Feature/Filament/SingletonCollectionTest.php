<?php

use Flexpik\FilamentStudio\Api\StudioApiRouteRegistrar;
use Flexpik\FilamentStudio\Models\StudioApiKey;
use Flexpik\FilamentStudio\Models\StudioCollection;
use Flexpik\FilamentStudio\Models\StudioField;
use Flexpik\FilamentStudio\Models\StudioRecord;
use Flexpik\FilamentStudio\Resources\DynamicCollectionResource;
use Flexpik\FilamentStudio\Resources\DynamicCollectionResource\Pages\CreateCollectionRecord;
use Flexpik\FilamentStudio\Resources\DynamicCollectionResource\Pages\ListCollectionRecords;
use Flexpik\FilamentStudio\Services\EavQueryBuilder;
use Illuminate\Foundation\Auth\User;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    DynamicCollectionResource::resetResolvedCollection();
    actingAs(User::forceCreate(['name' => 'Test', 'email' => fake()->unique()->safeEmail(), 'password' => bcrypt('password')]));

    $this->collection = StudioCollection::factory()->apiEnabled()->create([
        'name' => 'settings', 'slug' => 'settings', 'is_singleton' => true,
    ]);
    StudioField::factory()->create([
        'collection_id' => $this->collection->id, 'column_name' => 'site_name', 'label' => 'Site name',
        'field_type' => 'text', 'eav_cast' => 'text',
    ]);

    $route = new Route('GET', 'admin/studio/{collection_slug}', []);
    $route->bind(request());
    $route->setParameter('collection_slug', 'settings');
    request()->setRouteResolver(fn () => $route);
});

it('opens the create form from the list while the singleton has no record', function () {
    Livewire::test(ListCollectionRecords::class, ['collectionSlug' => 'settings'])
        ->assertRedirect(DynamicCollectionResource::getUrl('create', ['collection_slug' => 'settings']));
});

it('opens the single record from the list once it exists', function () {
    $record = EavQueryBuilder::for($this->collection)->create(['site_name' => 'Acme']);

    Livewire::test(ListCollectionRecords::class, ['collectionSlug' => 'settings'])
        ->assertRedirect(DynamicCollectionResource::getUrl('edit', ['collection_slug' => 'settings', 'record' => $record]));
});

it('sends the create page to the existing record instead of making a second one', function () {
    $record = EavQueryBuilder::for($this->collection)->create(['site_name' => 'Acme']);

    Livewire::test(CreateCollectionRecord::class, ['collectionSlug' => 'settings'])
        ->assertRedirect(DynamicCollectionResource::getUrl('edit', ['collection_slug' => 'settings', 'record' => $record]));
});

it('refuses a second record through the REST API', function () {
    StudioApiRouteRegistrar::register();
    $key = Str::random(64);
    StudioApiKey::factory()->fullAccess()->create(['key' => hash('sha256', $key)]);
    $headers = ['X-Api-Key' => $key, 'Accept' => 'application/json'];

    $this->postJson('/api/studio/settings', ['data' => ['site_name' => 'One']], $headers)->assertCreated();
    $this->postJson('/api/studio/settings', ['data' => ['site_name' => 'Two']], $headers)->assertStatus(422);

    expect(StudioRecord::where('collection_id', $this->collection->id)->count())->toBe(1);
});
