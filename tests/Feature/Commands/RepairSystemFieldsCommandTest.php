<?php

use Flexpik\FilamentStudio\Models\StudioCollection;
use Flexpik\FilamentStudio\Models\StudioField;
use Flexpik\FilamentStudio\Models\StudioFieldOption;
use Flexpik\FilamentStudio\Models\StudioRecord;
use Flexpik\FilamentStudio\Models\StudioValue;

beforeEach(function () {
    $this->collection = StudioCollection::factory()->create(['tenant_id' => 3, 'slug' => 'posts']);
});

/** A Status field exactly as releases up to v1.8.1 created it: choices parked in settings. */
function legacyStatusField(StudioCollection $collection): StudioField
{
    return StudioField::factory()->create([
        'collection_id' => $collection->id,
        'tenant_id' => $collection->tenant_id,
        'column_name' => 'status',
        'field_type' => 'select',
        'eav_cast' => 'text',
        'is_system' => true,
        'settings' => [
            'options_source' => 'static',
            'default_options' => [
                ['value' => 'draft', 'label' => 'Draft'],
                ['value' => 'published', 'label' => 'Published'],
                ['value' => 'archived', 'label' => 'Archived'],
            ],
        ],
    ]);
}

function autoFillField(StudioCollection $collection, string $column, string $cast, array $on, string $token): StudioField
{
    return StudioField::factory()->create([
        'collection_id' => $collection->id,
        'tenant_id' => $collection->tenant_id,
        'column_name' => $column,
        'field_type' => $cast === 'datetime' ? 'datetime' : 'belongs_to',
        'eav_cast' => $cast,
        'is_system' => true,
        'auto_fill_on' => $on,
        'auto_fill_value' => $token,
    ]);
}

it('turns legacy default_options into real option rows', function () {
    $status = legacyStatusField($this->collection);

    $this->artisan('studio:repair-system-fields')->assertSuccessful();

    $options = StudioFieldOption::where('field_id', $status->id)->orderBy('sort_order')->get();
    expect($options->pluck('label', 'value')->all())->toBe(['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'])
        ->and($options->pluck('tenant_id')->unique()->all())->toBe([3])
        ->and($status->fresh()->settings)->toBe(['options_source' => 'static']);
});

it('is idempotent and never overwrites options a user already defined', function () {
    $status = legacyStatusField($this->collection);
    StudioFieldOption::create(['field_id' => $status->id, 'tenant_id' => 3, 'value' => 'live', 'label' => 'Live', 'sort_order' => 0]);

    $this->artisan('studio:repair-system-fields')->assertSuccessful();
    $this->artisan('studio:repair-system-fields')->assertSuccessful();

    expect(StudioFieldOption::where('field_id', $status->id)->pluck('value')->all())->toBe(['live']);
});

it('back-fills empty system timestamp and user fields from the record itself', function () {
    $createdAt = autoFillField($this->collection, 'created_at', 'datetime', ['create'], '$NOW');
    $updatedAt = autoFillField($this->collection, 'updated_at', 'datetime', ['create', 'update'], '$NOW');
    $createdBy = autoFillField($this->collection, 'created_by', 'integer', ['create'], '$CURRENT_USER');
    $updatedBy = autoFillField($this->collection, 'updated_by', 'integer', ['create', 'update'], '$CURRENT_USER');

    $record = StudioRecord::create(['collection_id' => $this->collection->id, 'tenant_id' => 3, 'created_by' => 4, 'updated_by' => 5]);
    $record->forceFill(['created_at' => '2026-10-01 08:00:00', 'updated_at' => '2026-10-02 09:30:00'])->saveQuietly();

    $untouched = StudioRecord::create(['collection_id' => $this->collection->id, 'tenant_id' => 3, 'created_by' => 4]);
    StudioValue::create(['record_id' => $untouched->id, 'field_id' => $createdBy->id, 'val_integer' => 99]);

    $this->artisan('studio:repair-system-fields')->assertSuccessful();
    $this->artisan('studio:repair-system-fields')->assertSuccessful();

    $value = fn (StudioRecord $r, StudioField $f) => StudioValue::where('record_id', $r->id)->where('field_id', $f->id)->get();

    expect((string) $value($record, $createdAt)->sole()->val_datetime)->toStartWith('2026-10-01 08:00:00')
        ->and((string) $value($record, $updatedAt)->sole()->val_datetime)->toStartWith('2026-10-02 09:30:00')
        ->and($value($record, $createdBy)->sole()->val_integer)->toBe(4)
        ->and($value($record, $updatedBy)->sole()->val_integer)->toBe(5)
        ->and($value($untouched, $createdBy)->sole()->val_integer)->toBe(99)
        ->and($value($untouched, $updatedBy))->toBeEmpty();
});

it('runs automatically as part of the package migrations', function () {
    $migrations = collect(glob(__DIR__.'/../../../database/migrations/*.php.stub'))->map(fn ($f) => basename($f));

    expect($migrations)->toContain('z_repair_system_fields.php.stub')
        ->and(file_get_contents(__DIR__.'/../../../database/migrations/z_repair_system_fields.php.stub'))->toContain("Artisan::call('studio:repair-system-fields')");
});

it('gives existing Status system fields a draft default', function () {
    $status = legacyStatusField($this->collection);
    $custom = StudioField::factory()->create([
        'collection_id' => $this->collection->id, 'column_name' => 'status', 'field_type' => 'select',
        'eav_cast' => 'text', 'is_system' => true, 'default_value' => 'published',
        'collection_id' => StudioCollection::factory()->create()->id,
    ]);

    $this->artisan('studio:repair-system-fields')->assertSuccessful();

    expect($status->fresh()->default_value)->toBe('draft')
        ->and($custom->fresh()->default_value)->toBe('published');
});
