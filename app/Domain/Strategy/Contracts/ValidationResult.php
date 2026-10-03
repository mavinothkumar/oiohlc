<?php

namespace App\Domain\Strategy\Contracts;

class ValidationResult
{
    public function __construct(
        public readonly bool $isValid,
        public readonly array $errors = [],
        public readonly array $warnings = []
    ) {}

    public static function valid(array $warnings = []): self
    {
        return new self(true, [], $warnings);
    }

    public static function invalid(array $errors, array $warnings = []): self
    {
        return new self(false, $errors, $warnings);
    }
}
