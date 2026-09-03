<?php

declare(strict_types=1);

namespace Gowa\Laravel\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A recorded webhook delivery from the GOWA server.
 *
 * @property int $id
 * @property int|null $instance_id
 * @property string $device_id
 * @property string $event
 * @property string|null $url
 * @property array<string, mixed>|null $headers
 * @property array<string, mixed>|null $payload
 * @property string|null $exception
 * @property bool $processed
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 */
class GowaWebhookCall extends Model
{
    use MassPrunable;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'headers'   => 'array',
            'payload'   => 'array',
            'processed' => 'boolean',
        ];
    }

    public function getTable(): string
    {
        return config('gowa.table_names.webhook_calls', 'gowa_webhook_calls');
    }

    public function prunable(): Builder
    {
        $days = (int) config('gowa.webhook.prune_after_days', 30);

        return static::where('created_at', '<=', now()->subDays($days));
    }

    /**
     * Flag a recorded delivery whose processing threw. Silently does nothing when
     * auditing is off (no id) or the row has since been pruned.
     */
    public static function markFailed(?int $id, \Throwable $e): void
    {
        if ($id === null) {
            return;
        }

        static::query()->whereKey($id)->update([
            'processed' => false,
            'exception' => sprintf('%s: %s', $e::class, $e->getMessage()),
        ]);
    }

    /** @return BelongsTo<GowaInstance, GowaWebhookCall> */
    public function instance(): BelongsTo
    {
        return $this->belongsTo(config('gowa.models.instance', GowaInstance::class), 'instance_id');
    }
}
