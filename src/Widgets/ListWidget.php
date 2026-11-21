<?php

namespace Flexpik\FilamentStudio\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Flexpik\FilamentStudio\Concerns\InteractsWithPanelConfig;
use Flexpik\FilamentStudio\Enums\EavCast;
use Flexpik\FilamentStudio\Models\StudioCollection;
use Flexpik\FilamentStudio\Models\StudioPanel;
use Flexpik\FilamentStudio\Services\EavQueryBuilder;
use Illuminate\Support\Str;

class ListWidget extends TableWidget
{
    use InteractsWithPanelConfig;

    protected int|string|array $columnSpan = 'full';

    public function mount(StudioPanel $panel, array $variables = [], ?string $recordUuid = null): void
    {
        $this->mountInteractsWithPanelConfig($panel, $variables, $recordUuid);
    }

    public function table(Table $table): Table
    {
        $config = $this->resolvedConfig();
        $collectionId = $config['collection_id'] ?? null;

        if (! $collectionId) {
            return $table->columns([TextColumn::make('message')->default('No collection configured')]);
        }

        $collection = StudioCollection::find($collectionId);
        if (! $collection) {
            return $table->columns([TextColumn::make('message')->default('Collection not found')]);
        }

        $query = EavQueryBuilder::for($collection)->tenant($this->panel->tenant_id);

        if ($sortField = $config['sort_field'] ?? null) {
            $query->orderBy($sortField, $config['sort_direction'] ?? 'asc');
        }

        $limit = (int) ($config['limit'] ?? 10);
        $eloquentQuery = $query->toEloquentQuery()->limit($limit);

        // Read raw: the template's {{tokens}} name fields, not dashboard variables. Through the
        // variable resolver a one-token template like "{{title}}" would resolve to null.
        $template = (string) $this->panel->configValue('display_template', '');
        preg_match_all('/\{\{(\w+)\}\}/', $template, $matches);
        $fieldNames = $matches[1];

        $fields = $collection->fields()->whereIn('column_name', $fieldNames)->get()->keyBy('column_name');

        $columns = [];
        foreach (array_unique($fieldNames) as $fieldName) {
            $field = $fields->get($fieldName);
            $column = TextColumn::make($fieldName)->label($field?->label ?? Str::headline($fieldName));

            // Decimals come back padded to their storage scale (99.000000); show them trimmed.
            if ($field?->eav_cast === EavCast::Decimal) {
                $column->numeric(maxDecimalPlaces: max(0, (int) ($field->settings['scale'] ?? 6)));
            }

            $columns[] = $column;
        }

        if (empty($columns)) {
            $columns[] = TextColumn::make('uuid')->label('ID');
        }

        return $table
            ->query(fn () => $eloquentQuery)
            ->columns($columns)
            ->paginated(false)
            ->heading($this->panel->header_visible ? $this->panel->header_label : null);
    }
}
