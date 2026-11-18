<?php

use Flexpik\FilamentStudio\Models\StudioCollection;
use Flexpik\FilamentStudio\Models\StudioField;
use Flexpik\FilamentStudio\Models\StudioRecord;
use Flexpik\FilamentStudio\Models\StudioValue;
use Flexpik\FilamentStudio\Resources\DynamicCollectionResource;
use Flexpik\FilamentStudio\Resources\DynamicCollectionResource\Pages\CreateCollectionRecord;
use Flexpik\FilamentStudio\Resources\DynamicCollectionResource\Pages\EditCollectionRecord;
use Flexpik\FilamentStudio\Resources\DynamicCollectionResource\Pages\ListCollectionRecords;
use Illuminate\Foundation\Auth\User;
use Illuminate\Routing\Route;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    DynamicCollectionResource::resetResolvedCollection();

    $this->user = User::forceCreate(['name' => 'Ada Editor', 'email' => 'ada@example.com', 'password' => bcrypt('password')]);
    actingAs($this->user);

    $this->collection = StudioCollection::factory()->create(['name' => 'pages', 'slug' => 'pages', 'label' => 'Page', 'label_plural' => 'Pages']);

    $make = fn (string $column, string $type, string $cast, int $sort, ?array $on = null, ?string $value = null) => StudioField::factory()->create([
        'collection_id' => $this->collection->id,
        'column_name' => $column,
        'label' => ucfirst(str_replace('_', ' ', $column)),
        'field_type' => $type,
        'eav_cast' => $cast,
        'is_system' => $on !== null,
        'is_hidden_in_table' => false,
        'sort_order' => $sort,
        'auto_fill_on' => $on,
        'auto_fill_value' => $value,
    ]);

    $this->fields = [
        'title' => $make('title', 'text', 'text', 0),
        'created_at' => $make('created_at', 'datetime', 'datetime', 1, ['create'], '$NOW'),
        'updated_at' => $make('updated_at', 'datetime', 'datetime', 2, ['create', 'update'], '$NOW'),
        'created_by' => $make('created_by', 'belongs_to', 'integer', 3, ['create'], '$CURRENT_USER'),
        'updated_by' => $make('updated_by', 'belongs_to', 'integer', 4, ['create', 'update'], '$CURRENT_USER'),
    ];

    $route = new Route('GET', 'admin/studio/{collection_slug}', []);
    $route->bind(request());
    $route->setParameter('collection_slug', 'pages');
    request()->setRouteResolver(fn () => $route);
});

afterEach(fn () => Carbon::setTestNow());

it('shows auto-filled system fields as read-only on the create form', function () {
    Livewire::test(CreateCollectionRecord::class, ['collectionSlug' => 'pages'])
        ->assertFormFieldEnabled('title')
        ->assertFormFieldDisabled('created_at')
        ->assertFormFieldDisabled('updated_at')
        ->assertFormFieldDisabled('created_by')
        ->assertFormFieldDisabled('updated_by');
});

it('fills timestamps and the acting user when a record is created from the form', function () {
    Carbon::setTestNow('2026-11-18 10:00:00');

    Livewire::test(CreateCollectionRecord::class, ['collectionSlug' => 'pages'])
        ->fillForm(['title' => 'Hello'])
        ->call('create')
        ->assertHasNoFormErrors();

    $record = StudioRecord::where('collection_id', $this->collection->id)->firstOrFail();
    $value = fn (string $column) => StudioValue::where('record_id', $record->id)
        ->where('field_id', $this->fields[$column]->id)
        ->value($this->fields[$column]->eavColumn());

    expect((string) $value('created_at'))->toStartWith('2026-11-18 10:00:00')
        ->and((string) $value('updated_at'))->toStartWith('2026-11-18 10:00:00')
        ->and((int) $value('created_by'))->toBe($this->user->id)
        ->and((int) $value('updated_by'))->toBe($this->user->id);

    Livewire::test(ListCollectionRecords::class, ['collectionSlug' => 'pages'])
        ->assertSee('Ada Editor');

    Livewire::test(EditCollectionRecord::class, ['collectionSlug' => 'pages', 'record' => $record->uuid])
        ->assertSee('Ada Editor');
});

it('refreshes updated fields and keeps created fields when the record is edited', function () {
    Carbon::setTestNow('2026-11-18 10:00:00');
    Livewire::test(CreateCollectionRecord::class, ['collectionSlug' => 'pages'])
        ->fillForm(['title' => 'Hello'])
        ->call('create');
    $record = StudioRecord::where('collection_id', $this->collection->id)->firstOrFail();

    $other = User::forceCreate(['name' => 'Bo Reviewer', 'email' => 'bo@example.com', 'password' => bcrypt('password')]);
    actingAs($other);
    Carbon::setTestNow('2026-11-19 12:30:00');

    Livewire::test(EditCollectionRecord::class, ['collectionSlug' => 'pages', 'record' => $record->uuid])
        ->fillForm(['title' => 'Edited'])
        ->call('save')
        ->assertHasNoFormErrors();

    $value = fn (string $column) => StudioValue::where('record_id', $record->id)
        ->where('field_id', $this->fields[$column]->id)
        ->value($this->fields[$column]->eavColumn());

    expect((string) $value('created_at'))->toStartWith('2026-11-18 10:00:00')
        ->and((int) $value('created_by'))->toBe($this->user->id)
        ->and((string) $value('updated_at'))->toStartWith('2026-11-19 12:30:00')
        ->and((int) $value('updated_by'))->toBe($other->id);
});
