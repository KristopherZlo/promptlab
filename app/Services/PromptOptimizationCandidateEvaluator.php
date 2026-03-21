<?php

namespace App\Services;

use App\Models\ExperimentRun;
use App\Models\PromptOptimizationRun;
use App\Models\PromptVersion;
use App\Models\TestCase;
use Illuminate\Support\Str;
use RuntimeException;

class PromptOptimizationCandidateEvaluator
{
    public function __construct(
        private readonly AutomaticEvaluationService $automaticEvaluation,
        private readonly LLMProviderManager $providers,
        private readonly PromptCompiler $compiler,
        private readonly StructuredOutputValidator $validator,
    ) {
    }

    public function evaluate(PromptOptimizationRun $run, TestCase $testCase, array $candidate): array
    {
        $run->loadMissing('sourceVersion.promptTemplate.useCase');

        $sourceVersion = $run->sourceVersion;
        $promptTemplate = $sourceVersion?->promptTemplate;

        if (! $sourceVersion || ! $promptTemplate) {
            throw new RuntimeException('Optimization source version is unavailable.');
        }

        $candidateVersion = new PromptVersion([
            'team_id' => $run->team_id,
            'prompt_template_id' => $promptTemplate->id,
            'version_label' => 'gepa-candidate',
            'system_prompt' => $this->candidateValue($candidate, 'system_prompt', $sourceVersion->system_prompt),
            'user_prompt_template' => $this->candidateValue($candidate, 'user_prompt_template', $sourceVersion->user_prompt_template),
            'variables_schema' => $sourceVersion->variables_schema ?? [],
            'output_type' => $sourceVersion->output_type,
            'output_schema_json' => $sourceVersion->output_schema_json ?? [],
            'preferred_model' => $run->requested_model_name,
        ]);
        $candidateVersion->setRelation('promptTemplate', $promptTemplate);

        $compiled = $this->compiler->compile(
            $candidateVersion,
            $testCase->input_text,
            $testCase->variables_json ?? [],
        );

        if (! empty($compiled['missing'])) {
            throw new RuntimeException('Missing required variables: '.implode(', ', $compiled['missing']).'.');
        }

        $response = $this->providers->runPrompt($compiled['final_prompt'], [
            'team_id' => $run->team_id,
            'model' => $run->requested_model_name,
            'temperature' => data_get($run->config_json, 'evaluation.temperature', config('gepa.evaluation_temperature')),
            'max_tokens' => data_get($run->config_json, 'evaluation.max_tokens', config('gepa.evaluation_max_tokens')),
            'task_type' => $promptTemplate->task_type,
            'use_case_slug' => $promptTemplate->useCase?->slug,
            'output_type' => $candidateVersion->output_type,
            'output_schema' => $candidateVersion->output_schema_json,
            'prompt_version_label' => 'gepa-candidate',
            'system_prompt' => $compiled['system_prompt'],
            'user_prompt' => $compiled['user_prompt'],
        ]);

        $validation = $this->validator->validate($candidateVersion, (string) ($response['output_text'] ?? ''));

        $syntheticRun = new ExperimentRun([
            'team_id' => $run->team_id,
            'prompt_version_id' => $sourceVersion->id,
            'test_case_id' => $testCase->id,
            'input_text' => $testCase->input_text,
            'variables_json' => $testCase->variables_json ?? [],
            'compiled_prompt' => $compiled['final_prompt'],
            'output_text' => $response['output_text'] ?? '',
            'output_json' => $validation['output_json'],
            'latency_ms' => $response['latency_ms'] ?? null,
            'token_input' => $response['token_input'] ?? null,
            'token_output' => $response['token_output'] ?? null,
            'format_valid' => $validation['format_valid'],
            'error_message' => $validation['error'],
            'status' => ($validation['format_valid'] ?? false) ? 'success' : 'invalid_format',
        ]);
        $syntheticRun->setRelation('testCase', $testCase);

        $automatic = $this->automaticEvaluation->evaluateRun($syntheticRun);
        $automaticScore = $automatic['configured'] && $automatic['total_checks'] > 0
            ? ($automatic['passed_checks'] / $automatic['total_checks'])
            : 0.0;
        $formatScore = ($validation['format_valid'] ?? false) ? 1.0 : 0.0;
        $score = round(($automaticScore * 0.85) + ($formatScore * 0.15), 4);

        $feedback = collect([
            $automatic['summary'] ?? null,
            $validation['error'] ?? null,
            ! empty($automatic['checks'][0]['message'] ?? null) ? $automatic['checks'][0]['message'] : null,
        ])->filter()->implode(' ');

        return [
            'score' => $score,
            'side_info' => [
                'scores' => [
                    'automatic_checks' => round($automaticScore, 4),
                    'format_valid' => round($formatScore, 4),
                    'score' => $score,
                ],
                'Case' => $testCase->title,
                'Input' => Str::limit($testCase->input_text, 1200, '...'),
                'Expected' => ! empty($testCase->expected_json)
                    ? $testCase->expected_json
                    : (string) ($testCase->expected_output ?? ''),
                'Output' => $validation['output_json'] ?? (string) ($response['output_text'] ?? ''),
                'Feedback' => $feedback !== '' ? $feedback : 'Improve the candidate so automatic checks pass more consistently.',
                'Validation error' => $validation['error'],
                'Latency ms' => $response['latency_ms'] ?? null,
            ],
        ];
    }

    private function candidateValue(array $candidate, string $key, ?string $fallback): string
    {
        $value = $candidate[$key] ?? $fallback ?? '';

        return is_string($value) ? $value : (string) $value;
    }
}
