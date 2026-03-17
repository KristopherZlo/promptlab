<?php

namespace App\Services;

use App\Models\PromptVersion;
use Illuminate\Support\Arr;

class StructuredOutputValidator
{
    public function validate(PromptVersion $promptVersion, ?string $outputText): array
    {
        if ($promptVersion->output_type !== 'json') {
            return [
                'format_valid' => true,
                'output_json' => null,
                'error' => null,
            ];
        }

        $decoded = json_decode((string) $outputText, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'format_valid' => false,
                'output_json' => null,
                'error' => 'Output is not valid JSON.',
            ];
        }

        $schema = $promptVersion->output_schema_json ?? [];
        $requiredFields = Arr::wrap($schema['required'] ?? []);

        foreach ($requiredFields as $field) {
            if (! Arr::has($decoded, $field)) {
                return [
                    'format_valid' => false,
                    'output_json' => $decoded,
                    'error' => "Missing required field: {$field}.",
                ];
            }
        }

        foreach (($schema['types'] ?? []) as $field => $expectedType) {
            if (! Arr::has($decoded, $field)) {
                continue;
            }

            $actualValue = Arr::get($decoded, $field);
            $actualType = gettype($actualValue);
            $normalizedType = $actualType === 'integer' ? 'int' : ($actualType === 'boolean' ? 'bool' : $actualType);

            if ($normalizedType !== $expectedType) {
                return [
                    'format_valid' => false,
                    'output_json' => $decoded,
                    'error' => "Field {$field} must be {$expectedType}.",
                ];
            }
        }

        return [
            'format_valid' => true,
            'output_json' => $decoded,
            'error' => null,
        ];
    }
}
