<?php

declare(strict_types=1);

namespace Gowa\Laravel\Enums;

enum GowaInstanceStatus: string
{
    case Created    = 'created';
    case Connecting = 'connecting';
    case Open       = 'open';
    case Close      = 'close';

    public function isConnected(): bool
    {
        return $this === self::Open;
    }

    public function label(): string
    {
        return match ($this) {
            self::Created    => 'Created',
            self::Connecting => 'Connecting',
            self::Open       => 'Connected',
            self::Close      => 'Disconnected',
        };
    }
}
