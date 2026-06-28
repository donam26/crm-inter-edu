<?php

namespace App\Enums;

enum SchoolLevel: string
{
    case MamNon = 'mam_non';
    case TieuHoc = 'tieu_hoc';
    case THCS = 'thcs';
    case THPT = 'thpt';
    case LienCap = 'lien_cap';
    case Khac = 'khac';

    public function label(): string
    {
        return match ($this) {
            self::MamNon => 'Mầm non',
            self::TieuHoc => 'Tiểu học',
            self::THCS => 'THCS',
            self::THPT => 'THPT',
            self::LienCap => 'Liên cấp',
            self::Khac => 'Khác',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
