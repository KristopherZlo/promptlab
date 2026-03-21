<?php

return [
    'python_binary' => env('GEPA_PYTHON_BINARY', storage_path('app/gepa-runtime/python311/python.exe')),
    'script_path' => env('GEPA_SCRIPT_PATH', base_path('scripts/gepa_optimize_prompt.py')),
    'requirements_path' => env('GEPA_REQUIREMENTS_PATH', base_path('scripts/gepa-runtime-requirements.txt')),
    'runtime_root' => env('GEPA_RUNTIME_ROOT', storage_path('app/gepa-runtime')),
    'run_root' => env('GEPA_RUN_ROOT', storage_path('app/gepa-runtime/runs')),
    'default_budget_metric_calls' => (int) env('GEPA_DEFAULT_BUDGET', 18),
    'max_budget_metric_calls' => (int) env('GEPA_MAX_BUDGET', 60),
    'reflection_temperature' => (float) env('GEPA_REFLECTION_TEMPERATURE', 0.3),
    'reflection_max_tokens' => (int) env('GEPA_REFLECTION_MAX_TOKENS', 1400),
    'evaluation_temperature' => (float) env('GEPA_EVALUATION_TEMPERATURE', 0.2),
    'evaluation_max_tokens' => (int) env('GEPA_EVALUATION_MAX_TOKENS', 900),
    'job_timeout_seconds' => (int) env('GEPA_JOB_TIMEOUT', 3600),
    'optimization_process_timeout_seconds' => (int) env('GEPA_PROCESS_TIMEOUT', 3600),
    'installer_process_timeout_seconds' => (int) env('GEPA_INSTALL_PROCESS_TIMEOUT', 900),
    'download_timeout_seconds' => (int) env('GEPA_DOWNLOAD_TIMEOUT', 240),
    'python_embed_version' => env('GEPA_PYTHON_EMBED_VERSION', '3.11.9'),
    'python_embed_url' => env('GEPA_PYTHON_EMBED_URL', 'https://www.python.org/ftp/python/3.11.9/python-3.11.9-embed-amd64.zip'),
    'python_embed_sha256' => env('GEPA_PYTHON_EMBED_SHA256', '009d6bf7e3b2ddca3d784fa09f90fe54336d5b60f0e0f305c37f400bf83cfd3b'),
    'get_pip_url' => env('GEPA_GET_PIP_URL', 'https://bootstrap.pypa.io/get-pip.py'),
    'get_pip_sha256' => env('GEPA_GET_PIP_SHA256', 'feba1c697df45be1b539b40d93c102c9ee9dde1d966303323b830b06f3fbca3c'),
];
