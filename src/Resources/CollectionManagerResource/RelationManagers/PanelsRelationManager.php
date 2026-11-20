<?php

namespace Flexpik\FilamentStudio\Resources\CollectionManagerResource\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Flexpik\FilamentStudio\Enums\PanelPlacement;
use Flexpik\FilamentStudio\Models\StudioCollection;
use Flexpik\FilamentStudio\Models\StudioPanel;
use Flexpik\FilamentStudio\Panels\PanelFormSchema;
use Flexpik\FilamentStudio\Panels\PanelTypeRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Panels shown above or below this collection's record list and its record pages.
 */
class PanelsRelationManager extends RelationManager
{
    protected static string $relationship = 'panels';

    protected static ?string $title = 'Panels';

    protected static ?string $modelLabel = 'panel';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof StudioCollection;
    }

    /**
     * @return array<string, string>
     */
    public static function placementOptions(): array
    {
        return collect(PanelPlacement::cases())
            ->reject(fn (PanelPlacement $placement): bool => $placement->isDashboard())
            ->mapWithKeys(fn (PanelPlacement $placement): array => [$placement->value => $placement->label()])
            ->all();
    }

    public function form(Schema $schema): Schema
    {
        $placementFrom = function (Get $get): ?PanelPlacement {
            $state = $get('placement');

            return $state instanceof PanelPlacement ? $state : PanelPlacement::tryFrom((string) $state);
        };

        return $schema->components([
            Section::make('Placement')->schema([
                Select::make('placement')
                    ->label('Show on')
                    ->options(static::placementOptions())
                    ->default(PanelPlacement::CollectionHeader->value)
                    ->helperText('Collection placements sit above or below the record list; record placements sit above or below a single record\'s view and edit pages.')
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Get $get, Set $set) use ($placementFrom): void {
                        // Drop a type the new placement does not support (e.g. Variable is dashboard-only).
                        $type = $get('panel_type');
                        $placement = $placementFrom($get);

                        if ($type && $placement && ! array_key_exists($type, app(PanelTypeRegistry::class)->forPlacement($placement))) {
                            $set('panel_type', null);
                            $set('config', []);
                        }
                    }),
                PanelFormSchema::typeSelect($placementFrom, [
                    // Point data panels at this collection by default.
                    'collection_id' => $this->getOwnerRecord()->getKey(),
                ]),
            ]),
            PanelFormSchema::headerSection(),
            PanelFormSchema::configSection(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->where('tenant_id', Filament::getTenant()?->getKey()))
            ->columns([
                TextColumn::make('placement')
                    ->label('Placement')
                    ->formatStateUsing(fn (PanelPlacement $state): string => $state->label())
                    ->badge(),
                TextColumn::make('panel_type')->label('Type')->badge(),
                TextColumn::make('header_label')->label('Label'),
                TextColumn::make('sort_order')->label('Order')->sortable(),
            ])
            ->filters([
                SelectFilter::make('placement')->options(static::placementOptions()),
            ])
            ->defaultSort('sort_order')
            ->actions([
                EditAction::make()
                    ->mutateRecordDataUsing(function (array $data, StudioPanel $record): array {
                        $data['config'] = $record->merged_config;
                        $data['placement'] = $record->placement->value;

                        return $data;
                    }),
                DeleteAction::make(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add Panel')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['tenant_id'] = Filament::getTenant()?->getKey();
                        $data['context_collection_id'] = $this->getOwnerRecord()->getKey();
                        $data['dashboard_id'] = null;
                        $data['sort_order'] = (int) StudioPanel::query()
                            ->where('context_collection_id', $this->getOwnerRecord()->getKey())
                            ->where('placement', $data['placement'])
                            ->where('tenant_id', $data['tenant_id'])
                            ->max('sort_order') + 1;

                        return $data;
                    }),
            ])
            ->reorderable('sort_order');
    }
}
