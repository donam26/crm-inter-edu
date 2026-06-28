<?php

namespace App\Enums;

enum EventStatus: string
{
    case Scheduled = 'scheduled';
    case Done = 'done';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Đã lên lịch',
            self::Done => 'Đã diễn ra',
            self::Cancelled => 'Đã huỷ',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Scheduled => 'primary',
            self::Done => 'success',
            self::Cancelled => 'danger',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Scheduled;
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
