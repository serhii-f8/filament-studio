<?php

use Flexpik\FilamentStudio\Models\StudioCollection;
use Flexpik\FilamentStudio\Models\StudioField;
use Flexpik\FilamentStudio\Models\StudioValue;
use Flexpik\FilamentStudio\Services\EavQueryBuilder;
use Illuminate\Support\Carbon;

mutates(EavQueryBuilder::class);

beforeEach(function () {
    $this->collection = StudioCollection::factory()->create(['tenant_id' => 1, 'slug' => 'pages']);

    $make = fn (string $column, string $type, string $cast, ?array $on = null, ?string $value = null) => StudioField::factory()->create([
        'collection_id' => $this->collection->id,
        'tenant_id' => 1,
        'column_name' => $column,
        'label' => $column,
        'field_type' => $type,
        'eav_cast' => $cast,
        'is_system' => $on !== null,
        'auto_fill_on' => $on,
        'auto_fill_value' => $value,
    ]);

    $this->title = $make('title', 'text', 'text');
    $this->createdAt = $make('created_at', 'datetime', 'datetime', ['create'], '$NOW');
    $this->updatedAt = $make('updated_at', 'datetime', 'datetime', ['create', 'update'], '$NOW');
    $this->createdBy = $make('created_by', 'belongs_to', 'integer', ['create'], '$CURRENT_USER');
    $this->updatedBy = $make('updated_by', 'belongs_to', 'integer', ['create', 'update'], '$CURRENT_USER');

    $this->value = fn (int $recordId, StudioField $field) => StudioValue::where('record_id', $recordId)
        ->where('field_id', $field->id)
        ->first()
        ?->{$field->eav_cast->column()};
});

afterEach(fn () => Carbon::setTestNow());

it('auto-fills timestamp and user fields on create', function () {
    Carbon::setTestNow('2026-11-18 10:00:00');

    $record = EavQueryBuilder::for($this->collection)->tenant(1)->create(['title' => 'Hello'], 7);

    expect(($this->value)($record->id, $this->title))->toBe('Hello')
        ->and((string) ($this->value)($record->id, $this->createdAt))->toStartWith('2026-11-18 10:00:00')
        ->and((string) ($this->value)($record->id, $this->updatedAt))->toStartWith('2026-11-18 10:00:00')
        ->and((int) ($this->value)($record->id, $this->createdBy))->toBe(7)
        ->and((int) ($this->value)($record->id, $this->updatedBy))->toBe(7);
});

it('overrides client-supplied values for auto-filled fields on create', function () {
    Carbon::setTestNow('2026-11-18 10:00:00');

    $record = EavQueryBuilder::for($this->collection)->tenant(1)->create([
        'title' => 'Hello',
        'created_at' => '1999-01-01 00:00:00',
        'created_by' => 999,
    ], 7);

    expect((string) ($this->value)($record->id, $this->createdAt))->toStartWith('2026-11-18 10:00:00')
        ->and((int) ($this->value)($record->id, $this->createdBy))->toBe(7);
});

it('refreshes only update-triggered fields on update', function () {
    Carbon::setTestNow('2026-11-18 10:00:00');
    $record = EavQueryBuilder::for($this->collection)->tenant(1)->create(['title' => 'Hello'], 7);

    Carbon::setTestNow('2026-11-19 12:30:00');
    EavQueryBuilder::for($this->collection)->tenant(1)->update($record->id, ['title' => 'Edited'], 8);

    expect(($this->value)($record->id, $this->title))->toBe('Edited')
        ->and((string) ($this->value)($record->id, $this->createdAt))->toStartWith('2026-11-18 10:00:00')
        ->and((int) ($this->value)($record->id, $this->createdBy))->toBe(7)
        ->and((string) ($this->value)($record->id, $this->updatedAt))->toStartWith('2026-11-19 12:30:00')
        ->and((int) ($this->value)($record->id, $this->updatedBy))->toBe(8);
});

it('falls back to the authenticated user when no user id is passed', function () {
    $this->authenticateUser();

    $record = EavQueryBuilder::for($this->collection)->tenant(1)->create(['title' => 'Hello']);

    expect((int) ($this->value)($record->id, $this->createdBy))->toBe(auth()->id());
});

it('keeps the previous user when an update has no user to attribute', function () {
    $record = EavQueryBuilder::for($this->collection)->tenant(1)->create(['title' => 'Hello'], 7);

    EavQueryBuilder::for($this->collection)->tenant(1)->update($record->id, ['title' => 'Edited']);

    expect((int) ($this->value)($record->id, $this->updatedBy))->toBe(7);
});
