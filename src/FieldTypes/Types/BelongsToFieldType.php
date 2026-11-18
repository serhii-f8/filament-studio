<?php

namespace Flexpik\FilamentStudio\FieldTypes\Types;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Filters\SelectFilter;
use Flexpik\FilamentStudio\Enums\EavCast;
use Flexpik\FilamentStudio\FieldTypes\AbstractFieldType;
use Flexpik\FilamentStudio\Models\StudioCollection;
use Flexpik\FilamentStudio\Services\LocaleResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;

class BelongsToFieldType extends AbstractFieldType
{
    public static string $key = 'belongs_to';

    public static string $label = 'Belongs To';

    public static string $icon = 'heroicon-o-arrow-top-right-on-square';

    public static EavCast $eavCast = EavCast::Text;

    public static string $category = 'relational';

    public static function settingsSchema(): array
    {
        return [
            TextInput::make('related_collection')->label('Related Collection Slug')->required()->placeholder('e.g. categories')->helperText('The collection this field references. Creates a foreign key relationship.'),
            TextInput::make('display_field')->label('Display Field')->default('name')->placeholder('name')->helperText('Which field from the related collection to display in the dropdown.'),
            Toggle::make('searchable')->label('Searchable')->default(true)->helperText('Enable type-ahead search in the relationship dropdown.'),
            Toggle::make('preload')->label('Preload Options')->default(true)->helperText('Load all related records on page load. Recommended for small datasets.'),
            Toggle::make('allow_create')->label('Allow Creating New Records')->default(false)->helperText('Allow creating new related records directly from the dropdown.'),
            Toggle::make('tenant_scoped')->label('Tenant Scoped')->default(true)->helperText('Only show related records belonging to the current tenant.'),
            Select::make('on_delete')->options(['set_null' => 'Set Null', 'restrict' => 'Restrict'])->default('set_null')->helperText('What happens to this record when the related record is deleted.'),
            Select::make('on_update')->options(['cascade' => 'Cascade', 'no_action' => 'No Action'])->default('cascade')->helperText('What happens to this record when the related record\'s key changes.'),
        ];
    }

    public function toFilamentComponent(): Component
    {
        $select = Select::make($this->field->column_name);

        $relatedSlug = $this->setting('related_collection');

        if (empty($relatedSlug) && $this->referencesUser()) {
            return $select->options(fn (mixed $state): array => filled($state)
                ? [$state => static::userLabel($state)]
                : []);
        }

        if (empty($relatedSlug)) {
            $select->options([])
                ->disabled()
                ->placeholder('No related collection configured');

            return $select;
        }

        $displayField = $this->setting('display_field', 'name');

        $select->options(function () use ($relatedSlug, $displayField): array {
            $collection = StudioCollection::where('slug', $relatedSlug)->first();

            if (! $collection) {
                return [];
            }

            $field = $collection->fields()->where('column_name', $displayField)->first();

            if (! $field) {
                return [];
            }

            $valCol = $field->eav_cast->column();

            $locale = app(LocaleResolver::class)
                ->defaultLocale($collection);

            return $collection->records()
                ->join('studio_values', function ($join) use ($field, $locale) {
                    $join->on('studio_records.id', '=', 'studio_values.record_id')
                        ->where('studio_values.field_id', '=', $field->id)
                        ->where('studio_values.locale', '=', $locale);
                })
                ->pluck("studio_values.{$valCol}", 'studio_records.id')
                ->filter()
                ->toArray();
        });

        if ($this->setting('searchable', true)) {
            $select->searchable();
        }

        if ($this->setting('preload', true)) {
            $select->preload();
        }

        return $select;
    }

    public function toTableColumn(): ?Column
    {
        $column = TextColumn::make($this->field->column_name)
            ->label($this->field->label ?? $this->field->column_name);

        if (empty($this->setting('related_collection')) && $this->referencesUser()) {
            $labels = [];

            $column->formatStateUsing(function (mixed $state) use (&$labels): ?string {
                if (blank($state)) {
                    return null;
                }

                return $labels[$state] ??= static::userLabel($state);
            });
        }

        return $column;
    }

    /**
     * System "created by" / "updated by" fields store an auth user id rather than a studio record.
     */
    protected function referencesUser(): bool
    {
        return $this->field->auto_fill_value === '$CURRENT_USER';
    }

    protected static function userLabel(mixed $id): string
    {
        /** @var class-string<Model> $model */
        $model = config('auth.providers.users.model', User::class);
        $user = $model::query()->find($id);

        return (string) ($user?->getAttribute('name') ?? $user?->getAttribute('email') ?? $id);
    }

    public function toFilter(): ?BaseFilter
    {
        return SelectFilter::make($this->field->column_name)
            ->label($this->field->label ?? $this->field->column_name)
            ->searchable();
    }
}
