<?php

namespace Flexpik\FilamentStudio\Resources\DashboardResource\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Flexpik\FilamentStudio\Enums\PanelPlacement;
use Flexpik\FilamentStudio\Models\StudioPanel;
use Flexpik\FilamentStudio\Panels\PanelFormSchema;
use Livewire\Attributes\On;

class PanelsRelationManager extends RelationManager
{
    protected static string $relationship = 'panels';

    protected static ?string $modelLabel = 'panel';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Panel Type')->schema([
                PanelFormSchema::typeSelect(fn (): PanelPlacement => PanelPlacement::Dashboard),
            ]),
            PanelFormSchema::headerSection(),
            PanelFormSchema::configSection(),
            Section::make('Grid Size')->schema([
                TextInput::make('grid_col_span')
                    ->label('Column Span (1-12)')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(12)
                    ->default(6),
                TextInput::make('grid_row_span')
                    ->label('Row Span (1-8)')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(8)
                    ->default(4),
            ]),
        ]);
    }

    /**
     * Panels added from the page header ("Add Panel") are created outside this table.
     */
    #[On('studio-panels-updated')]
    public function refreshPanels(): void
    {
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('panel_type')->label('Type')->badge(),
                TextColumn::make('header_label')->label('Label'),
                TextColumn::make('grid_col_span')->label('Columns'),
                TextColumn::make('grid_order')->label('Order')->sortable(),
            ])
            ->defaultSort('grid_order')
            ->actions([
                EditAction::make()
                    ->mutateRecordDataUsing(function (array $data, StudioPanel $record): array {
                        $data['config'] = $record->merged_config;

                        return $data;
                    }),
                DeleteAction::make(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['tenant_id'] = Filament::getTenant()?->getKey();
                        $data['dashboard_id'] = $this->getOwnerRecord()->getKey();
                        $data['placement'] = PanelPlacement::Dashboard;
                        $data['grid_order'] = $this->getOwnerRecord()->panels()->max('grid_order') + 1;

                        return $data;
                    }),
            ])
            ->reorderable('grid_order');
    }
}
