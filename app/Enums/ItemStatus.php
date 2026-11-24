<?php

namespace App\Enums;

enum ItemStatus: string
{
    case Active = 'active';
    case Sold = 'sold';
    case Hidden = 'hidden';
}
