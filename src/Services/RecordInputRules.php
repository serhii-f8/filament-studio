<?php

namespace Flexpik\FilamentStudio\Services;

use Flexpik\FilamentStudio\Models\StudioCollection;
use Flexpik\FilamentStudio\Models\StudioField;
use Flexpik\FilamentStudio\Models\StudioFieldOption;
use Flexpik\FilamentStudio\Rules\EavUniqueRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Validation rules for record data written outside the Filament form (REST API, MCP).
 *
 * Mirrors what the form enforces: required/nullable, the storage type, the field's
 * custom rules, defined select options and the "Unique" setting.
 */
class RecordInputRules
{
    /** Field types whose value must be one of the field's defined options. */
    public const SINGLE_OPTION_TYPES = ['select', 'radio'];

    /** Field types whose value is a list of the field's defined options. */
    public const MULTI_OPTION_TYPES = ['multi_select', 'checkbox_list'];

    /**
     * @param  Collection<int, StudioField>  $fields
     * @param  'store'|'update'  $mode
     * @return array<string, array<int, string|ValidationRule|\Stringable>>
     */
    public static function for(StudioCollection $collection, Collection $fields, string $mode, ?int $ignoreRecordId = null): array
    {
        $rules = [];

        foreach ($fields as $field) {
            if ($field->is_system) {
                continue;
            }

            $key = 'data.'.$field->column_name;

            $base = $mode === 'store'
                ? ($field->is_required ? ['required'] : ['nullable'])
                : ['sometimes'];

            $fieldRules = array_merge(
                $base,
                static::typeRules($field),
                is_array($field->validation_rules) ? $field->validation_rules : [],
            );

            if ($field->is_unique) {
                $fieldRules[] = new EavUniqueRule($field, $collection, $collection->tenant_id, $ignoreRecordId);
            }

            $options = static::allowedOptions($field);

            if ($options !== null && in_array($field->field_type, self::SINGLE_OPTION_TYPES, true)) {
                $fieldRules[] = Rule::in($options);
            }

            $rules[$key] = $fieldRules;

            if ($options !== null && in_array($field->field_type, self::MULTI_OPTION_TYPES, true)) {
                $rules[$key.'.*'] = [Rule::in($options)];
            }
        }

        return $rules;
    }

    /**
     * @return array<int, string>
     */
    public static function typeRules(StudioField $field): array
    {
        return match ($field->eav_cast->value) {
            'integer' => ['integer'],
            'decimal' => ['numeric'],
            'boolean' => ['boolean'],
            'datetime' => ['date'],
            'json' => ['array'],
            default => ['string'],
        };
    }

    /**
     * The option values a selection field accepts, or null when it accepts anything
     * (options come from another collection, new options may be created, or none are defined).
     *
     * @return array<int, string>|null
     */
    public static function allowedOptions(StudioField $field): ?array
    {
        if (! in_array($field->field_type, [...self::SINGLE_OPTION_TYPES, ...self::MULTI_OPTION_TYPES], true)) {
            return null;
        }

        $settings = $field->settings ?? [];

        if (($settings['options_source'] ?? 'static') !== 'static' || ($settings['allow_create'] ?? false)) {
            return null;
        }

        $options = StudioFieldOption::query()
            ->where('field_id', $field->id)
            ->pluck('value')
            ->map(fn ($value) => (string) $value)
            ->all();

        return $options === [] ? null : $options;
    }
}
