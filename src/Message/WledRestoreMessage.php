<?php

declare(strict_types=1);

namespace App\Message;

final class WledRestoreMessage
{
    public function __construct(
        public readonly string $host,
    ) {}
}
