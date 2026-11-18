<?php

use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Flexpik\FilamentStudio\Enums\EavCast;
use Flexpik\FilamentStudio\FieldTypes\Types\SelectFieldType;
use Flexpik\FilamentStudio\Models\StudioField;
use Flexpik\FilamentStudio\Models\StudioFieldOption;

it('has correct static properties', function () {
    expect(SelectFieldType::$key)->toBe('select');
    expect(SelectFieldType::$label)->toBe('Select');
    expect(SelectFieldType::$icon)->toBe('heroicon-o-chevron-up-down');
    expect(SelectFieldType::$eavCast)->toBe(EavCast::Text);
    expect(SelectFieldType::$category)->toBe('selection');
});

it('generates a Select component', function () {
    $field = StudioField::factory()->make([
        'column_name' => 'status',
        'field_type' => 'select',
        'settings' => ['options_source' => 'static'],
    ]);

    $type = new SelectFieldType($field);
    $component = $type->toFilamentComponent();

    expect($component)->toBeInstanceOf(Select::class);
});

it('applies searchable setting', function () {
    $field = StudioField::factory()->make([
        'column_name' => 'category',
        'field_type' => 'select',
        'settings' => ['searchable' => true],
    ]);

    $type = new SelectFieldType($field);
    $component = $type->toFilamentComponent();

    expect($component)->toBeInstanceOf(Select::class);
});

it('applies preload setting', function () {
    $field = StudioField::factory()->make([
        'column_name' => 'region',
        'field_type' => 'select',
        'settings' => ['preload' => true],
    ]);

    $type = new SelectFieldType($field);
    $component = $type->toFilamentComponent();

    expect($component)->toBeInstanceOf(Select::class);
});

it('generates a TextColumn with badge for tables', function () {
    $field = StudioField::factory()->make([
        'column_name' => 'status',
        'field_type' => 'select',
        'settings' => [],
    ]);

    $type = new SelectFieldType($field);
    $column = $type->toTableColumn();

    expect($column)->toBeInstanceOf(TextColumn::class);
});

it('returns a settings schema', function () {
    $schema = SelectFieldType::settingsSchema();

    expect($schema)->toBeArray();
    expect($schema)->not->toBeEmpty();
});

it('shows option labels rather than stored values in the table column', function () {
    $field = StudioField::factory()->create(['column_name' => 'status', 'field_type' => 'select', 'eav_cast' => 'text']);
    StudioFieldOption::create(['field_id' => $field->id, 'value' => 'review', 'label' => 'In review', 'sort_order' => 0]);

    $column = (new SelectFieldType($field))->toTableColumn();

    expect($column->formatState('review'))->toBe('In review')
        ->and($column->formatState('retired'))->toBe('retired');
});

it('colours the table badge with the option colour', function () {
    $field = StudioField::factory()->create(['column_name' => 'status', 'field_type' => 'select', 'eav_cast' => 'text']);
    StudioFieldOption::create(['field_id' => $field->id, 'value' => 'churned', 'label' => 'Churned', 'color' => 'danger', 'sort_order' => 0]);
    StudioFieldOption::create(['field_id' => $field->id, 'value' => 'lead', 'label' => 'Lead', 'sort_order' => 1]);

    $column = (new SelectFieldType($field))->toTableColumn();

    expect($column->getColor('churned'))->toBe('danger')
        ->and($column->getColor('lead'))->toBe('primary');
});

it('turns a hex option colour into a Filament palette', function () {
    $field = StudioField::factory()->create(['column_name' => 'tier', 'field_type' => 'select', 'eav_cast' => 'text']);
    StudioFieldOption::create(['field_id' => $field->id, 'value' => 'gold', 'label' => 'Gold', 'color' => '#f59e0b', 'sort_order' => 0]);

    $colour = (new SelectFieldType($field))->toTableColumn()->getColor('gold');

    expect($colour)->toBeArray()->toHaveKey(500);
});
