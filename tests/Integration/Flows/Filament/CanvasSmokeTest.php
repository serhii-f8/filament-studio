<?php

declare(strict_types=1);

use Filament\Support\Facades\FilamentAsset;
use Flexpik\FilamentStudio\Flows\Filament\Resources\FlowResource;
use Flexpik\FilamentStudio\Flows\Models\StudioFlow;

it('the design page returns 200 with canvas mount div and API key meta tag', function () {
    $this->withoutVite();
    $this->actingAs($this->makeUserWith(['view_flows', 'update_flows']));
    $flow = StudioFlow::factory()->create();

    $response = $this->get(FlowResource::getUrl('design', ['record' => $flow]));

    $response->assertOk();
    $response->assertSee('id="flow-canvas-root"', false);
    $response->assertSee('<meta name="studio-api-key"', false);
});

it('ships the prebuilt flow canvas bundle as a Filament asset', function () {
    // The canvas is built by the package (resources/dist), not by the host app's Vite.
    $assets = collect(FilamentAsset::getScripts(['serhii-f8/filament-studio']))
        ->merge(FilamentAsset::getStyles(['serhii-f8/filament-studio']))
        ->filter(fn ($asset) => $asset->getId() === 'studio-flow-canvas');

    expect($assets)->toHaveCount(2)
        ->and(file_exists(__DIR__.'/../../../../resources/dist/flow-canvas.js'))->toBeTrue()
        ->and(file_exists(__DIR__.'/../../../../resources/dist/flow-canvas.css'))->toBeTrue();
});
