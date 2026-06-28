<?php

namespace App\Enums;

enum LeadStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Qualified = 'qualified';
    case Proposal = 'proposal';
    case Negotiation = 'negotiation';
    case Won = 'won';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Mới',
            self::Contacted => 'Đã liên hệ',
            self::Qualified => 'Đã lọc',
            self::Proposal => 'Đã chào hàng',
            self::Negotiation => 'Đang đàm phán',
            self::Won => 'Thắng',
            self::Lost => 'Mất',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
