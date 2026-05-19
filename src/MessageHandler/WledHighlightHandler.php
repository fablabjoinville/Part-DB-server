<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Parts\StorageLocation;
use App\Message\WledHighlightMessage;
use Doctrine\ORM\EntityManagerInterface;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class WledHighlightHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
        #[Autowire(env: 'MQTT_HOST')]
        private readonly string $mqttHost,
        #[Autowire(env: 'MQTT_PORT')]
        private readonly string $mqttPort,
        #[Autowire(env: 'WLED_HIGHLIGHT_DURATION_S')]
        private readonly string $highlightDurationS,
        #[Autowire(env: 'WLED_HIGHLIGHT_COLOR')]
        private readonly string $highlightColor,
        #[Autowire(env: 'WLED_HIGHLIGHT_EFFECT')]
        private readonly string $highlightEffect,
    ) {}

    public function __invoke(WledHighlightMessage $message): void
    {
        $location = $this->em->find(StorageLocation::class, $message->storageLocationId);
        if (!$location instanceof StorageLocation) {
            return;
        }

        $ledStart = $location->getWledLedStart();
        $ledEnd   = $location->getWledLedEnd();

        if ($ledStart === null || $ledEnd === null || $ledEnd < $ledStart) {
            // Location has no LED range configured — nothing to do.
            return;
        }

        $mqttTopic = $location->resolveWledMqttTopic();
        if ($mqttTopic === null) {
            return;
        }

        $payload = $this->buildPayload($ledStart, $ledEnd);

        try {
            $settings = (new ConnectionSettings())
                ->setConnectTimeout(3)
                ->setSocketTimeout(3)
                ->setKeepAliveInterval(0);

            $client = new MqttClient($this->mqttHost, (int) $this->mqttPort, 'partdb-wled-' . uniqid());
            $client->connect($settings, cleanSession: true);
            $client->publish($mqttTopic . '/api', json_encode($payload), qualityOfService: 0, retain: false);
            $client->disconnect();
        } catch (\Throwable $e) {
            // Fire-and-forget: log but never crash the worker over MQTT errors.
            $this->logger->warning('WLED MQTT publish failed for location {id}: {msg}', [
                'id'  => $message->storageLocationId,
                'msg' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Builds the WLED JSON API payload.
     *
     * Uses a temporary segment on the target LED range with a blink effect and
     * WLED's native Nightlight timer to fade out after the configured duration.
     * No "turn off" command is ever sent from PHP — the controller handles it.
     */
    private function buildPayload(int $ledStart, int $ledEnd): array
    {
        $color    = ltrim($this->highlightColor, '#');
        $duration = max(1, (int) $this->highlightDurationS);
        $effect   = (int) $this->highlightEffect;

        return [
            'seg' => [
                [
                    'id'    => 0,
                    'start' => $ledStart,
                    'stop'  => $ledEnd + 1, // WLED stop is exclusive
                    'on'    => true,
                    'bri'   => 255,
                    'col'   => [[$color]],
                    'fx'    => $effect,
                    'sx'    => 180,
                    'nl'    => [
                        'on'   => true,
                        'dur'  => $duration,
                        'mode' => 1,    // fade to target brightness
                        'tbri' => 0,    // target brightness = off
                    ],
                ],
            ],
        ];
    }
}
