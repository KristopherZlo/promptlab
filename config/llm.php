<?php

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
];
