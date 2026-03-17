<?php

namespace App\Services\ModelProviders\Contracts;

interface LLMProvider
{
    public function runPrompt(string $compiledPrompt, array $options = []): array;
}
