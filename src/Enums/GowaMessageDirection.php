<?php

declare(strict_types=1);

namespace Gowa\Laravel\Enums;

enum GowaMessageDirection: string
{
    case Inbound = 'inbound';
    case Outbound = 'outbound';
}
