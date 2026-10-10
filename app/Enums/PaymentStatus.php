<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Open = 'open';
    case Paid = 'paid';
    case PaymentFailed = 'payment_failed';
    case Void = 'void';
    case Uncollectible = 'uncollectible';
}
