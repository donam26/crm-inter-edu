<?php

namespace App\Enums;

enum DealStage: string
{
    case Lead = 'lead';
    case Proposal = 'proposal';
    case Negotiation = 'negotiation';
    case ClosedWon = 'closed_won';
    case ClosedLost = 'closed_lost';

    public function label(): string
    {
        return match ($this) {
            self::Lead => 'Mới',
            self::Proposal => 'Chào hàng',
            self::Negotiation => 'Đàm phán',
            self::ClosedWon => 'Thắng',
            self::ClosedLost => 'Mất',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Lead => 'secondary',
            self::Proposal => 'primary',
            self::Negotiation => 'warning',
            self::ClosedWon => 'success',
            self::ClosedLost => 'danger',
        };
    }

    public function isClosed(): bool
    {
        return in_array($this, [self::ClosedWon, self::ClosedLost], true);
    }

    public function isOpen(): bool
    {
        return ! $this->isClosed();
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
