<?php

use Flexpik\FilamentStudio\Models\StudioCollection;
use Flexpik\FilamentStudio\Models\StudioField;
use Flexpik\FilamentStudio\Models\StudioRecord;
use Flexpik\FilamentStudio\Services\EavQueryBuilder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->collection = StudioCollection::factory()->create(['slug' => 'articles']);
    $this->valuesTable = config('filament-studio.table_prefix', 'studio_').'values';
});

function jsonField(StudioCollection $collection, string $column, string $type): StudioField
{
    return StudioField::factory()->create([
        'collection_id' => $collection->id,
        'column_name' => $column,
        'field_type' => $type,
        'eav_cast' => 'json',
    ]);
}

function rawValue(StudioCollection $collection, string $valuesTable, StudioField $field, array $columns): void
{
    $record = StudioRecord::factory()->create(['collection_id' => $collection->id]);

    DB::table($valuesTable)->insert(array_merge([
        'record_id' => $record->id,
        'field_id' => $field->id,
        'locale' => 'en',
    ], $columns));
}

it('writes plain strings into a json field as valid JSON', function () {
    $field = jsonField($this->collection, 'meta', 'key_value');

    EavQueryBuilder::for($this->collection)->create(['meta' => 'just text']);

    $raw = DB::table($this->valuesTable)->where('field_id', $field->id)->value('val_json');
    expect(json_validate($raw))->toBeTrue()
        ->and(json_decode($raw, true))->toBe('just text');
});

it('keeps strings that already are JSON as they are', function () {
    $field = jsonField($this->collection, 'meta', 'key_value');

    EavQueryBuilder::for($this->collection)->create(['meta' => '{"a":1}']);

    expect(DB::table($this->valuesTable)->where('field_id', $field->id)->value('val_json'))->toBe('{"a":1}');
});

it('moves legacy json-cast rich editor and file fields to text storage', function () {
    $body = jsonField($this->collection, 'body', 'rich_editor');
    $cover = jsonField($this->collection, 'cover', 'image');

    rawValue($this->collection, $this->valuesTable, $body, ['val_json' => '<p>Hello</p>']);
    rawValue($this->collection, $this->valuesTable, $cover, ['val_json' => '"covers/a.png"']);

    $this->artisan('studio:repair-json-storage')->assertSuccessful();

    expect($body->fresh()->eav_cast->value)->toBe('text')
        ->and($cover->fresh()->eav_cast->value)->toBe('text');

    $rows = DB::table($this->valuesTable)->whereIn('field_id', [$body->id, $cover->id])->orderBy('field_id')->get();
    expect($rows->pluck('val_text')->all())->toBe(['<p>Hello</p>', 'covers/a.png'])
        ->and($rows->pluck('val_json')->filter()->all())->toBe([]);
});

it('re-encodes malformed values left in genuine json fields', function () {
    $meta = jsonField($this->collection, 'meta', 'key_value');

    rawValue($this->collection, $this->valuesTable, $meta, ['val_json' => 'not json']);
    rawValue($this->collection, $this->valuesTable, $meta, ['val_json' => '{"ok":true}']);

    $this->artisan('studio:repair-json-storage')->assertSuccessful();

    $values = DB::table($this->valuesTable)->where('field_id', $meta->id)->orderBy('id')->pluck('val_json')->all();
    expect($values)->toBe(['"not json"', '{"ok":true}'])
        ->and($meta->fresh()->eav_cast->value)->toBe('json');
});

it('is idempotent', function () {
    $body = jsonField($this->collection, 'body', 'rich_editor');
    rawValue($this->collection, $this->valuesTable, $body, ['val_json' => '<p>Hello</p>']);

    $this->artisan('studio:repair-json-storage')->assertSuccessful();
    $this->artisan('studio:repair-json-storage')->assertSuccessful();

    expect(DB::table($this->valuesTable)->where('field_id', $body->id)->value('val_text'))->toBe('<p>Hello</p>');
});
