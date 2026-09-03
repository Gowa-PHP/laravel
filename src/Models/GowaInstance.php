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
use Illuminate\Support\Str;

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

    protected static function booted(): void
    {
        static::creating(function (self $instance): void {
            if (empty($instance->device_id)) {
                $instance->device_id = (string) Str::uuid7();
            }
        });
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

    /** @return HasMany<GowaWebhookCall> */
    public function webhookCalls(): HasMany
    {
        return $this->hasMany(config('gowa.models.webhook_call', GowaWebhookCall::class), 'instance_id');
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        // Mirrors the GOWA server's own resolution order: a device-level secret wins,
        // otherwise the global one is used. The server signs *every* delivery, so a
        // device without its own secret still arrives signed with the global secret --
        // an absent secret is a misconfiguration here, never a reason to skip the check.
        $secret = $this->webhook_secret ?: config('gowa.webhook.secret');

        if (empty($secret)) {
            return false;
        }

        $signatureHeader = (string) (
            $request->header('X-Hub-Signature-256')
            ?? $request->header('X-Gowa-Signature')
            ?? $request->header('X-Signature-256')
            ?? $request->header('X-Hub-Signature')
            ?? ''
        );

        return WebhookSignature::verify(
            $request->getContent(),
            $signatureHeader,
            (string) $secret,
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
