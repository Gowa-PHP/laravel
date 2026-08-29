<?php

declare(strict_types=1);

namespace Gowa\Laravel\Models;

use Gowa\Laravel\Enums\GowaMessageDirection;
use Gowa\Laravel\Enums\GowaMessageStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single WhatsApp message — inbound or outbound.
 *
 * @property int $id
 * @property int $instance_id
 * @property int|null $conversation_id
 * @property string $message_id
 * @property GowaMessageDirection $direction
 * @property GowaMessageStatus $status
 * @property string $type
 * @property string|null $body
 * @property string|null $media_url
 * @property string|null $media_mime
 * @property string|null $reply_to
 * @property array<string, mixed>|null $meta
 * @property \Carbon\CarbonImmutable|null $sent_at
 * @property \Carbon\CarbonImmutable|null $delivered_at
 * @property \Carbon\CarbonImmutable|null $read_at
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 */
class GowaMessage extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'direction'    => GowaMessageDirection::class,
            'status'       => GowaMessageStatus::class,
            'meta'         => 'array',
            'sent_at'      => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
            'read_at'      => 'immutable_datetime',
        ];
    }

    public function getTable(): string
    {
        return config('gowa.table_names.messages', 'gowa_messages');
    }

    /** @return BelongsTo<GowaInstance, GowaMessage> */
    public function instance(): BelongsTo
    {
        return $this->belongsTo(config('gowa.models.instance'), 'instance_id');
    }

    /** @return BelongsTo<GowaConversation, GowaMessage> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(config('gowa.models.conversation'), 'conversation_id');
    }

    public function isInbound(): bool
    {
        return $this->direction === GowaMessageDirection::Inbound;
    }

    public function isOutbound(): bool
    {
        return $this->direction === GowaMessageDirection::Outbound;
    }
}
