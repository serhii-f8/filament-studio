<?php

use Flexpik\FilamentStudio\Enums\EavCast;
use Flexpik\FilamentStudio\Enums\PanelPlacement;
use Flexpik\FilamentStudio\Models\StudioCollection;
use Flexpik\FilamentStudio\Models\StudioField;
use Flexpik\FilamentStudio\Models\StudioPanel;
use Flexpik\FilamentStudio\Models\StudioRecord;
use Flexpik\FilamentStudio\Models\StudioValue;
use Flexpik\FilamentStudio\Widgets\ListWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->collection = StudioCollection::factory()->create(['tenant_id' => 1]);

    $this->nameField = StudioField::factory()->create([
        'collection_id' => $this->collection->id,
        'tenant_id' => 1,
        'column_name' => 'name',
        'field_type' => 'text',
        'eav_cast' => EavCast::Text,
    ]);

    foreach (['Alice', 'Bob', 'Charlie'] as $name) {
        $record = StudioRecord::factory()->create([
            'collection_id' => $this->collection->id,
            'tenant_id' => 1,
        ]);
        StudioValue::create([
            'record_id' => $record->id,
            'field_id' => $this->nameField->id,
            'val_text' => $name,
        ]);
    }
});

it('creates a list widget with table', function () {
    $panel = StudioPanel::factory()->create([
        'tenant_id' => 1,
        'placement' => PanelPlacement::Dashboard,
        'panel_type' => 'list',
        'config' => [
            'collection_id' => $this->collection->id,
            'display_template' => '{{name}}',
            'sort_field' => 'name',
            'sort_direction' => 'asc',
            'limit' => 10,
        ],
    ]);

    $widget = new ListWidget;
    $widget->mount($panel);

    expect($widget)->toBeInstanceOf(ListWidget::class);
});

it('renders every placeholder of a multi-field template, sorted, with field labels as headers', function () {
    $this->nameField->update(['label' => 'Full Name']);
    $company = StudioField::factory()->create([
        'collection_id' => $this->collection->id, 'tenant_id' => 1,
        'column_name' => 'company', 'label' => 'Company', 'field_type' => 'text', 'eav_cast' => EavCast::Text,
    ]);
    foreach (StudioRecord::where('collection_id', $this->collection->id)->get() as $i => $record) {
        StudioValue::create(['record_id' => $record->id, 'field_id' => $company->id, 'val_text' => "Co{$i}"]);
    }

    $panel = StudioPanel::factory()->create([
        'tenant_id' => 1,
        'placement' => PanelPlacement::Dashboard,
        'panel_type' => 'list',
        'header_label' => 'People',
        'config' => [
            'collection_id' => $this->collection->id,
            'display_template' => '{{name}} — {{company}}',
            'sort_field' => 'name',
            'sort_direction' => 'desc',
            'limit' => 2,
        ],
    ]);

    Livewire::test(ListWidget::class, ['panel' => $panel])
        ->loadTable()
        ->assertSeeInOrder(['Full Name', 'Company', 'Charlie', 'Bob'])
        ->assertDontSee('Alice')
        ->assertDontSee('Full_name');
});

it('shows decimal values without storage-scale padding', function () {
    $price = StudioField::factory()->create([
        'collection_id' => $this->collection->id, 'tenant_id' => 1,
        'column_name' => 'price', 'label' => 'Price', 'field_type' => 'decimal', 'eav_cast' => EavCast::Decimal,
    ]);
    $record = StudioRecord::where('collection_id', $this->collection->id)->first();
    StudioValue::create(['record_id' => $record->id, 'field_id' => $price->id, 'val_decimal' => '99.000000']);

    $panel = StudioPanel::factory()->create([
        'tenant_id' => 1,
        'placement' => PanelPlacement::Dashboard,
        'panel_type' => 'list',
        'config' => ['collection_id' => $this->collection->id, 'display_template' => '{{price}}', 'limit' => 10],
    ]);

    $widget = Livewire::test(ListWidget::class, ['panel' => $panel])->loadTable()->assertSee('Price');
    $column = $widget->instance()->getTable()->getColumn('price');

    // MySQL returns DECIMAL(20,6) values padded ("99.000000"); SQLite does not, so format directly.
    expect($column->formatState('99.000000'))->toBe('99')
        ->and($column->formatState('12.500000'))->toBe('12.5');
});

it('shows the field for a single-token display template instead of falling back to the record id', function () {
    $panel = StudioPanel::factory()->create([
        'tenant_id' => 1,
        'placement' => PanelPlacement::Dashboard,
        'panel_type' => 'list',
        'config' => ['collection_id' => $this->collection->id, 'display_template' => '{{name}}', 'limit' => 10],
    ]);

    $widget = Livewire::test(ListWidget::class, ['panel' => $panel])->loadTable();

    expect(array_keys($widget->instance()->getTable()->getColumns()))->toBe(['name']);
    $widget->assertSee('Alice');
});
