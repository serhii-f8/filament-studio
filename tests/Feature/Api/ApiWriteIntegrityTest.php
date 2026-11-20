<?php

use Flexpik\FilamentStudio\Api\StudioApiRouteRegistrar;
use Flexpik\FilamentStudio\Models\StudioApiKey;
use Flexpik\FilamentStudio\Models\StudioCollection;
use Flexpik\FilamentStudio\Models\StudioField;
use Flexpik\FilamentStudio\Models\StudioFieldOption;
use Flexpik\FilamentStudio\Models\StudioRecord;
use Flexpik\FilamentStudio\Models\StudioValue;
use Flexpik\FilamentStudio\Services\EavQueryBuilder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

beforeEach(function () {
    StudioApiRouteRegistrar::register();

    $this->collection = StudioCollection::factory()->apiEnabled()->create(['slug' => 'accounts']);

    $make = fn (array $attrs) => StudioField::factory()->create(array_merge([
        'collection_id' => $this->collection->id,
        'eav_cast' => 'text',
    ], $attrs));

    $this->login = $make(['column_name' => 'login', 'label' => 'Login', 'field_type' => 'text', 'is_unique' => true, 'sort_order' => 1]);
    $this->password = $make(['column_name' => 'pw', 'label' => 'Password', 'field_type' => 'password', 'sort_order' => 2]);
    $this->priority = $make(['column_name' => 'priority', 'label' => 'Priority', 'field_type' => 'select', 'sort_order' => 3]);
    $this->tags = $make(['column_name' => 'tags', 'label' => 'Tags', 'field_type' => 'multi_select', 'eav_cast' => 'json', 'sort_order' => 4]);

    foreach (['low', 'high'] as $i => $value) {
        StudioFieldOption::create(['field_id' => $this->priority->id, 'value' => $value, 'label' => ucfirst($value), 'sort_order' => $i]);
        StudioFieldOption::create(['field_id' => $this->tags->id, 'value' => $value, 'label' => ucfirst($value), 'sort_order' => $i]);
    }

    EavQueryBuilder::invalidateFieldCache($this->collection->id);

    $this->plainKey = Str::random(64);
    StudioApiKey::factory()->fullAccess()->create(['key' => hash('sha256', $this->plainKey)]);
    $this->headers = ['X-Api-Key' => $this->plainKey, 'Accept' => 'application/json'];
});

it('hashes passwords written through the API and never returns them', function () {
    $response = $this->postJson('/api/studio/accounts', ['data' => ['login' => 'neo', 'pw' => 'hunter2']], $this->headers);

    $response->assertCreated();
    expect($response->json('data.data'))->not->toHaveKey('pw');
    expect($response->getContent())->not->toContain('hunter2');

    $stored = StudioValue::query()->where('field_id', $this->password->id)->value('val_text');
    expect($stored)->not->toBe('hunter2')
        ->and(Hash::check('hunter2', $stored))->toBeTrue();

    $list = $this->getJson('/api/studio/accounts', $this->headers);
    expect($list->getContent())->not->toContain($stored);

    $uuid = $response->json('data.uuid');
    $show = $this->getJson("/api/studio/accounts/{$uuid}", $this->headers);
    expect($show->json('data.data'))->not->toHaveKey('pw');
});

it('hashes passwords on API update', function () {
    $uuid = $this->postJson('/api/studio/accounts', ['data' => ['login' => 'neo']], $this->headers)->json('data.uuid');

    $this->putJson("/api/studio/accounts/{$uuid}", ['data' => ['pw' => 'swordfish']], $this->headers)->assertOk();

    $stored = StudioValue::query()->where('field_id', $this->password->id)->value('val_text');
    expect(Hash::check('swordfish', $stored))->toBeTrue();
});

it('does not double-hash an already hashed password written by the form', function () {
    $hash = bcrypt('hunter2');

    EavQueryBuilder::for($this->collection)->create(['login' => 'trinity', 'pw' => $hash]);

    expect(StudioValue::query()->where('field_id', $this->password->id)->value('val_text'))->toBe($hash);
});

it('rejects a duplicate value for a unique field on store', function () {
    $this->postJson('/api/studio/accounts', ['data' => ['login' => 'neo']], $this->headers)->assertCreated();

    $this->postJson('/api/studio/accounts', ['data' => ['login' => 'neo']], $this->headers)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['data.login']);
});

it('allows a record to keep its own unique value on update but not take another', function () {
    $neo = $this->postJson('/api/studio/accounts', ['data' => ['login' => 'neo']], $this->headers)->json('data.uuid');
    $this->postJson('/api/studio/accounts', ['data' => ['login' => 'morpheus']], $this->headers)->assertCreated();

    $this->putJson("/api/studio/accounts/{$neo}", ['data' => ['login' => 'neo']], $this->headers)->assertOk();
    $this->putJson("/api/studio/accounts/{$neo}", ['data' => ['login' => 'morpheus']], $this->headers)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['data.login']);
});

it('rejects select values that are not defined options', function () {
    $this->postJson('/api/studio/accounts', ['data' => ['priority' => 'urgent!!']], $this->headers)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['data.priority']);

    $this->postJson('/api/studio/accounts', ['data' => ['tags' => ['low', 'bogus']]], $this->headers)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['data.tags.1']);

    $this->postJson('/api/studio/accounts', ['data' => ['priority' => 'high', 'tags' => ['low']]], $this->headers)
        ->assertCreated();
});

it('accepts any value for a select that allows creating options', function () {
    $this->priority->update(['settings' => ['allow_create' => true]]);
    EavQueryBuilder::invalidateFieldCache($this->collection->id);

    $this->postJson('/api/studio/accounts', ['data' => ['priority' => 'brand-new']], $this->headers)->assertCreated();
});

it('treats an invalid per_page as the default instead of failing', function (string $perPage) {
    EavQueryBuilder::for($this->collection)->create(['login' => 'a']);

    $this->getJson("/api/studio/accounts?per_page={$perPage}", $this->headers)
        ->assertOk()
        ->assertJsonCount(1, 'data');
})->with(['-5', '0', 'abc']);

it('returns the fresh updated_at after an update', function () {
    $this->travelTo(now()->subDay());
    $uuid = $this->postJson('/api/studio/accounts', ['data' => ['login' => 'neo']], $this->headers)->json('data.uuid');
    $this->travelBack();

    $response = $this->putJson("/api/studio/accounts/{$uuid}", ['data' => ['login' => 'neo2']], $this->headers);

    $fresh = StudioRecord::query()->where('uuid', $uuid)->first();
    expect($response->json('data.updated_at'))->toBe($fresh->updated_at->toIso8601String())
        ->and($fresh->updated_at->isToday())->toBeTrue();
});

it('orders API pages deterministically when records share a created_at', function () {
    $this->travelTo(now());
    $uuids = collect(range(1, 4))->map(fn ($i) => EavQueryBuilder::for($this->collection)->create(['login' => "u{$i}"])->uuid);

    $page1 = $this->getJson('/api/studio/accounts?per_page=2&page=1', $this->headers)->json('data.*.uuid');
    $page2 = $this->getJson('/api/studio/accounts?per_page=2&page=2', $this->headers)->json('data.*.uuid');

    expect(array_merge($page1, $page2))->toBe($uuids->reverse()->values()->all());
});

it('does not repeat system timestamp fields inside data', function () {
    StudioField::factory()->create([
        'collection_id' => $this->collection->id,
        'column_name' => 'created_at',
        'field_type' => 'datetime',
        'eav_cast' => 'datetime',
        'is_system' => true,
        'auto_fill_on' => ['create'],
        'auto_fill_value' => '$NOW',
    ]);
    EavQueryBuilder::invalidateFieldCache($this->collection->id);

    $created = $this->postJson('/api/studio/accounts', ['data' => ['login' => 'neo']], $this->headers);

    expect($created->json('data.data'))->not->toHaveKey('created_at')
        ->and($created->json('data.created_at'))->not->toBeNull();
    expect($this->getJson('/api/studio/accounts', $this->headers)->json('data.0.data'))->not->toHaveKey('created_at');
});
