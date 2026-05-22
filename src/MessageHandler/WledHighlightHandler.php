<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Parts\StorageLocation;
use App\Message\WledHighlightMessage;
use App\Message\WledRestoreMessage;
use App\Settings\MiscSettings\WledSettings;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsMessageHandler]
final class WledHighlightHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
        private readonly WledSettings $wledSettings,
        private readonly HttpClientInterface $httpClient,
        private readonly MessageBusInterface $messageBus,
    ) {}

    public function __invoke(WledHighlightMessage $message): void
    {
        $location = $this->em->find(StorageLocation::class, $message->storageLocationId);
        if (!$location instanceof StorageLocation) {
            return;
        }

        [$startX, $stopX, $startY, $stopY, $rowHost] = $this->resolveSegmentCoords($location);
        if ($startX === null) {
            $this->logger->warning('Cannot resolve WLED segment for location "{name}" (id={id})', [
                'name' => $location->getName(),
                'id'   => $location->getID(),
            ]);
            return;
        }

        // Host priority: per-row config override > entity tree > system default
        $host = $rowHost ?? $location->resolveWledHost() ?? $this->wledSettings->wledHost;
        if (!$host) {
            return;
        }

        [$r, $g, $b] = $this->hexToRgb($this->wledSettings->highlightColor);
        $durationS = max(1, (int) $this->wledSettings->highlightDurationS);

        $payload = [
            'seg' => [[
                'id'     => 1,
                'start'  => $startX,
                'stop'   => $stopX,
                'startY' => $startY,
                'stopY'  => $stopY,
                'col'    => [[$r, $g, $b], [0, 0, 0]],
                'fx'     => $this->wledSettings->highlightEffect,
                'sx'     => 60,
                'on'     => true,
                'bri'    => 255,
            ]],
        ];

        try {
            $response = $this->httpClient->request('POST', "http://{$host}/json/state", [
                'json'    => $payload,
                'timeout' => 5,
            ]);
            $data = $response->toArray();
            if (!($data['success'] ?? false)) {
                $this->logger->error('WLED API returned non-success for location "{name}"', [
                    'name'     => $location->getName(),
                    'response' => $data,
                ]);
                return;
            }
        } catch (\Throwable $e) {
            $this->logger->error('WLED HTTP request failed for location "{name}": {msg}', [
                'name' => $location->getName(),
                'msg'  => $e->getMessage(),
            ]);
            return;
        }

        // Schedule restore (turn segment 1 off) after the highlight duration
        $this->messageBus->dispatch(
            new WledRestoreMessage($host),
            [new DelayStamp($durationS * 1000)]
        );
    }

    /**
     * Resolves [startX, stopX, startY, stopY, host|null] for segment 1 (ELECTRODRAWER).
     * Returns [null, null, null, null, null] if coordinates cannot be determined.
     *
     * Priority:
     * 1. Manual override: wled_led_start / wled_led_end (host from entity tree / system default).
     * 2. Name-based auto-detection: name matches "[Letters][Digits]" (e.g. "E35").
     */
    private function resolveSegmentCoords(StorageLocation $location): array
    {
        $ledStart = $location->getWledLedStart();
        $ledEnd   = $location->getWledLedEnd();
        if ($ledStart !== null && $ledEnd !== null && $ledEnd >= $ledStart) {
            return [$ledStart, $ledEnd + 1, 0, 1, null];
        }

        $name = trim($location->getName() ?? '');
        if (!preg_match('/^([A-Za-z]+)(\d+)$/i', $name, $m)) {
            return [null, null, null, null, null];
        }

        $rowLetter = strtoupper($m[1]);
        $colNumber = (int) $m[2];

        $rowConfig = json_decode($this->wledSettings->rowConfig, true) ?? [];
        if (!isset($rowConfig[$rowLetter])) {
            $this->logger->warning('Row "{row}" not found in WLED rowConfig', ['row' => $rowLetter]);
            return [null, null, null, null, null];
        }

        $cfg       = $rowConfig[$rowLetter];
        $y         = (int) $cfg['y'];
        $perDrawer = max(1, (int) ($cfg['perDrawer'] ?? 1));
        $startX    = ($colNumber - 1) * $perDrawer;  // colNumber is 1-based (C01 = first column)
        $stopX     = $startX + $perDrawer;
        $rowHost   = ($cfg['host'] ?? '') !== '' ? $cfg['host'] : null;

        return [$startX, $stopX, $y, $y + 1, $rowHost];
    }

    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }
}
