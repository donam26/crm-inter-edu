<?php

namespace App\Enums;

enum ActivityType: string
{
    case Call = 'call';
    case Email = 'email';
    case Meeting = 'meeting';
    case Note = 'note';

    public function label(): string
    {
        return match ($this) {
            self::Call => 'Gọi điện',
            self::Email => 'Email',
            self::Meeting => 'Họp',
            self::Note => 'Ghi chú',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
