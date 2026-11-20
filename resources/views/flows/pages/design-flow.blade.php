<x-filament-panels::page>
    @push('styles')
        <meta name="studio-api-key" content="{{ $this->canvasApiKey }}">
        {{-- Prebuilt with the package (resources/dist) and published by `php artisan filament:assets`. --}}
        <link rel="stylesheet" href="{{ \Filament\Support\Facades\FilamentAsset::getStyleHref('studio-flow-canvas', 'serhii-f8/filament-studio') }}">
        <script type="module" src="{{ \Filament\Support\Facades\FilamentAsset::getScriptSrc('studio-flow-canvas', 'serhii-f8/filament-studio') }}"></script>
    @endpush

    <div
        id="flow-canvas-root"
        data-flow-id="{{ $record->id }}"
        data-api-base="{{ $this->getApiBase() }}"
        data-flow-name="{{ $record->name }}"
        class="h-[calc(100dvh-10rem)] w-full"
    ></div>
</x-filament-panels::page>
