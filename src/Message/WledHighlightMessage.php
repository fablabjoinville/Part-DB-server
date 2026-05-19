<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Dispatched when a user views or manually highlights a part.
 * The handler publishes a fire-and-forget WLED JSON payload via MQTT.
 * All LED timeout/off logic is handled natively by the WLED controller firmware.
 */
final class WledHighlightMessage
{
    public function __construct(
        public readonly int $partId,
        public readonly int $storageLocationId,
    ) {}
}
