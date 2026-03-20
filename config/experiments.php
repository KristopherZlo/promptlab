<?php

return [
    'jobs' => [
        'execute_run' => [
            'tries' => (int) env('EXPERIMENT_RUN_JOB_TRIES', 3),
            'backoff' => array_values(array_map(
                static fn (string $value): int => max(0, (int) trim($value)),
                array_filter(explode(',', (string) env('EXPERIMENT_RUN_JOB_BACKOFF', '10,30,90')))
            )),
            'timeout' => (int) env('EXPERIMENT_RUN_JOB_TIMEOUT', 120),
        ],
    ],
];
