<?php

declare(strict_types=1);

namespace Gowa\Laravel\Enums;

enum GowaMessageStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Read = 'read';
    case Failed = 'failed';
}
