<?php

use Flexpik\FilamentStudio\Enums\SortDirection;
use Flexpik\FilamentStudio\Models\StudioCollection;
use Flexpik\FilamentStudio\Models\StudioField;
use Flexpik\FilamentStudio\Models\StudioRecord;
use Flexpik\FilamentStudio\Models\StudioValue;
use Flexpik\FilamentStudio\Resources\DynamicCollectionResource;
use Flexpik\FilamentStudio\Resources\DynamicCollectionResource\Pages\CreateCollectionRecord;
use Flexpik\FilamentStudio\Resources\DynamicCollectionResource\Pages\ListCollectionRecords;
use Flexpik\FilamentStudio\Services\EavQueryBuilder;
use Illuminate\Foundation\Auth\User;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    DynamicCollectionResource::resetResolvedCollection();
    EavQueryBuilder::invalidateFieldCache();

    $this->user = User::forceCreate(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => bcrypt('password')]);
    actingAs($this->user);

    $this->collection = StudioCollection::factory()->create([
        'name' => 'tasks', 'slug' => 'tasks', 'label' => 'Task', 'label_plural' => 'Tasks',
        'sort_field' => 'sort_order', 'sort_direction' => SortDirection::Asc,
    ]);

    $this->title = StudioField::factory()->create([
        'collection_id' => $this->collection->id,
        'column_name' => 'title', 'label' => 'Title',
        'field_type' => 'text', 'eav_cast' => 'text',
        'is_hidden_in_table' => false, 'sort_order' => 0,
    ]);

    $this->sortField = StudioField::factory()->create([
        'collection_id' => $this->collection->id,
        'column_name' => 'sort_order', 'label' => 'Sort Order',
        'field_type' => 'integer', 'eav_cast' => 'integer',
        'is_system' => true, 'is_required' => false,
        'is_hidden_in_table' => false, 'sort_order' => 1,
    ]);

    $route = new Route('GET', 'admin/studio/{collection_slug}', []);
    $route->bind(request());
    $route->setParameter('collection_slug', 'tasks');
    request()->setRouteResolver(fn () => $route);

    $this->sortOf = fn (StudioRecord $record) => StudioValue::where('record_id', $record->id)
        ->where('field_id', $this->sortField->id)
        ->value('val_integer');
});

it('assigns max + 1 sort order to new records created through the query builder', function () {
    $builder = EavQueryBuilder::for($this->collection);

    $a = $builder->create(['title' => 'A']);
    $b = $builder->create(['title' => 'B']);
    $c = $builder->create(['title' => 'C', 'sort_order' => 10]);
    $d = $builder->create(['title' => 'D']);
    $e = $builder->create(['title' => 'E', 'sort_order' => null]);

    expect(($this->sortOf)($a))->toBe(1)
        ->and(($this->sortOf)($b))->toBe(2)
        ->and(($this->sortOf)($c))->toBe(10)
        ->and(($this->sortOf)($d))->toBe(11)
        ->and(($this->sortOf)($e))->toBe(12);
});

it('scopes the next sort order to the collection and tenant', function () {
    $other = StudioCollection::factory()->create(['slug' => 'other']);
    $otherSort = StudioField::factory()->create([
        'collection_id' => $other->id, 'column_name' => 'sort_order',
        'field_type' => 'integer', 'eav_cast' => 'integer', 'is_system' => true,
    ]);
    EavQueryBuilder::for($other)->create(['sort_order' => 50]);

    EavQueryBuilder::for($this->collection)->tenant(7)->create(['title' => 'tenant 7', 'sort_order' => 30]);

    $record = EavQueryBuilder::for($this->collection)->create(['title' => 'no tenant']);

    expect(($this->sortOf)($record))->toBe(1);

    $tenantRecord = EavQueryBuilder::for($this->collection)->tenant(7)->create(['title' => 'tenant 7 again']);

    expect(($this->sortOf)($tenantRecord))->toBe(31);
});

it('does not add a sort order to collections without the system field', function () {
    $plain = StudioCollection::factory()->create(['slug' => 'plain']);
    StudioField::factory()->create([
        'collection_id' => $plain->id, 'column_name' => 'title',
        'field_type' => 'text', 'eav_cast' => 'text',
    ]);

    $record = EavQueryBuilder::for($plain)->create(['title' => 'x']);

    expect(StudioValue::where('record_id', $record->id)->count())->toBe(1);
});

it('assigns sort order to records created from the create form', function () {
    EavQueryBuilder::for($this->collection)->create(['title' => 'Existing', 'sort_order' => 4]);

    Livewire::test(CreateCollectionRecord::class, ['collectionSlug' => 'tasks'])
        ->fillForm(['title' => 'From form'])
        ->call('create')
        ->assertHasNoFormErrors();

    $record = StudioRecord::where('collection_id', $this->collection->id)->latest('id')->firstOrFail();

    expect(($this->sortOf)($record))->toBe(5);
});

it('makes the records table reorderable when the collection has a sort_order field', function () {
    $component = Livewire::test(ListCollectionRecords::class, ['collectionSlug' => 'tasks']);

    expect($component->instance()->getTable()->isReorderable())->toBeTrue()
        ->and($component->instance()->getTable()->getReorderColumn())->toBe('sort_order');
});

it('is not reorderable without a sort_order field', function () {
    $this->sortField->delete();
    EavQueryBuilder::invalidateFieldCache();

    $component = Livewire::test(ListCollectionRecords::class, ['collectionSlug' => 'tasks']);

    expect($component->instance()->getTable()->isReorderable())->toBeFalse();
});

it('writes the dragged order into the EAV values', function () {
    $builder = EavQueryBuilder::for($this->collection);
    $a = $builder->create(['title' => 'Alpha']);
    $b = $builder->create(['title' => 'Bravo']);
    $c = $builder->create(['title' => 'Charlie']);
    // A record created before the sort_order field existed has no value row yet.
    $d = StudioRecord::create(['uuid' => (string) str()->uuid(), 'collection_id' => $this->collection->id]);

    Livewire::test(ListCollectionRecords::class, ['collectionSlug' => 'tasks'])
        ->call('toggleTableReordering')
        ->call('reorderTable', [(string) $c->id, (string) $d->id, (string) $a->id, (string) $b->id])
        ->assertHasNoErrors();

    expect(($this->sortOf)($c))->toBe(1)
        ->and(($this->sortOf)($d))->toBe(2)
        ->and(($this->sortOf)($a))->toBe(3)
        ->and(($this->sortOf)($b))->toBe(4);

    Livewire::test(ListCollectionRecords::class, ['collectionSlug' => 'tasks'])
        ->assertSeeInOrder(['Charlie', 'Alpha', 'Bravo']);
});

it('ignores keys from other collections when reordering', function () {
    $a = EavQueryBuilder::for($this->collection)->create(['title' => 'Alpha']);

    $other = StudioCollection::factory()->create(['slug' => 'other']);
    $foreign = StudioRecord::create(['uuid' => (string) str()->uuid(), 'collection_id' => $other->id]);

    Livewire::test(ListCollectionRecords::class, ['collectionSlug' => 'tasks'])
        ->call('reorderTable', [(string) $foreign->id, (string) $a->id]);

    expect(StudioValue::where('record_id', $foreign->id)->count())->toBe(0)
        ->and(($this->sortOf)($a))->toBe(1);
});

it('sorts the records table by the collection sort field by default', function () {
    $builder = EavQueryBuilder::for($this->collection);
    $builder->create(['title' => 'Third', 'sort_order' => 3]);
    $builder->create(['title' => 'First', 'sort_order' => 1]);
    $builder->create(['title' => 'Second', 'sort_order' => 2]);

    Livewire::test(ListCollectionRecords::class, ['collectionSlug' => 'tasks'])
        ->assertSeeInOrder(['First', 'Second', 'Third']);

    $this->collection->update(['sort_direction' => SortDirection::Desc]);
    DynamicCollectionResource::resetResolvedCollection();

    Livewire::test(ListCollectionRecords::class, ['collectionSlug' => 'tasks'])
        ->assertSeeInOrder(['Third', 'Second', 'First']);
});

it('numbers a descending drag order so the top row keeps the highest sort order', function () {
    $this->collection->update(['sort_direction' => SortDirection::Desc]);
    DynamicCollectionResource::resetResolvedCollection();

    $builder = EavQueryBuilder::for($this->collection);
    $a = $builder->create(['title' => 'Alpha']);
    $b = $builder->create(['title' => 'Bravo']);
    $c = $builder->create(['title' => 'Charlie']);

    Livewire::test(ListCollectionRecords::class, ['collectionSlug' => 'tasks'])
        ->call('reorderTable', [(string) $b->id, (string) $c->id, (string) $a->id]);

    expect(($this->sortOf)($b))->toBe(3)
        ->and(($this->sortOf)($c))->toBe(2)
        ->and(($this->sortOf)($a))->toBe(1);

    Livewire::test(ListCollectionRecords::class, ['collectionSlug' => 'tasks'])
        ->assertSeeInOrder(['Bravo', 'Charlie', 'Alpha']);
});

it('does not reorder for users who cannot edit records', function () {
    $a = EavQueryBuilder::for($this->collection)->create(['title' => 'Alpha']);
    $b = EavQueryBuilder::for($this->collection)->create(['title' => 'Bravo']);

    Gate::before(fn ($user, $ability) => $ability === 'updateRecord' ? false : null);

    Livewire::test(ListCollectionRecords::class, ['collectionSlug' => 'tasks'])
        ->call('reorderTable', [(string) $b->id, (string) $a->id]);

    expect(($this->sortOf)($a))->toBe(1)
        ->and(($this->sortOf)($b))->toBe(2);
});

it('numbers records created without an explicit tenant within the collection\'s tenant', function () {
    $collection = StudioCollection::factory()->create(['tenant_id' => 5]);
    StudioField::factory()->create([
        'collection_id' => $collection->id, 'tenant_id' => 5, 'column_name' => 'sort_order',
        'field_type' => 'integer', 'eav_cast' => 'integer', 'is_system' => true,
    ]);

    $builder = fn () => EavQueryBuilder::for($collection);
    $builder()->create([]);
    $second = $builder()->create([]);

    expect($builder()->getRecordData($second)['sort_order'])->toBe(2);
});
