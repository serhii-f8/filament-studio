<?php

namespace Flexpik\FilamentStudio\Panels;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Flexpik\FilamentStudio\Enums\PanelPlacement;

/**
 * The panel editor form pieces shared by the dashboard and collection panel relation managers.
 */
class PanelFormSchema
{
    /**
     * The panel type picker. Types are limited to those supporting the placement returned by
     * $placement (re-evaluated so a placement picker can narrow the list live).
     *
     * @param  \Closure(Get): ?PanelPlacement  $placement
     * @param  array<string, mixed>  $configOverrides  merged into the seeded config when a type is picked
     */
    public static function typeSelect(\Closure $placement, array $configOverrides = []): Select
    {
        $registry = app(PanelTypeRegistry::class);

        return Select::make('panel_type')
            ->label('Type')
            ->options(function (Get $get) use ($registry, $placement): array {
                $current = $placement($get);

                if ($current === null) {
                    return [];
                }

                return collect($registry->forPlacement($current))
                    ->mapWithKeys(fn (string $class, string $key) => [$key => $class::$label])
                    ->toArray();
            })
            ->required()
            ->live()
            ->afterStateUpdated(function (?string $state, Set $set) use ($registry, $configOverrides): void {
                // Seed the config container with the panel type's defaults so the
                // dependent config fields always hydrate against an array. Without
                // this, `config` is null on a fresh panel and Livewire throws
                // "Cannot set properties of null (setting 'collection_id')", and
                // toggles absent from defaultConfig() log "Livewire Entangle Error".
                try {
                    $config = $state ? $registry->get($state)::initialConfig() : [];
                } catch (\InvalidArgumentException) {
                    $config = [];
                }

                foreach ($configOverrides as $key => $value) {
                    if (array_key_exists($key, $config)) {
                        $config[$key] = $value;
                    }
                }

                $set('config', $config);
            });
    }

    public static function headerSection(): Section
    {
        return Section::make('Header')->schema([
            Toggle::make('header_visible')
                ->label('Show Header')
                ->default(true),
            TextInput::make('header_label')
                ->label('Label'),
            TextInput::make('header_icon')
                ->label('Icon'),
            TextInput::make('header_color')
                ->label('Color'),
            TextInput::make('header_note')
                ->label('Note'),
        ]);
    }

    public static function configSection(): Section
    {
        $registry = app(PanelTypeRegistry::class);

        return Section::make('Configuration')
            ->statePath('config')
            ->schema(function (Get $get) use ($registry) {
                $panelType = $get('panel_type');
                if (! $panelType) {
                    return [];
                }

                return $registry->configSchema($panelType);
            });
    }
}
