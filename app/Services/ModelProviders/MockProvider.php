<?php

namespace App\Services\ModelProviders;

use App\Services\ModelProviders\Contracts\LLMProvider;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class MockProvider implements LLMProvider
{
    public function runPrompt(string $compiledPrompt, array $options = []): array
    {
        $startedAt = microtime(true);
        $output = $this->generateOutput($compiledPrompt, $options);
        $latency = (int) round((microtime(true) - $startedAt) * 1000) + 120;

        return [
            'output_text' => $output,
            'model_name' => $options['model'] ?? 'mock:team-lab-v1',
            'token_input' => $this->approximateTokens($compiledPrompt),
            'token_output' => $this->approximateTokens($output),
            'latency_ms' => $latency,
            'raw_response' => [
                'provider' => 'mock',
                'mode' => 'deterministic-fixture',
            ],
        ];
    }

    private function generateOutput(string $compiledPrompt, array $options): string
    {
        $useCaseSlug = (string) ($options['use_case_slug'] ?? '');
        $taskType = (string) ($options['task_type'] ?? '');
        $versionLabel = Str::lower((string) ($options['prompt_version_label'] ?? ''));

        if (($options['output_type'] ?? 'text') === 'json') {
            return $this->jsonOutput($useCaseSlug, $versionLabel, Arr::wrap($options['output_schema']['required'] ?? []));
        }

        return match ($useCaseSlug ?: $taskType) {
            'customer-email-summarization', 'summarization' => $this->summaryOutput($compiledPrompt, $versionLabel),
            'rewrite-for-business-tone', 'rewrite' => $this->rewriteOutput(),
            'meeting-note-summarization' => $this->meetingNotesOutput($compiledPrompt),
            default => $this->genericOutput($compiledPrompt),
        };
    }

    private function jsonOutput(string $useCaseSlug, string $versionLabel, array $requiredFields): string
    {
        $payload = match ($useCaseSlug) {
            'ticket-categorization' => [
                'category' => 'billing',
                'priority' => Str::contains($versionLabel, 'v1') ? 'medium' : 'high',
                'needs_human_review' => true,
                'reason' => 'Customer reports repeated payment failure after two retry attempts.',
            ],
            default => collect($requiredFields)->mapWithKeys(fn ($field) => [$field => 'value'])->all(),
        };

        if (Str::contains($versionLabel, 'v1')) {
            unset($payload['reason']);
        }

        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    private function summaryOutput(string $compiledPrompt, string $versionLabel): string
    {
        $excerpt = $this->excerpt($compiledPrompt);

        if (Str::contains($versionLabel, 'action')) {
            return "Core issue: Customer cannot complete payment after repeated retries.\nUrgency: High due to service disruption and billing concern.\nRequested action: Review payment logs and contact the customer with a confirmed next step.\nShort summary: {$excerpt}";
        }

        if (Str::contains($versionLabel, 'structured') || Str::contains($versionLabel, 'v3')) {
            return "- Main issue: payment retry failed twice for the same account.\n- Customer expectation: confirmation that billing is fixed or escalated today.\n- Recommended next step: route to billing operations and send a clear follow-up.";
        }

        return "The customer is having a payment issue and needs support. They expect a clear follow-up from the team. Summary context: {$excerpt}";
    }

    private function rewriteOutput(): string
    {
        return "Thanks for raising this. I reviewed the draft and rewrote it into a more direct business tone. The revised version keeps the key request, removes filler, and makes the next action explicit.";
    }

    private function meetingNotesOutput(string $compiledPrompt): string
    {
        $excerpt = $this->excerpt($compiledPrompt);

        return "Key decisions:\n- Keep the pilot focused on customer support workflows.\n- Use one approved prompt per use case in production.\n\nAction items:\n- Product team to finalize success criteria.\n- Engineering to validate format checks.\n\nOwners:\n- Product lead\n- AI engineer\n\nDeadlines:\n- Pilot review next Friday\n\nContext:\n{$excerpt}";
    }

    private function genericOutput(string $compiledPrompt): string
    {
        return "Structured response generated from mock provider.\n\nKey points:\n- Output follows the requested task shape.\n- This provider is deterministic for local development.\n- Prompt excerpt: ".$this->excerpt($compiledPrompt);
    }

    private function excerpt(string $text): string
    {
        $collapsed = preg_replace('/\s+/', ' ', trim($text)) ?? trim($text);

        return Str::limit($collapsed, 180, '...');
    }

    private function approximateTokens(string $content): int
    {
        return max(1, (int) ceil(str_word_count($content) * 1.35));
    }
}
