<?php

use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Flexpik\FilamentStudio\Enums\EavCast;
use Flexpik\FilamentStudio\FieldTypes\Types\BelongsToFieldType;
use Flexpik\FilamentStudio\Models\StudioField;

it('has correct static properties', function () {
    expect(BelongsToFieldType::$key)->toBe('belongs_to');
    expect(BelongsToFieldType::$label)->toBe('Belongs To');
    expect(BelongsToFieldType::$icon)->toBe('heroicon-o-arrow-top-right-on-square');
    expect(BelongsToFieldType::$eavCast)->toBe(EavCast::Text);
    expect(BelongsToFieldType::$category)->toBe('relational');
});

it('generates a Select component', function () {
    $field = StudioField::factory()->make(['column_name' => 'author_id', 'field_type' => 'belongs_to', 'settings' => ['related_collection' => 'authors', 'display_field' => 'name']]);
    $type = new BelongsToFieldType($field);
    expect($type->toFilamentComponent())->toBeInstanceOf(Select::class);
});

it('applies searchable setting', function () {
    $field = StudioField::factory()->make(['column_name' => 'category_id', 'field_type' => 'belongs_to', 'settings' => ['related_collection' => 'categories', 'searchable' => true]]);
    $type = new BelongsToFieldType($field);
    expect($type->toFilamentComponent())->toBeInstanceOf(Select::class);
});

it('generates a TextColumn for tables', function () {
    $field = StudioField::factory()->make(['column_name' => 'author_id', 'field_type' => 'belongs_to', 'settings' => ['related_collection' => 'authors']]);
    $type = new BelongsToFieldType($field);
    expect($type->toTableColumn())->toBeInstanceOf(TextColumn::class);
});

it('returns a settings schema', function () {
    expect(BelongsToFieldType::settingsSchema())->toBeArray()->not->toBeEmpty();
});

it('shows the user name for a $CURRENT_USER system field without a related collection', function () {
    $user = $this->authenticateUser();
    $field = StudioField::factory()->make([
        'column_name' => 'created_by',
        'field_type' => 'belongs_to',
        'eav_cast' => 'integer',
        'auto_fill_on' => ['create'],
        'auto_fill_value' => '$CURRENT_USER',
        'settings' => null,
    ]);
    $type = new BelongsToFieldType($field);

    expect($type->toTableColumn()->formatState($user->id))->toBe('Test User')
        ->and($type->toTableColumn()->formatState(987654))->toBe('987654')
        ->and($type->toTableColumn()->formatState(null))->toBeNull();
});
