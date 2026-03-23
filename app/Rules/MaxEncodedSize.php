<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class MaxEncodedSize implements ValidationRule
{
    public function __construct(private readonly int $maxBytes)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null) {
            return;
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($encoded === false) {
            return;
        }

        if (strlen($encoded) <= $this->maxBytes) {
            return;
        }

        $fail("The {$attribute} field must not exceed {$this->kilobytes()} KB when encoded.");
    }

    private function kilobytes(): int
    {
        return (int) ceil($this->maxBytes / 1024);
    }
}
