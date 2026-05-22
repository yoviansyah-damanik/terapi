<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Validasi bahwa nilai adalah string base64 gambar PNG atau JPG yang valid */
class ImageBase64Rule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $decoded = base64_decode($value, strict: true);

        if ($decoded === false) {
            $fail("Field :attribute harus berupa string base64 yang valid.");
            return;
        }

        $isPng = str_starts_with($decoded, "\x89PNG");
        $isJpg = str_starts_with($decoded, "\xFF\xD8\xFF");

        if (!$isPng && !$isJpg) {
            $fail("Field :attribute harus berupa gambar PNG atau JPG yang dikodekan base64.");
        }
    }
}
