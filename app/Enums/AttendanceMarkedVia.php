<?php

namespace App\Enums;

enum AttendanceMarkedVia: string
{
    case AdminPanel = 'admin_panel';
    case Manual = 'manual';
    case MobileApp = 'mobile_app';
}
