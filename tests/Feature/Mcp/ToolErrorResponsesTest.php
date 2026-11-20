<?php

declare(strict_types=1);

use Flexpik\FilamentStudio\Enums\ApiAction;
use Flexpik\FilamentStudio\Mcp\Exceptions\StudioMcpExceptionHandler;
use Flexpik\FilamentStudio\Mcp\Exceptions\StudioNotFoundException;
use Flexpik\FilamentStudio\Mcp\Tools\Collections\CreateCollectionTool;
use Flexpik\FilamentStudio\Mcp\Tools\Collections\DeleteCollectionTool;
use Flexpik\FilamentStudio\Mcp\Tools\Collections\GetCollectionTool;
use Flexpik\FilamentStudio\Mcp\Tools\Records\GetRecordTool;
use Flexpik\FilamentStudio\Models\StudioApiKey;
use Flexpik\FilamentStudio\Models\StudioCollection;
use Laravel\Mcp\Response;

it('builds an isError MCP response from the exception handler', function () {
    $response = (new StudioMcpExceptionHandler)->toMcpResponse(new StudioNotFoundException('collection', 'x'));

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->isError())->toBeTrue()
        ->and((string) $response->content())->toContain('STUDIO_NOT_FOUND');
});

it('flags authorization failures as errors', function () {
    $key = StudioApiKey::factory()->withPermissions([])->forTenant(1)->create();

    mcpCallTool($key, CreateCollectionTool::class, ['name' => 'Products', 'slug' => 'products'])
        ->assertHasErrors(['STUDIO_UNAUTHORIZED']);
});

it('flags collection-level authorization failures as errors', function () {
    StudioCollection::factory()->create(['slug' => 'products', 'tenant_id' => 1]);
    $key = StudioApiKey::factory()->withPermissions([])->forTenant(1)->create();

    mcpCallTool($key, GetRecordTool::class, [
        'collection_slug' => 'products',
        'uuid' => '00000000-0000-0000-0000-000000000000',
    ])->assertHasErrors(['STUDIO_UNAUTHORIZED']);
});

it('flags not-found results as errors', function () {
    StudioCollection::factory()->create(['slug' => 'products', 'tenant_id' => 1]);
    $key = StudioApiKey::factory()
        ->withPermissions(['products' => [ApiAction::Show->value]])
        ->forTenant(1)->create();

    mcpCallTool($key, GetRecordTool::class, [
        'collection_slug' => 'products',
        'uuid' => '00000000-0000-0000-0000-000000000000',
    ])->assertHasErrors(['STUDIO_NOT_FOUND']);
});

it('flags invalid confirm tokens as errors', function () {
    StudioCollection::factory()->create(['slug' => 'products', 'tenant_id' => 1]);
    $key = StudioApiKey::factory()->withPermissions(['_studio' => ['manage_collections']])->forTenant(1)->create();

    mcpCallTool($key, DeleteCollectionTool::class, ['slug' => 'products', 'confirm_token' => 'bogus'])
        ->assertHasErrors();
});

it('does not flag successful calls as errors', function () {
    StudioCollection::factory()->create(['slug' => 'products', 'tenant_id' => 1]);
    $key = StudioApiKey::factory()->withPermissions(['_studio' => ['read_schema']])->forTenant(1)->create();

    mcpCallTool($key, GetCollectionTool::class, ['slug' => 'products'])->assertHasNoErrors();
});
