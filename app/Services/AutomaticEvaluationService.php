<?php

namespace App\Services;

use App\Models\ExperimentRun;
use Illuminate\Support\Str;

class AutomaticEvaluationService
{
    public function evaluateRun(ExperimentRun $run): array
    {
        $run->loadMissing('testCase');

        $testCase = $run->testCase;

        if (! $testCase) {
            return $this->emptyEvaluation();
        }

        $checks = [];

        if (filled($testCase->expected_output)) {
            $checks[] = $this->textContainsCheck((string) $testCase->expected_output, (string) ($run->output_text ?? ''));
        }

        if (! empty($testCase->expected_json ?? [])) {
            $checks[] = $this->jsonSubsetCheck($testCase->expected_json ?? [], $run);
        }

        if ($checks === []) {
            return $this->emptyEvaluation();
        }

        $passedChecks = collect($checks)->where('passed', true)->count();
        $totalChecks = count($checks);
        $passed = $passedChecks === $totalChecks;

        return [
            'configured' => true,
            'passed' => $passed,
            'passed_checks' => $passedChecks,
            'total_checks' => $totalChecks,
            'summary' => $passed
                ? "All {$totalChecks} quality checks passed."
                : "{$passedChecks} of {$totalChecks} quality checks passed.",
            'checks' => $checks,
        ];
    }

    private function textContainsCheck(string $expected, string $actual): array
    {
        $normalizedExpected = $this->normalizeText($expected);
        $normalizedActual = $this->normalizeText($actual);
        $passed = $normalizedExpected !== ''
            && $normalizedActual !== ''
            && str_contains(Str::lower($normalizedActual), Str::lower($normalizedExpected));

        return [
            'key' => 'expected_output',
            'label' => 'Expected text fragment',
            'type' => 'text_contains',
            'passed' => $passed,
            'expected_preview' => trim($expected),
            'actual_preview' => trim($actual),
            'message' => $passed
                ? 'Result contains the expected reference text.'
                : ($normalizedActual === ''
                    ? 'No result was generated for text comparison.'
                    : 'Result does not contain the expected reference text.'),
        ];
    }

    private function jsonSubsetCheck(array $expected, ExperimentRun $run): array
    {
        $actual = $run->output_json;
        $actualPreview = $actual;

        if ($actual === null && filled($run->output_text)) {
            $decoded = json_decode((string) $run->output_text, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $actual = $decoded;
                $actualPreview = $decoded;
            }
        }

        if (! is_array($actual)) {
            return [
                'key' => 'expected_json',
                'label' => 'Expected JSON subset',
                'type' => 'json_subset',
                'passed' => false,
                'expected_preview' => $expected,
                'actual_preview' => $actualPreview ?? ($run->output_text ?? ''),
                'message' => 'The result is not valid JSON, so the expected JSON check could not pass.',
            ];
        }

        $mismatch = $this->jsonSubsetMismatch($expected, $actual);

        return [
            'key' => 'expected_json',
            'label' => 'Expected JSON subset',
            'type' => 'json_subset',
            'passed' => $mismatch === null,
            'expected_preview' => $expected,
            'actual_preview' => $actual,
            'message' => $mismatch ?? 'Result JSON contains the expected keys and values.',
        ];
    }

    private function jsonSubsetMismatch(mixed $expected, mixed $actual, string $path = '$'): ?string
    {
        if (is_array($expected)) {
            if (! is_array($actual)) {
                return "{$path} is not an array/object in the actual output.";
            }

            if (array_is_list($expected)) {
                if (! array_is_list($actual)) {
                    return "{$path} is not a JSON list in the actual output.";
                }

                foreach ($expected as $index => $value) {
                    if (! array_key_exists($index, $actual)) {
                        return "{$path}[{$index}] is missing from the actual output.";
                    }

                    $mismatch = $this->jsonSubsetMismatch($value, $actual[$index], "{$path}[{$index}]");

                    if ($mismatch !== null) {
                        return $mismatch;
                    }
                }

                return null;
            }

            foreach ($expected as $key => $value) {
                if (! array_key_exists($key, $actual)) {
                    return "{$path}.{$key} is missing from the actual output.";
                }

                $mismatch = $this->jsonSubsetMismatch($value, $actual[$key], "{$path}.{$key}");

                if ($mismatch !== null) {
                    return $mismatch;
                }
            }

            return null;
        }

        if ($expected !== $actual) {
            return "{$path} expected ".var_export($expected, true).' but received '.var_export($actual, true).'.';
        }

        return null;
    }

    private function normalizeText(string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $value));
    }

    private function emptyEvaluation(): array
    {
        return [
            'configured' => false,
            'passed' => null,
            'passed_checks' => 0,
            'total_checks' => 0,
            'summary' => null,
            'checks' => [],
        ];
    }
}
