<?php

namespace ChannelEngine\Business\Proxy;

use ChannelEngine\Infrastructure\DI\ServiceRegistry;
use ChannelEngine\Infrastructure\HTTP\HttpClient;
use ChannelEngine\Business\Interface\ChannelEngineProxyInterface;
use Exception;
use RuntimeException;

class ChannelEngineProxy implements ChannelEngineProxyInterface
{
    private HttpClient $httpClient;

    public function __construct()
    {
        try {
            $this->httpClient = ServiceRegistry::get(HttpClient::class);
        } catch (Exception $e) {
            error_log("CRITICAL: ChannelEngineProxy could not be initialized. " .
                "Failed to get a required service. Original error: " . $e->getMessage());
            throw new RuntimeException(
                "ChannelEngineProxy failed to initialize due to a " .
                "missing critical dependency.", 0, $e
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function getSettings(string $accountName, string $apiKey): array
    {
        $url = "https://{$accountName}.channelengine.net/api/v2/settings";

        $this->httpClient->setDefaultHeaders([
            'X-CE-KEY' => $apiKey,
            'Content-Type' => 'application/json'
        ]);

        try {
            $response = $this->httpClient->get($url);

            if ($response['status_code'] !== 200) {
                throw new Exception('Failed to get settings: HTTP ' . $response['status_code']);
            }

            $body = $response['body'];
            if (!isset($body['Success']) || !$body['Success']) {
                throw new Exception($body['Message'] ?? 'Unknown error');
            }

            return $body['Content'] ?? [];

        } catch (Exception $e) {
            throw new Exception('Failed to get ChannelEngine settings: ' . $e->getMessage());
        }
    }
}