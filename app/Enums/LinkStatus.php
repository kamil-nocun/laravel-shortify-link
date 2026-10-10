<?php

namespace App\Enums;

enum LinkStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Expired = 'expired';
    case Disabled = 'disabled';
}
