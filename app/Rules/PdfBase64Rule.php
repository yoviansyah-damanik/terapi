<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Validasi bahwa nilai adalah string base64 file PDF yang valid (magic bytes %PDF) */
class PdfBase64Rule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $decoded = base64_decode(substr($value, 0, 32), strict: true);

        if ($decoded === false || !str_starts_with($decoded, '%PDF')) {
            $fail("Field :attribute harus berupa file PDF yang valid dan dikodekan base64.");
        }
    }
}
