<?php

declare(strict_types=1);

namespace Flexpik\FilamentStudio\Support;

use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Component;
use Throwable;

/**
 * Builds the initial state for a list of not-yet-mounted schema components.
 *
 * Type-specific settings (field type settingsSchema(), panel type configSchema()) are swapped
 * in when the user picks a type. Their state paths must exist before the browser renders them:
 * a Toggle entangles its path with Alpine, and an undefined path logs "Livewire Entangle Error".
 * This returns every field's declared default (null when it has none), so the whole sub-form
 * can be seeded with one $set().
 */
final class SchemaDefaults
{
    /**
     * @param  array<mixed>  $components
     * @return array<string, mixed>
     */
    public static function from(array $components): array
    {
        $state = [];

        foreach ($components as $component) {
            if ($component instanceof Field) {
                data_set($state, $component->getName(), self::defaultOf($component));

                continue;
            }

            if (! $component instanceof Component) {
                continue;
            }

            try {
                $children = $component->getDefaultChildComponents();
            } catch (Throwable) {
                continue;
            }

            if (! is_array($children) || $children === []) {
                continue;
            }

            $childState = self::from($children);
            $statePath = $component->getStatePath(isAbsolute: false);

            if (filled($statePath)) {
                data_set($state, $statePath, $childState);
            } else {
                $state = array_replace_recursive($state, $childState);
            }
        }

        return $state;
    }

    private static function defaultOf(Field $field): mixed
    {
        try {
            return $field->getDefaultState();
        } catch (Throwable) {
            // Defaults computed from a mounted container (record, Get, livewire) can't be
            // resolved here; null still initialises the path.
            return null;
        }
    }
}
