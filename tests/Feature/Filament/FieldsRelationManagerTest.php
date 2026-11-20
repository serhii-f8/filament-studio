<?php

use Flexpik\FilamentStudio\Models\StudioCollection;
use Flexpik\FilamentStudio\Models\StudioField;
use Flexpik\FilamentStudio\Models\StudioMigrationLog;
use Flexpik\FilamentStudio\Resources\CollectionManagerResource\Pages\EditCollection;
use Flexpik\FilamentStudio\Resources\CollectionManagerResource\RelationManagers\FieldsRelationManager;
use Illuminate\Foundation\Auth\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->user = User::forceCreate(['name' => 'Test', 'email' => fake()->unique()->safeEmail(), 'password' => bcrypt('password')]);
    actingAs($this->user);
    $this->collection = StudioCollection::factory()->create();
});

// --- Task 6: Table & Reorder ---

it('can render the relation manager', function () {
    Livewire::test(FieldsRelationManager::class, [
        'ownerRecord' => $this->collection,
        'pageClass' => EditCollection::class,
    ])
        ->assertSuccessful();
});

it('can list fields for a collection', function () {
    $fields = StudioField::factory()
        ->count(3)
        ->sequence(
            ['column_name' => 'title', 'sort_order' => 1],
            ['column_name' => 'body', 'sort_order' => 2],
            ['column_name' => 'status', 'sort_order' => 3],
        )
        ->create(['collection_id' => $this->collection->id]);

    Livewire::test(FieldsRelationManager::class, [
        'ownerRecord' => $this->collection,
        'pageClass' => EditCollection::class,
    ])
        ->assertCanSeeTableRecords($fields);
});

it('displays expected field columns', function () {
    StudioField::factory()->create([
        'collection_id' => $this->collection->id,
        'column_name' => 'title',
        'label' => 'Title',
        'field_type' => 'text',
        'eav_cast' => 'text',
        'is_required' => true,
        'sort_order' => 0,
    ]);

    Livewire::test(FieldsRelationManager::class, [
        'ownerRecord' => $this->collection,
        'pageClass' => EditCollection::class,
    ])
        ->assertCanRenderTableColumn('column_name')
        ->assertCanRenderTableColumn('label')
        ->assertCanRenderTableColumn('field_type')
        ->assertCanRenderTableColumn('eav_cast')
        ->assertCanRenderTableColumn('is_required')
        ->assertCanRenderTableColumn('sort_order');
});

it('orders fields by sort_order', function () {
    $fieldB = StudioField::factory()->create([
        'collection_id' => $this->collection->id,
        'column_name' => 'b_field',
        'sort_order' => 2,
    ]);
    $fieldA = StudioField::factory()->create([
        'collection_id' => $this->collection->id,
        'column_name' => 'a_field',
        'sort_order' => 1,
    ]);

    Livewire::test(FieldsRelationManager::class, [
        'ownerRecord' => $this->collection,
        'pageClass' => EditCollection::class,
    ])
        ->assertCanSeeTableRecords([$fieldA, $fieldB], inOrder: true);
});

// --- Task 7: Create, Edit, Delete Actions ---

it('can create a field via the relation manager', function () {
    Livewire::test(FieldsRelationManager::class, [
        'ownerRecord' => $this->collection,
        'pageClass' => EditCollection::class,
    ])
        ->callTableAction('create', data: [
            'field_type' => 'text',
            'column_name' => 'title',
            'label' => 'Title',
            'eav_cast' => 'text',
            'is_required' => true,
            'width' => 'full',
        ])
        ->assertHasNoTableActionErrors();

    $field = StudioField::where('collection_id', $this->collection->id)
        ->where('column_name', 'title')
        ->first();

    expect($field)
        ->not->toBeNull()
        ->label->toBe('Title')
        ->field_type->toBe('text')
        ->is_required->toBeTrue();
});

it('logs add_field when creating a field', function () {
    Livewire::test(FieldsRelationManager::class, [
        'ownerRecord' => $this->collection,
        'pageClass' => EditCollection::class,
    ])
        ->callTableAction('create', data: [
            'field_type' => 'text',
            'column_name' => 'name',
            'label' => 'Name',
            'eav_cast' => 'text',
        ]);

    $log = StudioMigrationLog::where('collection_id', $this->collection->id)
        ->where('operation', 'add_field')
        ->first();

    expect($log)
        ->not->toBeNull()
        ->after_state->not->toBeNull();
});

it('can edit a field via the relation manager', function () {
    $field = StudioField::factory()->create([
        'collection_id' => $this->collection->id,
        'column_name' => 'title',
        'label' => 'Title',
        'field_type' => 'text',
    ]);

    Livewire::test(FieldsRelationManager::class, [
        'ownerRecord' => $this->collection,
        'pageClass' => EditCollection::class,
    ])
        ->callTableAction('edit', $field, data: [
            'label' => 'Updated Title',
        ])
        ->assertHasNoTableActionErrors();

    $field->refresh();
    expect($field->label)->toBe('Updated Title');
});

it('logs update_field when editing a field', function () {
    $field = StudioField::factory()->create([
        'collection_id' => $this->collection->id,
        'column_name' => 'title',
        'label' => 'Original',
        'field_type' => 'text',
    ]);

    Livewire::test(FieldsRelationManager::class, [
        'ownerRecord' => $this->collection,
        'pageClass' => EditCollection::class,
    ])
        ->callTableAction('edit', $field, data: [
            'label' => 'Changed',
        ]);

    $log = StudioMigrationLog::where('collection_id', $this->collection->id)
        ->where('operation', 'update_field')
        ->first();

    expect($log)
        ->not->toBeNull()
        ->before_state->not->toBeNull()
        ->after_state->not->toBeNull();
});

it('can delete a field via the relation manager', function () {
    $field = StudioField::factory()->create([
        'collection_id' => $this->collection->id,
        'column_name' => 'to_delete',
    ]);

    Livewire::test(FieldsRelationManager::class, [
        'ownerRecord' => $this->collection,
        'pageClass' => EditCollection::class,
    ])
        ->callTableAction('delete', $field);

    expect(StudioField::find($field->id))->toBeNull();
});

it('logs delete_field when deleting a field', function () {
    $field = StudioField::factory()->create([
        'collection_id' => $this->collection->id,
        'column_name' => 'to_delete',
    ]);

    Livewire::test(FieldsRelationManager::class, [
        'ownerRecord' => $this->collection,
        'pageClass' => EditCollection::class,
    ])
        ->callTableAction('delete', $field);

    $log = StudioMigrationLog::where('collection_id', $this->collection->id)
        ->where('operation', 'delete_field')
        ->first();

    expect($log)
        ->not->toBeNull()
        ->before_state->not->toBeNull();
});

// --- Type-specific settings & static options (issue #11) ---

function fieldsManager(StudioCollection $collection)
{
    return Livewire::test(FieldsRelationManager::class, [
        'ownerRecord' => $collection,
        'pageClass' => EditCollection::class,
    ]);
}

it('persists type-specific settings when a field is created in the editor', function () {
    fieldsManager($this->collection)
        ->mountTableAction('create')
        ->setTableActionData(['field_type' => 'select'])
        ->setTableActionData(['column_name' => 'colour', 'label' => 'Colour', 'settings.options_source' => 'static', 'settings.searchable' => true])
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    $settings = StudioField::where('column_name', 'colour')->firstOrFail()->settings;

    expect($settings['options_source'])->toBe('static')
        ->and($settings['searchable'])->toBeTrue();
});

it('keeps existing type-specific settings when a field is edited', function () {
    $field = StudioField::factory()->create([
        'collection_id' => $this->collection->id,
        'column_name' => 'author',
        'label' => 'Author',
        'field_type' => 'belongs_to',
        'eav_cast' => 'text',
        'settings' => ['related_collection' => 'authors', 'display_field' => 'full_name'],
    ]);

    fieldsManager($this->collection)
        ->mountTableAction('edit', $field)
        ->assertTableActionDataSet(['settings.related_collection' => 'authors', 'settings.display_field' => 'full_name'])
        ->setTableActionData(['label' => 'Written by'])
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    $field->refresh();
    expect($field->label)->toBe('Written by')
        ->and($field->settings['related_collection'])->toBe('authors')
        ->and($field->settings['display_field'])->toBe('full_name');
});

it('lets the editor define static options for a select field', function () {
    fieldsManager($this->collection)
        ->mountTableAction('create')
        ->setTableActionData(['field_type' => 'select'])
        ->setTableActionData([
            'column_name' => 'priority',
            'label' => 'Priority',
            'settings.options_source' => 'static',
            'options' => [
                ['value' => 'low', 'label' => 'Low'],
                ['value' => 'high', 'label' => 'High'],
            ],
        ])
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    $field = StudioField::where('column_name', 'priority')->firstOrFail();

    expect($field->options()->pluck('label', 'value')->all())->toBe(['low' => 'Low', 'high' => 'High'])
        ->and($field->options()->pluck('tenant_id')->unique()->all())->toBe([$this->collection->tenant_id]);
});

it('shows and updates existing options when editing a select field', function () {
    $field = StudioField::factory()->create([
        'collection_id' => $this->collection->id,
        'column_name' => 'status',
        'label' => 'Status',
        'field_type' => 'select',
        'eav_cast' => 'text',
        'settings' => ['options_source' => 'static'],
    ]);
    foreach (['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'] as $i => $label) {
        $field->options()->create(['value' => $i, 'label' => $label, 'sort_order' => count($field->options()->get())]);
    }

    $component = fieldsManager($this->collection)->mountTableAction('edit', $field);

    $options = collect($component->get('mountedActions.0.data.options'))->values();
    expect($options->pluck('label', 'value')->all())->toBe(['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived']);

    $component
        ->setTableActionData(['options' => [
            ['value' => 'draft', 'label' => 'Draft'],
            ['value' => 'live', 'label' => 'Live'],
        ]])
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    expect($field->options()->pluck('label', 'value')->all())->toBe(['draft' => 'Draft', 'live' => 'Live']);
});

it('fills settings a field never stored with the type defaults instead of switching them off', function () {
    $field = StudioField::factory()->create([
        'collection_id' => $this->collection->id,
        'column_name' => 'author',
        'label' => 'Author',
        'field_type' => 'belongs_to',
        'eav_cast' => 'text',
        'settings' => ['related_collection' => 'authors'],
    ]);

    fieldsManager($this->collection)
        ->mountTableAction('edit', $field)
        ->assertTableActionDataSet(['settings.searchable' => true, 'settings.preload' => true])
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    expect($field->refresh()->settings)
        ->toMatchArray(['related_collection' => 'authors', 'searchable' => true, 'preload' => true]);
});

it('makes new fields filterable by default and lets the editor turn it off', function () {
    $component = Livewire::test(FieldsRelationManager::class, [
        'ownerRecord' => $this->collection,
        'pageClass' => EditCollection::class,
    ]);

    $component->mountTableAction('create')
        ->assertTableActionDataSet(['is_filterable' => true])
        ->setTableActionData(['field_type' => 'text', 'column_name' => 'city', 'label' => 'City', 'width' => 'full'])
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    $field = StudioField::where('collection_id', $this->collection->id)->where('column_name', 'city')->first();
    expect($field->is_filterable)->toBeTrue();

    $component->callTableAction('edit', $field, data: ['is_filterable' => false])->assertHasNoTableActionErrors();
    expect($field->fresh()->is_filterable)->toBeFalse();
});
