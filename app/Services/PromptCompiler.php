<?php

namespace App\Services;

use App\Models\PromptVersion;
use Illuminate\Support\Collection;

class PromptCompiler
{
    public function compile(PromptVersion $promptVersion, string $inputText, array $variables = []): array
    {
        $schema = collect($promptVersion->variables_schema ?? [])
            ->filter(fn ($field) => is_array($field) && filled($field['name'] ?? null))
            ->values();

        $resolvedVariables = ['input_text' => trim($inputText)] + $variables;
        $missing = [];

        $schema->each(function (array $field) use (&$resolvedVariables, &$missing): void {
            $name = $field['name'];

            if (
                ! array_key_exists($name, $resolvedVariables)
                && array_key_exists('default', $field)
                && $field['default'] !== null
                && $field['default'] !== ''
            ) {
                $resolvedVariables[$name] = $field['default'];
            }

            if (($field['required'] ?? false) && blank($resolvedVariables[$name] ?? null)) {
                $missing[] = $name;
            }
        });

        $systemPrompt = $this->replaceVariables($promptVersion->system_prompt ?? '', $resolvedVariables);
        $userPrompt = $this->replaceVariables($promptVersion->user_prompt_template, $resolvedVariables);

        return [
            'system_prompt' => trim($systemPrompt),
            'user_prompt' => trim($userPrompt),
            'final_prompt' => trim($this->assemblePrompt($systemPrompt, $userPrompt)),
            'variables' => $resolvedVariables,
            'missing' => array_values(array_unique($missing)),
        ];
    }

    private function replaceVariables(string $content, array $variables): string
    {
        return preg_replace_callback('/{{\s*([a-zA-Z0-9_\.]+)\s*}}/', function (array $matches) use ($variables): string {
            $value = data_get($variables, $matches[1], '');

            if (is_array($value)) {
                return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
            }

            return (string) $value;
        }, $content) ?? $content;
    }

    private function assemblePrompt(string $systemPrompt, string $userPrompt): string
    {
        $parts = new Collection();

        if (filled(trim($systemPrompt))) {
            $parts->push("SYSTEM:\n".trim($systemPrompt));
        }

        $parts->push("USER:\n".trim($userPrompt));

        return $parts->implode("\n\n");
    }
}
