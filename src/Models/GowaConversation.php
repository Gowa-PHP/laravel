<?php

declare(strict_types=1);

namespace Gowa\Laravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A conversation thread with a contact on a given GOWA instance.
 *
 * @property int $id
 * @property int $instance_id
 * @property string $contact_jid
 * @property string|null $contact_name
 * @property string|null $contact_phone
 * @property array<string, mixed>|null $meta
 * @property \Carbon\CarbonImmutable|null $last_message_at
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 */
class GowaConversation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'meta'            => 'array',
            'last_message_at' => 'immutable_datetime',
        ];
    }

    public function getTable(): string
    {
        return config('gowa.table_names.conversations', 'gowa_conversations');
    }

    /** @return BelongsTo<GowaInstance, GowaConversation> */
    public function instance(): BelongsTo
    {
        return $this->belongsTo(config('gowa.models.instance'), 'instance_id');
    }

    /** @return HasMany<GowaMessage> */
    public function messages(): HasMany
    {
        return $this->hasMany(config('gowa.models.message'), 'conversation_id');
    }
}
