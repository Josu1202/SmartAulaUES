<?php

namespace App\Enums;

enum AttendanceMethod: string
{
    case Nfc = 'nfc';
    case Qr = 'qr';
    case Link = 'link';
    case Manual = 'manual';
}
