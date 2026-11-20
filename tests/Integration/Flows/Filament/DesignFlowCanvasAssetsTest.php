<?php

declare(strict_types=1);

use Flexpik\FilamentStudio\Flows\Filament\Resources\FlowResource;
use Flexpik\FilamentStudio\Flows\Models\StudioFlow;

it('loads the prebuilt canvas bundle shipped with the package, not a host Vite entry', function () {
    $this->actingAs($this->makeUserWith(['view_flows', 'update_flows']));
    $flow = StudioFlow::factory()->create();

    // No withoutVite(): a host app has no Vite entry for the package's canvas source.
    $response = $this->get(FlowResource::getUrl('design', ['record' => $flow]));

    $response->assertOk()
        ->assertSee('id="flow-canvas-root"', false)
        ->assertSee('serhii-f8/filament-studio/studio-flow-canvas.js', false)
        ->assertSee('serhii-f8/filament-studio/studio-flow-canvas.css', false)
        ->assertDontSee('resources/js/flows/flow-canvas.tsx', false);
});

it('ships the built canvas files', function () {
    $dist = dirname(__DIR__, 4).'/resources/dist';

    expect("{$dist}/flow-canvas.js")->toBeFile()
        ->and("{$dist}/flow-canvas.css")->toBeFile();
});
