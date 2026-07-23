<?php

declare(strict_types=1);

namespace App\Accounting;

enum JournalStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Posted = 'posted';
    case Reversed = 'reversed';
    case Void = 'void';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Approved, self::Void],
            self::Approved => [self::Posted],
            self::Posted => [self::Reversed],
            self::Reversed,
            self::Void => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    /**
     * @throws IllegalTransitionException
     */
    public function transitionTo(self $next): self
    {
        if (! $this->canTransitionTo($next)) {
            throw new IllegalTransitionException($this->value, $next->value);
        }

        return $next;
    }
}
