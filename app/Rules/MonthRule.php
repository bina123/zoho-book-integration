<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class MonthRule implements ValidationRule
{
    public const PATTERN = '/^\d{4}-(0[1-9]|1[0-2])$/';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match(self::PATTERN, $value)) {
            $fail('The :attribute must be a valid YYYY-MM month.');
        }
    }
}
