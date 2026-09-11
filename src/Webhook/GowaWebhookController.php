<?php

declare(strict_types=1);

namespace Gowa\Laravel\Webhook;

use Gowa\Laravel\Webhook\Events\GowaMessageAck;
use Gowa\Laravel\Webhook\Events\GowaMessageReaction;
use Gowa\Laravel\Webhook\Events\GowaMessageReceived;
use Gowa\Laravel\Webhook\Events\GowaWebhookReceived;
use Gowa\Sdk\Webhook\Dto\IncomingAck;
use Gowa\Sdk\Webhook\Dto\IncomingMessage;
use Gowa\Sdk\Webhook\Dto\IncomingReaction;
use Gowa\Sdk\Webhook\Event;
use Gowa\Sdk\Webhook\WebhookEvent;
use Gowa\Sdk\Webhook\WebhookParser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

final class GowaWebhookController extends Controller
{
    public function __invoke(Request $request, string $deviceId): Response
    {
        $isStateless = (bool) (config('gowa.stateless', false) || config('gowa.driver_only', false));
        $instanceModel = $isStateless ? null : config('gowa.models.instance');

        /** @var \Gowa\Laravel\Models\GowaInstance|null $instance */
        $instance = null;
        if (! $isStateless && $instanceModel && class_exists($instanceModel)) {
            $instance = $instanceModel::query()->where('device_id', $deviceId)->first();
        }

        if ($instance !== null) {
            if (! $instance->verifyWebhookSignature($request)) {
                return response('', 403);
            }
            $instanceId = $instance->id;
        } else {
            // Unknown device: the only accepted proof is a valid HMAC signature made
            // with the global secret. Never treat a device id from the URL as an
            // authentication signal -- it travels in the clear on every delivery.
            $globalSecret = config('gowa.webhook.secret');

            if ($globalSecret === null || $globalSecret === '') {
                return response('', 404);
            }

            $signatureHeader = (string) (
                $request->header('X-Hub-Signature-256')
                ?? $request->header('X-Gowa-Signature')
                ?? $request->header('X-Signature-256')
                ?? $request->header('X-Hub-Signature')
                ?? ''
            );

            if (! \Gowa\Sdk\Security\WebhookSignature::verify($request->getContent(), $signatureHeader, $globalSecret)) {
                return response('', 403);
            }

            // Signature checks out, but this device has no local instance row.
            $instanceId = null;
        }

        $parsed = WebhookParser::parse($request->getContent());

        // Record the delivery before dispatching, so a listener that blows up still has
        // a row to mark as failed. Queued listeners run after this request is gone.
        $webhookCallId = $this->recordCall($request, $deviceId, $instanceId, $parsed, $instance);

        GowaWebhookReceived::dispatch(
            $instanceId,
            $deviceId,
            $parsed['event'],
            $parsed['data'],
            $parsed['raw'],
            $request->fullUrl(),
            self::auditableHeaders($request),
            $webhookCallId,
        );

        match ($parsed['event']) {
            Event::Message => $parsed['data'] instanceof IncomingMessage
                ? GowaMessageReceived::dispatch($instanceId, $deviceId, $parsed['data'], $parsed['raw'], $webhookCallId)
                : null,
            Event::MessageAck => $parsed['data'] instanceof IncomingAck
                ? GowaMessageAck::dispatch($instanceId, $deviceId, $parsed['data'], $parsed['raw'], $webhookCallId)
                : null,
            Event::MessageReaction => $parsed['data'] instanceof IncomingReaction
                ? GowaMessageReaction::dispatch($instanceId, $deviceId, $parsed['data'], $parsed['raw'], $webhookCallId)
                : null,
            default => null,
        };

        return response('', 200);
    }

    /**
     * Persist the delivery in `gowa_webhook_calls` and return its id, or null when
     * auditing is switched off or the model has been removed.
     *
     * @param array{event: Event, data: mixed, raw: array<string, mixed>}|WebhookEvent $parsed
     */
    private function recordCall(
        Request $request,
        string $deviceId,
        ?int $instanceId,
        array|WebhookEvent $parsed,
        ?object $instance = null,
    ): ?int {
        if ((config('gowa.stateless', false) || config('gowa.driver_only', false)) || ! config('gowa.webhook.record_calls', true)) {
            return null;
        }

        $webhookCallModel = config('gowa.models.webhook_call', \Gowa\Laravel\Models\GowaWebhookCall::class);

        if (! class_exists($webhookCallModel)) {
            return null;
        }

        $eventString = is_string($parsed['raw']['event'] ?? null)
            ? (string) $parsed['raw']['event']
            : $parsed['event']->value;

        $data = [
            'instance_id' => $instanceId,
            'device_id'   => $deviceId,
            'event'       => $eventString,
            'url'         => $request->fullUrl(),
            'headers'     => self::auditableHeaders($request),
            'payload'     => $parsed['raw'],
            'processed'   => true,
        ];

        if (config('gowa.teams.enabled', false) && $instance !== null) {
            $teamFk = config('gowa.teams.foreign_key', 'team_id');
            if (isset($instance->{$teamFk})) {
                $data[$teamFk] = $instance->{$teamFk};
            }
        }

        $call = $webhookCallModel::create($data);

        return (int) $call->id;
    }

    /**
     * Headers worth keeping for auditing, minus the ones that carry credentials.
     *
     * @return array<string, array<int, string|null>>
     */
    private static function auditableHeaders(Request $request): array
    {
        return array_diff_key(
            $request->headers->all(),
            array_flip(['authorization', 'cookie', 'proxy-authorization', 'x-gowa-secret', 'x-api-key']),
        );
    }
}
