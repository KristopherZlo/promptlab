<?php

$csvUrls = static fn (string $value): array => array_values(array_filter(array_map(
    static fn (string $item): string => trim($item),
    explode(',', $value)
)));

$openAiBaseUrl = rtrim((string) env('OPENAI_BASE_URL', 'https://api.openai.com/v1'), '/');
$anthropicBaseUrl = rtrim((string) env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'), '/');

return [
    'default_model' => env('LLM_DEFAULT_MODEL', 'mock:team-lab-v1'),

    'models' => [
        [
            'value' => 'mock:team-lab-v1',
            'label' => 'Mock / Team Lab V1',
            'driver' => 'mock',
            'available' => true,
        ],
        [
            'value' => 'openai:gpt-4.1-mini',
            'label' => 'OpenAI / GPT-4.1 mini',
            'driver' => 'openai',
            'available' => (bool) env('OPENAI_API_KEY'),
        ],
        [
            'value' => 'openai:gpt-4o-mini',
            'label' => 'OpenAI / GPT-4o mini',
            'driver' => 'openai',
            'available' => (bool) env('OPENAI_API_KEY'),
        ],
    ],

    'connection_base_urls' => [
        'openai' => array_values(array_unique(array_filter([
            $openAiBaseUrl,
            ...$csvUrls((string) env('OPENAI_ALLOWED_BASE_URLS', '')),
        ]))),
        'anthropic' => array_values(array_unique(array_filter([
            $anthropicBaseUrl,
            ...$csvUrls((string) env('ANTHROPIC_ALLOWED_BASE_URLS', '')),
        ]))),
    ],
];
