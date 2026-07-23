<?php

declare(strict_types=1);

namespace App\Accounting;

final class IdempotencyKey
{
    private function __construct(
        private readonly string $value,
    ) {}

    public static function fromSourceIdentity(SourceIdentity $identity): self
    {
        // Deterministic hash from the source identity tuple
        $hash = hash('sha256', $identity->toKey());

        return new self($hash);
    }

    public function value(): string
    {
        return $this->value;
    }
}
