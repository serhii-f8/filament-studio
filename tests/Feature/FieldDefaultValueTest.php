<?php

use Flexpik\FilamentStudio\Models\StudioCollection;
use Flexpik\FilamentStudio\Models\StudioField;
use Flexpik\FilamentStudio\Models\StudioFieldOption;
use Flexpik\FilamentStudio\Models\StudioRecord;
use Flexpik\FilamentStudio\Models\StudioValue;
use Flexpik\FilamentStudio\Resources\CollectionManagerResource\Pages\CreateCollection;
use Flexpik\FilamentStudio\Resources\DynamicCollectionResource;
use Flexpik\FilamentStudio\Resources\DynamicCollectionResource\Pages\CreateCollectionRecord;
use Flexpik\FilamentStudio\Resources\DynamicCollectionResource\Pages\EditCollectionRecord;
use Flexpik\FilamentStudio\Services\EavQueryBuilder;
use Illuminate\Foundation\Auth\User;
use Illuminate\Routing\Route;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

function bindDefaultValueTestSlug(string $slug): void
{
    $route = new Route('GET', 'admin/studio/{collection_slug}', []);
    $route->bind(request());
    $route->setParameter('collection_slug', $slug);
    request()->setRouteResolver(fn () => $route);
}

beforeEach(function () {
    DynamicCollectionResource::resetResolvedCollection();
    $this->user = User::forceCreate(['name' => 'Test', 'email' => fake()->unique()->safeEmail(), 'password' => bcrypt('password')]);
    actingAs($this->user);
});

function makePostsWithStatus(): array
{
    $collection = StudioCollection::factory()->create([
        'name' => 'posts', 'label' => 'Post', 'label_plural' => 'Posts', 'slug' => 'posts',
    ]);

    StudioField::factory()->required()->create([
        'collection_id' => $collection->id, 'column_name' => 'title', 'label' => 'Title',
        'field_type' => 'text', 'eav_cast' => 'text', 'sort_order' => 1,
    ]);

    $status = StudioField::factory()->required()->create([
        'collection_id' => $collection->id, 'column_name' => 'status', 'label' => 'Status',
        'field_type' => 'select', 'eav_cast' => 'text', 'sort_order' => 2, 'is_system' => true,
        'settings' => ['options_source' => 'static'], 'default_value' => 'draft',
    ]);

    foreach (['draft' => 'Draft', 'published' => 'Published'] as $value => $label) {
        StudioFieldOption::create(['field_id' => $status->id, 'tenant_id' => $collection->tenant_id, 'value' => $value, 'label' => $label]);
    }

    return [$collection, $status];
}

it('gives the wizard status system field a draft default', function () {
    Livewire::test(CreateCollection::class)
        ->fillForm(['name' => 'posts', 'label' => 'Post', 'label_plural' => 'Posts', 'system_fields' => ['status']])
        ->call('create')
        ->assertHasNoFormErrors();

    $status = StudioField::where('column_name', 'status')
        ->where('collection_id', StudioCollection::where('name', 'posts')->value('id'))
        ->first();

    expect($status->default_value)->toBe('draft')
        ->and($status->is_required)->toBeTrue();
});

it('pre-fills the field default on the create record form', function () {
    makePostsWithStatus();
    bindDefaultValueTestSlug('posts');

    Livewire::test(CreateCollectionRecord::class, ['collectionSlug' => 'posts'])
        ->assertFormSet(['status' => 'draft'])
        ->fillForm(['title' => 'Hello'])
        ->call('create')
        ->assertHasNoFormErrors();

    $record = StudioRecord::latest('id')->first();
    $data = EavQueryBuilder::for(StudioCollection::where('slug', 'posts')->first())->getRecordData($record);

    expect($data['status'])->toBe('draft');
});

it('does not overwrite an existing value with the default on the edit form', function () {
    [$collection] = makePostsWithStatus();
    bindDefaultValueTestSlug('posts');

    $record = EavQueryBuilder::for($collection)->create(['title' => 'Hi', 'status' => 'published']);

    Livewire::test(EditCollectionRecord::class, ['collectionSlug' => 'posts', 'record' => $record->uuid])
        ->assertFormSet(['status' => 'published']);
});

it('applies field defaults when creating records through EavQueryBuilder', function () {
    [$collection, $status] = makePostsWithStatus();

    $record = EavQueryBuilder::for($collection)->create(['title' => 'No status given']);

    expect(StudioValue::where('record_id', $record->id)->where('field_id', $status->id)->value('val_text'))
        ->toBe('draft');
});

it('keeps an explicitly given value over the default', function () {
    [$collection, $status] = makePostsWithStatus();

    $record = EavQueryBuilder::for($collection)->create(['title' => 'Given', 'status' => 'published']);

    expect(StudioValue::where('record_id', $record->id)->where('field_id', $status->id)->value('val_text'))
        ->toBe('published');
});

it('casts typed defaults to the field storage type', function () {
    $collection = StudioCollection::factory()->create();
    $qty = StudioField::factory()->create([
        'collection_id' => $collection->id, 'column_name' => 'qty',
        'field_type' => 'integer', 'eav_cast' => 'integer', 'default_value' => '5',
    ]);
    $flag = StudioField::factory()->create([
        'collection_id' => $collection->id, 'column_name' => 'flag',
        'field_type' => 'toggle', 'eav_cast' => 'boolean', 'default_value' => 'false',
    ]);

    $record = EavQueryBuilder::for($collection)->create([]);

    expect(StudioValue::where('record_id', $record->id)->where('field_id', $qty->id)->value('val_integer'))->toBe(5)
        ->and((bool) StudioValue::where('record_id', $record->id)->where('field_id', $flag->id)->value('val_boolean'))->toBeFalse();
});
