<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chưa làm',
            self::InProgress => 'Đang làm',
            self::Completed => 'Hoàn thành',
            self::Cancelled => 'Đã huỷ',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Pending => 'secondary',
            self::InProgress => 'primary',
            self::Completed => 'success',
            self::Cancelled => 'danger',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Pending, self::InProgress], true);
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
