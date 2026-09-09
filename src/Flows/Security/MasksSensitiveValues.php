<?php

declare(strict_types=1);

namespace Flexpik\FilamentStudio\Flows\Security;

class MasksSensitiveValues
{
    /**
     * Recursively walk $payload and replace values whose keys match any of the
     * configured regex patterns with '***'.
     *
     * $secretValues are additionally scrubbed wherever they appear inside string
     * leaves. Key patterns alone cannot protect a resolved flow secret: it may be
     * interpolated into a url, or into a field named something innocuous.
     *
     * @param  array<mixed>  $payload
     * @param  array<string>  $secretValues
     * @return array<mixed>
     */
    public function mask(array $payload, array $secretValues = []): array
    {
        $patterns = config('filament-studio.flows.sensitive_key_patterns', [
            '/password/i',
            '/token/i',
            '/secret/i',
            '/api[_-]?key/i',
            '/authorization/i',
            '/bearer/i',
        ]);

        $masked = $this->walkAndMask($payload, $patterns);

        $secretValues = array_values(array_filter(
            array_map('strval', $secretValues),
            fn (string $value): bool => $value !== '',
        ));

        return $secretValues === [] ? $masked : $this->scrubValues($masked, $secretValues);
    }

    /**
     * @param  array<mixed>  $payload
     * @param  array<string>  $secretValues
     * @return array<mixed>
     */
    private function scrubValues(array $payload, array $secretValues): array
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->scrubValues($value, $secretValues);

                continue;
            }

            if (is_string($value)) {
                $payload[$key] = str_replace($secretValues, '***', $value);
            }
        }

        return $payload;
    }

    /**
     * @param  array<mixed>  $payload
     * @param  array<string>  $patterns
     * @return array<mixed>
     */
    private function walkAndMask(array $payload, array $patterns): array
    {
        foreach ($payload as $key => $value) {
            $keyStr = (string) $key;
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $keyStr)) {
                    $payload[$key] = '***';
                    break; // key matched, no need to recurse into it
                }
            }
            // If not masked and value is array, recurse
            if ($payload[$key] !== '***' && is_array($value)) {
                $payload[$key] = $this->walkAndMask($value, $patterns);
            }
        }

        return $payload;
    }
}
