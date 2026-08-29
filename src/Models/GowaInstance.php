<?php

declare(strict_types=1);

namespace Gowa\Laravel\Models;

use Gowa\Laravel\Enums\GowaInstanceStatus;
use Gowa\Sdk\Config;
use Gowa\Sdk\GowaClient;
use Gowa\Sdk\Security\WebhookSignature;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;

/**
 * A paired WhatsApp device managed by the GOWA server.
 *
 * @property int $id
 * @property string $name
 * @property string $device_id
 * @property GowaInstanceStatus $status
 * @property string|null $phone_number
 * @property string|null $webhook_secret
 * @property array<string, mixed>|null $meta
 * @property \Carbon\CarbonImmutable|null $connected_at
 * @property \Carbon\CarbonImmutable|null $last_seen_at
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 */
class GowaInstance extends Model
{
    protected $fillable = [
        'name',
        'device_id',
        'status',
        'phone_number',
        'webhook_secret',
        'meta',
        'connected_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'status'       => GowaInstanceStatus::class,
            'meta'         => 'array',
            'connected_at' => 'immutable_datetime',
            'last_seen_at' => 'immutable_datetime',
        ];
    }

    public function getTable(): string
    {
        return config('gowa.table_names.instances', 'gowa_instances');
    }

    /** @return HasMany<GowaConversation> */
    public function conversations(): HasMany
    {
        return $this->hasMany(config('gowa.models.conversation'), 'instance_id');
    }

    /** @return HasMany<GowaMessage> */
    public function messages(): HasMany
    {
        return $this->hasMany(config('gowa.models.message'), 'instance_id');
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        if (empty($this->webhook_secret)) {
            return true;
        }

        return WebhookSignature::verify(
            $request->getContent(),
            (string) $request->header('X-Gowa-Signature', ''),
            $this->webhook_secret,
        );
    }

    public function client(): GowaClient
    {
        return new GowaClient(new Config(
            baseUrl: config('gowa.base_url'),
            username: config('gowa.username'),
            password: config('gowa.password'),
            timeout: config('gowa.timeout', 15),
        ));
    }
}
