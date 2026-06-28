<?php

namespace App\Enums;

enum EventType: string
{
    case Meeting = 'meeting';
    case Call = 'call';
    case Onsite = 'onsite';
    case Online = 'online';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Meeting => 'Họp',
            self::Call => 'Gọi điện',
            self::Onsite => 'Tại văn phòng',
            self::Online => 'Trực tuyến',
            self::Other => 'Khác',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
