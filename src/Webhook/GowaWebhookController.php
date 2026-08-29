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
use Gowa\Sdk\Webhook\WebhookParser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

final class GowaWebhookController extends Controller
{
    public function __invoke(Request $request, string $deviceId): Response
    {
        $instanceModel = config('gowa.models.instance');

        /** @var \Gowa\Laravel\Models\GowaInstance|null $instance */
        $instance = $instanceModel::query()
            ->where('device_id', $deviceId)
            ->first();

        if ($instance === null) {
            return response('', 404);
        }

        if (! $instance->verifyWebhookSignature($request)) {
            return response('', 403);
        }

        $parsed = WebhookParser::parse($request->all());

        GowaWebhookReceived::dispatch(
            $instance->id,
            $parsed['event'],
            $parsed['data'],
            $parsed['raw'],
        );

        match ($parsed['event']) {
            Event::Message => $parsed['data'] instanceof IncomingMessage
                ? GowaMessageReceived::dispatch($instance->id, $parsed['data'], $parsed['raw'])
                : null,
            Event::MessageAck => $parsed['data'] instanceof IncomingAck
                ? GowaMessageAck::dispatch($instance->id, $parsed['data'], $parsed['raw'])
                : null,
            Event::MessageReaction => $parsed['data'] instanceof IncomingReaction
                ? GowaMessageReaction::dispatch($instance->id, $parsed['data'], $parsed['raw'])
                : null,
            default => null,
        };

        return response('', 200);
    }
}
