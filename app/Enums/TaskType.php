<?php

namespace App\Enums;

enum TaskType: string
{
    case Call = 'call';
    case Email = 'email';
    case Meeting = 'meeting';
    case FollowUp = 'follow_up';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Call => 'Gọi điện',
            self::Email => 'Email',
            self::Meeting => 'Họp',
            self::FollowUp => 'Theo dõi',
            self::Other => 'Khác',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
