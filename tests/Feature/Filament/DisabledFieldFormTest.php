<?php

use Flexpik\FilamentStudio\Models\StudioCollection;
use Flexpik\FilamentStudio\Models\StudioField;
use Flexpik\FilamentStudio\Models\StudioRecord;
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

    $this->collection = StudioCollection::factory()->create(['name' => 'tickets', 'slug' => 'tickets']);
    StudioField::factory()->create([
        'collection_id' => $this->collection->id, 'column_name' => 'title', 'label' => 'Title',
        'field_type' => 'text', 'eav_cast' => 'text',
    ]);
    StudioField::factory()->create([
        'collection_id' => $this->collection->id, 'column_name' => 'code', 'label' => 'Code',
        'field_type' => 'text', 'eav_cast' => 'text',
        'is_disabled_on_create' => false, 'is_disabled_on_edit' => true,
    ]);

    $route = new Route('GET', 'admin/studio/{collection_slug}', []);
    $route->bind(request());
    $route->setParameter('collection_slug', 'tickets');
    request()->setRouteResolver(fn () => $route);
});

it('lets a field disabled only on edit be filled on create', function () {
    Livewire::test(CreateCollectionRecord::class, ['collectionSlug' => 'tickets'])
        ->assertFormFieldIsEnabled('code')
        ->fillForm(['title' => 'First', 'code' => 'T-1'])
        ->call('create')
        ->assertHasNoFormErrors();

    $data = EavQueryBuilder::for($this->collection)->getRecordData(StudioRecord::latest('id')->first());

    expect($data['code'])->toBe('T-1');
});

it('shows a field disabled on edit read-only and keeps its value when the record is saved', function () {
    $record = EavQueryBuilder::for($this->collection)->create(['title' => 'First', 'code' => 'T-1']);

    Livewire::test(EditCollectionRecord::class, ['collectionSlug' => 'tickets', 'record' => $record->uuid])
        ->assertFormFieldIsDisabled('code')
        ->assertFormSet(['code' => 'T-1'])
        ->fillForm(['title' => 'Renamed'])
        ->call('save')
        ->assertHasNoFormErrors();

    $data = EavQueryBuilder::for($this->collection)->getRecordData($record->fresh());

    expect($data['title'])->toBe('Renamed')
        ->and($data['code'])->toBe('T-1');
});
