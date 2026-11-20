<?php

use Flexpik\FilamentStudio\Models\StudioCollection;
use Flexpik\FilamentStudio\Models\StudioField;
use Flexpik\FilamentStudio\Models\StudioRecord;
use Flexpik\FilamentStudio\Models\StudioRecordVersion;
use Flexpik\FilamentStudio\Models\StudioValue;
use Flexpik\FilamentStudio\Observers\RecordVersioningObserver;
use Flexpik\FilamentStudio\Services\EavQueryBuilder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-01-01 12:00:00');

    $this->collection = StudioCollection::factory()->withVersioning()->create();
    $this->field = StudioField::factory()->create([
        'collection_id' => $this->collection->id,
        'column_name' => 'title',
        'eav_cast' => 'text',
    ]);
    $this->record = StudioRecord::factory()->create(['collection_id' => $this->collection->id]);
    $this->value = StudioValue::factory()->create([
        'record_id' => $this->record->id,
        'field_id' => $this->field->id,
        'val_text' => 'Old',
    ]);
});

afterEach(fn () => Carbon::setTestNow());

function simulateEdit($test, string $newValue): void
{
    $observer = new RecordVersioningObserver;
    $observer->updating($test->record);
    $test->value->update(['val_text' => $newValue]);
    $observer->updated($test->record);
}

it('orders same-second versions latest-first with the after-snapshot on top', function () {
    simulateEdit($this, 'New');

    $versions = StudioRecordVersion::query()
        ->where('record_id', $this->record->id)
        ->latestFirst()
        ->get();

    expect($versions)->toHaveCount(2)
        ->and($versions->first()->created_at->equalTo($versions->last()->created_at))->toBeTrue()
        ->and($versions->first()->snapshot)->toBe(['title' => 'New'])
        ->and($versions->last()->snapshot)->toBe(['title' => 'Old']);
});

it('does not create a duplicate before-snapshot when edits happen in the same second', function () {
    simulateEdit($this, 'New');
    simulateEdit($this, 'Newer');

    $snapshots = StudioRecordVersion::query()
        ->where('record_id', $this->record->id)
        ->latestFirst()
        ->pluck('snapshot')
        ->all();

    expect($snapshots)->toBe([
        ['title' => 'Newer'],
        ['title' => 'New'],
        ['title' => 'Old'],
    ]);
});

it('uses the id tiebreaker in the record page version history queries', function (string $file) {
    $source = file_get_contents(dirname(__DIR__, 2).'/src/Resources/DynamicCollectionResource/Pages/'.$file);

    expect($source)->toContain('->latestFirst()');
})->with(['ViewCollectionRecord.php', 'EditCollectionRecord.php']);

it('records the restored state as the latest version even within the same second', function () {
    simulateEdit($this, 'New');

    $oldVersion = StudioRecordVersion::query()
        ->where('record_id', $this->record->id)
        ->latestFirst()
        ->get()
        ->last();

    EavQueryBuilder::for($this->collection)
        ->tenant($this->record->tenant_id)
        ->restoreFromVersion($this->record->uuid, $oldVersion->id);

    $latest = StudioRecordVersion::query()
        ->where('record_id', $this->record->id)
        ->latestFirst()
        ->first();

    expect($latest->snapshot)->toBe(['title' => 'Old'])
        ->and($latest->id)->not->toBe($oldVersion->id);
});
