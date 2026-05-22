<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\WledRestoreMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsMessageHandler]
final class WledRestoreHandler
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly HttpClientInterface $httpClient,
    ) {}

    public function __invoke(WledRestoreMessage $message): void
    {
        try {
            $response = $this->httpClient->request('POST', "http://{$message->host}/json/state", [
                'json'    => ['seg' => [['id' => 1, 'on' => false]]],
                'timeout' => 5,
            ]);
            $response->toArray();
        } catch (\Throwable $e) {
            $this->logger->error('WLED restore failed for host "{host}": {msg}', [
                'host' => $message->host,
                'msg'  => $e->getMessage(),
            ]);
        }
    }
}
