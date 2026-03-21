<?php

namespace App\Services;

use App\Models\PromptOptimizationRun;
use Illuminate\Support\Arr;
use RuntimeException;

class PromptOptimizationReflectionService
{
    public function __construct(
        private readonly LLMProviderManager $providers,
    ) {
    }

    public function reflect(PromptOptimizationRun $run, string|array $prompt): string
    {
        $run->loadMissing('promptTemplate.useCase');

        [$systemPrompt, $userPrompt, $compiledPrompt] = $this->normalizePrompt($prompt);

        $response = $this->providers->runPrompt($compiledPrompt, [
            'team_id' => $run->team_id,
            'model' => $run->requested_model_name,
            'temperature' => data_get($run->config_json, 'reflection.temperature', config('gepa.reflection_temperature')),
            'max_tokens' => data_get($run->config_json, 'reflection.max_tokens', config('gepa.reflection_max_tokens')),
            'task_type' => $run->promptTemplate?->task_type ?? 'generation',
            'use_case_slug' => $run->promptTemplate?->useCase?->slug,
            'output_type' => 'text',
            'prompt_version_label' => 'gepa-reflection',
            'system_prompt' => $systemPrompt,
            'user_prompt' => $userPrompt,
        ]);

        return trim((string) ($response['output_text'] ?? ''));
    }

    private function normalizePrompt(string|array $prompt): array
    {
        if (is_string($prompt)) {
            return ['', $prompt, $prompt];
        }

        $systemParts = [];
        $conversationParts = [];

        foreach ($prompt as $message) {
            $role = strtolower((string) Arr::get($message, 'role', 'user'));
            $content = $this->normalizeContent(Arr::get($message, 'content', ''));

            if ($content === '') {
                continue;
            }

            if ($role === 'system') {
                $systemParts[] = $content;
                continue;
            }

            $conversationParts[] = strtoupper($role).":\n".$content;
        }

        $systemPrompt = trim(implode("\n\n", $systemParts));
        $userPrompt = trim(implode("\n\n", $conversationParts));

        if ($userPrompt === '') {
            $userPrompt = trim(implode("\n\n", array_filter([$systemPrompt])));
            $systemPrompt = '';
        }

        if ($userPrompt === '') {
            throw new RuntimeException('GEPA reflection prompt was empty.');
        }

        $compiledPrompt = trim(implode("\n\n", array_filter([
            $systemPrompt !== '' ? "SYSTEM:\n{$systemPrompt}" : null,
            "USER:\n{$userPrompt}",
        ])));

        return [$systemPrompt, $userPrompt, $compiledPrompt];
    }

    private function normalizeContent(mixed $content): string
    {
        if (is_string($content)) {
            return trim($content);
        }

        if (is_array($content) || is_object($content)) {
            return trim((string) json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        return trim((string) $content);
    }
}
