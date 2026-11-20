<?php

use Filament\Infolists\Components\Entry;
use Flexpik\FilamentStudio\FieldTypes\FieldTypeRegistry;
use Flexpik\FilamentStudio\Models\StudioCollection;
use Flexpik\FilamentStudio\Models\StudioField;
use Flexpik\FilamentStudio\Models\StudioFieldOption;
use Flexpik\FilamentStudio\Resources\DynamicCollectionResource;
use Flexpik\FilamentStudio\Resources\DynamicCollectionResource\Pages\EditCollectionRecord;
use Flexpik\FilamentStudio\Resources\DynamicCollectionResource\Pages\ViewCollectionRecord;
use Flexpik\FilamentStudio\Services\EavQueryBuilder;
use Illuminate\Foundation\Auth\User;
use Illuminate\Routing\Route;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

function viewPageField(StudioCollection $collection, string $column, string $type, string $cast, int $sort, array $extra = []): StudioField
{
    return StudioField::factory()->create(array_merge([
        'collection_id' => $collection->id, 'column_name' => $column, 'label' => ucfirst(str_replace('_', ' ', $column)),
        'field_type' => $type, 'eav_cast' => $cast, 'sort_order' => $sort,
    ], $extra));
}

beforeEach(function () {
    DynamicCollectionResource::resetResolvedCollection();
    actingAs(User::forceCreate(['name' => 'Viewer', 'email' => fake()->unique()->safeEmail(), 'password' => bcrypt('password')]));

    $this->collection = StudioCollection::factory()->create(['name' => 'tickets', 'slug' => 'tickets', 'label' => 'Ticket', 'tenant_id' => null]);
    viewPageField($this->collection, 'subject', 'text', 'text', 1);
    $status = viewPageField($this->collection, 'status', 'select', 'text', 2, ['settings' => ['options_source' => 'static']]);
    StudioFieldOption::create(['field_id' => $status->id, 'value' => 'open', 'label' => 'Open for triage', 'color' => 'success']);
    viewPageField($this->collection, 'urgent', 'toggle', 'boolean', 3);
    viewPageField($this->collection, 'secret', 'password', 'text', 4);
    viewPageField($this->collection, 'delivery', 'select', 'text', 5);
    viewPageField($this->collection, 'address', 'text', 'text', 6, ['settings' => ['conditions' => ['visible' => [
        'logic' => 'and',
        'rules' => [['type' => 'field_value', 'field' => 'delivery', 'op' => 'equals', 'value' => 'ship']],
    ]]]]);

    $route = new Route('GET', 'admin/studio/{collection_slug}', []);
    $route->bind(request());
    $route->setParameter('collection_slug', 'tickets');
    request()->setRouteResolver(fn () => $route);
});

it('shows the record as labelled values instead of a disabled form', function () {
    $record = EavQueryBuilder::for($this->collection)->create([
        'subject' => 'Printer on fire', 'status' => 'open', 'urgent' => true, 'secret' => 'hunter2', 'delivery' => 'pickup', 'address' => '1 Main St',
    ]);

    $page = Livewire::test(ViewCollectionRecord::class, ['collectionSlug' => 'tickets', 'record' => $record->uuid])
        ->assertSuccessful()
        ->assertSee('Printer on fire')
        ->assertSee('Open for triage')
        ->assertDontSee('hunter2')
        ->assertDontSee('1 Main St');

    expect($page->instance()->getSchema('infolist')->getComponents())->not->toBeEmpty()
        ->and($page->html())->not->toContain('disabled="disabled"');
});

it('shows a conditionally visible field when its condition holds', function () {
    $record = EavQueryBuilder::for($this->collection)->create(['subject' => 'Parcel', 'delivery' => 'ship', 'address' => '1 Main St']);

    Livewire::test(ViewCollectionRecord::class, ['collectionSlug' => 'tickets', 'record' => $record->uuid])
        ->assertSee('1 Main St');
});

it('uses the record title as the view and edit headings', function () {
    $record = EavQueryBuilder::for($this->collection)->create(['subject' => 'Printer on fire']);

    expect(Livewire::test(ViewCollectionRecord::class, ['collectionSlug' => 'tickets', 'record' => $record->uuid])->instance()->getTitle())
        ->toBe('Printer on fire')
        ->and(Livewire::test(EditCollectionRecord::class, ['collectionSlug' => 'tickets', 'record' => $record->uuid])->instance()->getTitle())
        ->toBe('Edit Printer on fire');
});

it('prefers the collection display template for the heading', function () {
    $this->collection->update(['display_template' => '{{subject}} ({{status}})']);
    DynamicCollectionResource::resetResolvedCollection();
    $record = EavQueryBuilder::for($this->collection)->create(['subject' => 'Printer', 'status' => 'open']);

    expect(Livewire::test(ViewCollectionRecord::class, ['collectionSlug' => 'tickets', 'record' => $record->uuid])->instance()->getTitle())
        ->toBe('Printer (open)');
});

it('falls back to the collection label when the record has no title value', function () {
    $record = EavQueryBuilder::for($this->collection)->create(['urgent' => false]);

    expect(Livewire::test(ViewCollectionRecord::class, ['collectionSlug' => 'tickets', 'record' => $record->uuid])->instance()->getTitle())
        ->toBe('Ticket');
});

it('renders a view entry for every built-in field type without errors', function () {
    $collection = StudioCollection::factory()->create(['name' => 'everything', 'slug' => 'everything', 'tenant_id' => null]);
    $registry = app(FieldTypeRegistry::class);
    $sort = 0;

    foreach ($registry->all() as $key => $class) {
        $field = viewPageField($collection, "f_{$key}", $key, $class::$eavCast->value, ++$sort);
        $entry = $registry->make($field)->buildInfolistEntry();

        expect($entry === null || $entry instanceof Entry)->toBeTrue("{$key} returned an unexpected entry");
    }

    $route = new Route('GET', 'admin/studio/{collection_slug}', []);
    $route->bind(request());
    $route->setParameter('collection_slug', 'everything');
    request()->setRouteResolver(fn () => $route);
    DynamicCollectionResource::resetResolvedCollection();

    $record = EavQueryBuilder::for($collection)->create([
        'f_text' => 'Hello', 'f_toggle' => true, 'f_tags' => ['a', 'b'], 'f_key_value' => ['k' => 'v'],
        'f_repeater' => [['name' => 'Ada']], 'f_date' => '2026-01-02', 'f_color' => '#ff0000', 'f_rich_editor' => '<p>Rich <script>alert(1)</script></p>',
    ]);

    Livewire::test(ViewCollectionRecord::class, ['collectionSlug' => 'everything', 'record' => $record->uuid])
        ->assertSuccessful()
        ->assertSee('Hello')
        ->assertSee('name: Ada')
        ->assertDontSee('<script>alert(1)</script>', escape: false);
});
