<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** A Ghanaian phone number as it is dialled locally: exactly 10 digits, starting with 0 (for example 0244123456). */
class PhoneNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^0[0-9]{9}$/', $value)) {
            $fail('The :attribute must be 10 digits starting with 0, for example 0244123456.');
        }
    }
}
