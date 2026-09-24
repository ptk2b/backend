<?php

namespace App\Rules;

use App\Services\FileSecurityScanner;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SecureFile implements ValidationRule
{
    protected array $allowedExtensions;
    protected int $maxKb;

    /**
     * @param array $allowedExtensions List of allowed extensions, e.g. ['pdf', 'jpg', 'jpeg', 'png']
     * @param int $maxKb Max size in kilobytes, e.g. 3072 for 3MB
     */
    public function __construct(array $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'], int $maxKb = 3072)
    {
        $this->allowedExtensions = $allowedExtensions;
        $this->maxKb = $maxKb;
    }

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null) {
            return;
        }

        $error = FileSecurityScanner::scan($value, $this->allowedExtensions, $this->maxKb);
        if ($error !== null) {
            $fail($error);
        }
    }
}
