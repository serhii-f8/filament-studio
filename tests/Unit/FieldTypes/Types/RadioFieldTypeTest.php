<?php

use Filament\Forms\Components\Radio;
use Filament\Tables\Columns\TextColumn;
use Flexpik\FilamentStudio\Enums\EavCast;
use Flexpik\FilamentStudio\FieldTypes\Types\RadioFieldType;
use Flexpik\FilamentStudio\Models\StudioField;
use Flexpik\FilamentStudio\Models\StudioFieldOption;

it('has correct static properties', function () {
    expect(RadioFieldType::$key)->toBe('radio');
    expect(RadioFieldType::$label)->toBe('Radio');
    expect(RadioFieldType::$icon)->toBe('heroicon-o-stop-circle');
    expect(RadioFieldType::$eavCast)->toBe(EavCast::Text);
    expect(RadioFieldType::$category)->toBe('selection');
});

it('generates a Radio component', function () {
    $field = StudioField::factory()->make([
        'column_name' => 'priority',
        'field_type' => 'radio',
        'settings' => [],
    ]);

    $type = new RadioFieldType($field);
    $component = $type->toFilamentComponent();

    expect($component)->toBeInstanceOf(Radio::class);
});

it('generates a TextColumn for tables', function () {
    $field = StudioField::factory()->make([
        'column_name' => 'priority',
        'field_type' => 'radio',
        'settings' => [],
    ]);

    $type = new RadioFieldType($field);
    $column = $type->toTableColumn();

    expect($column)->toBeInstanceOf(TextColumn::class);
});

it('returns a settings schema', function () {
    $schema = RadioFieldType::settingsSchema();

    expect($schema)->toBeArray();
});

it('shows option labels rather than stored values in the table column', function () {
    $field = StudioField::factory()->create(['column_name' => 'status', 'field_type' => 'radio', 'eav_cast' => 'text']);
    StudioFieldOption::create(['field_id' => $field->id, 'value' => 'review', 'label' => 'In review', 'sort_order' => 0]);

    $column = (new RadioFieldType($field))->toTableColumn();

    expect($column->formatState('review'))->toBe('In review')
        ->and($column->formatState('retired'))->toBe('retired');
});

it('colours the table badge with the option colour', function () {
    $field = StudioField::factory()->create(['column_name' => 'status', 'field_type' => 'radio', 'eav_cast' => 'text']);
    StudioFieldOption::create(['field_id' => $field->id, 'value' => 'churned', 'label' => 'Churned', 'color' => 'danger', 'sort_order' => 0]);
    StudioFieldOption::create(['field_id' => $field->id, 'value' => 'lead', 'label' => 'Lead', 'sort_order' => 1]);

    $column = (new RadioFieldType($field))->toTableColumn();

    expect($column->getColor('churned'))->toBe('danger')
        ->and($column->getColor('lead'))->toBe('primary');
});
