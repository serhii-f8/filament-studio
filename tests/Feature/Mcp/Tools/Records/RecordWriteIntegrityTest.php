<?php

declare(strict_types=1);

use Flexpik\FilamentStudio\Enums\ApiAction;
use Flexpik\FilamentStudio\Mcp\Tools\Records\CreateRecordTool;
use Flexpik\FilamentStudio\Mcp\Tools\Records\GetRecordTool;
use Flexpik\FilamentStudio\Mcp\Tools\Records\QueryRecordsTool;
use Flexpik\FilamentStudio\Mcp\Tools\Records\UpdateRecordTool;
use Flexpik\FilamentStudio\Models\StudioApiKey;
use Flexpik\FilamentStudio\Models\StudioCollection;
use Flexpik\FilamentStudio\Models\StudioField;
use Flexpik\FilamentStudio\Models\StudioFieldOption;
use Flexpik\FilamentStudio\Models\StudioRecord;
use Flexpik\FilamentStudio\Models\StudioValue;
use Flexpik\FilamentStudio\Services\EavQueryBuilder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->collection = StudioCollection::factory()->create(['slug' => 'users', 'tenant_id' => 1]);
    $field = fn (array $a) => StudioField::factory()->for($this->collection, 'collection')->create(array_merge(['tenant_id' => 1, 'eav_cast' => 'text'], $a));

    $field(['column_name' => 'login', 'field_type' => 'text', 'is_unique' => true]);
    $this->pw = $field(['column_name' => 'pw', 'field_type' => 'password']);
    $category = $field(['column_name' => 'category', 'field_type' => 'select']);
    StudioFieldOption::create(['field_id' => $category->id, 'value' => 'staff', 'label' => 'Staff', 'sort_order' => 0]);

    $this->key = StudioApiKey::factory()
        ->withPermissions(['users' => [ApiAction::Store->value, ApiAction::Update->value]])
        ->forTenant(1)->create();
});

it('rejects an undefined select option', function () {
    mcpCallTool($this->key, CreateRecordTool::class, [
        'collection_slug' => 'users',
        'data' => ['login' => 'a', 'category' => 'bogus'],
    ])->assertSee('STUDIO_VALIDATION_FAILED');

    expect(StudioRecord::count())->toBe(0);
});

it('rejects a duplicate unique value', function () {
    EavQueryBuilder::for($this->collection)->create(['login' => 'neo']);

    mcpCallTool($this->key, CreateRecordTool::class, [
        'collection_slug' => 'users',
        'data' => ['login' => 'neo'],
    ])->assertSee('STUDIO_VALIDATION_FAILED');
});

it('lets an update keep the record\'s own unique value', function () {
    $record = EavQueryBuilder::for($this->collection)->create(['login' => 'neo']);

    mcpCallTool($this->key, UpdateRecordTool::class, [
        'collection_slug' => 'users',
        'uuid' => $record->uuid,
        'data' => ['login' => 'neo', 'category' => 'staff'],
    ])->assertDontSee('STUDIO_VALIDATION_FAILED');
});

it('hashes passwords and does not echo them back', function () {
    mcpCallTool($this->key, CreateRecordTool::class, [
        'collection_slug' => 'users',
        'data' => ['login' => 'neo', 'pw' => 'hunter2'],
    ])->assertDontSee('hunter2')->assertDontSee('$2y$');

    $stored = StudioValue::where('field_id', $this->pw->id)->value('val_text');
    expect(Hash::check('hunter2', $stored))->toBeTrue();
});

it('never returns password hashes from get or query', function () {
    $record = EavQueryBuilder::for($this->collection)->create(['login' => 'neo', 'pw' => 'hunter2']);
    $key = StudioApiKey::factory()
        ->withPermissions(['users' => [ApiAction::Index->value, ApiAction::Show->value]])
        ->forTenant(1)->create();

    mcpCallTool($key, GetRecordTool::class, [
        'collection_slug' => 'users', 'uuid' => $record->uuid,
    ])->assertSee('neo')->assertDontSee('$2y$');

    mcpCallTool($key, QueryRecordsTool::class, [
        'collection_slug' => 'users',
    ])->assertSee('neo')->assertDontSee('$2y$');
});
