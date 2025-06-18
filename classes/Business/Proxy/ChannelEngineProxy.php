<?php

namespace ChannelEngine\Business\Proxy;

use ChannelEngine\Business\Interface\Proxy\ChannelEngineProxyInterface;
use ChannelEngine\Infrastructure\DI\ServiceRegistry;
use ChannelEngine\Infrastructure\HTTP\HttpClient;
use Exception;
use PrestaShopLogger;
use RuntimeException;

class ChannelEngineProxy implements ChannelEngineProxyInterface
{
    private HttpClient $httpClient;

    public function __construct()
    {
        try {
            $this->httpClient = ServiceRegistry::get(HttpClient::class);
        } catch (Exception $e) {
            PrestaShopLogger::addLog(
                'ChannelEngine: ChannelEngineProxy could not be initialized. ' .
                'Failed to get HttpClient service. Original error: ' . $e->getMessage(),
                3,
                null,
                'ChannelEngine'
            );
            throw new RuntimeException(
                "ChannelEngineProxy failed to initialize due to a missing critical dependency.",
                0,
                $e
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

        $response = $this->httpClient->get($url);
        $this->validateResponse($response);

        $body = $response['body'];
        $this->validateApiResponse($body);

        return $body['Content'] ?? [];
    }

    /**
     * @inheritDoc
     */
    public function syncProducts(string $accountName, string $apiKey, array $products): array
    {
        $url = "https://{$accountName}.channelengine.net/api/v2/products";

        $this->httpClient->setDefaultHeaders([
            'X-CE-KEY' => $apiKey,
            'Content-Type' => 'application/json'
        ]);

        $response = $this->httpClient->post($url, $products);
        $this->validateResponse($response, [200, 201]);

        $body = $response['body'];
        $this->validateApiResponse($body);

        return [
            'success' => true,
            'message' => $body['Message'] ?? 'Products synchronized successfully',
            'content' => $body['Content'] ?? []
        ];
    }

    /**
     * Validate HTTP response status code
     *
     * @param array $response
     * @param array $expectedCodes
     *
     * @throws Exception
     */
    private function validateResponse(array $response, array $expectedCodes = [200]): void
    {
        $statusCode = $response['status_code'];

        if (!in_array($statusCode, $expectedCodes)) {
            $this->handleErrorResponse($response);
        }
    }

    /**
     * Validate ChannelEngine API response structure and success status
     *
     * @param mixed $body
     *
     * @throws Exception
     */
    private function validateApiResponse($body): void
    {
        if (!is_array($body)) {
            throw new Exception('Invalid API response format');
        }

        if (!isset($body['Success']) || !$body['Success']) {
            $message = $body['Message'] ?? 'Unknown API error';

            if (isset($body['ValidationErrors']) && is_array($body['ValidationErrors'])) {
                $errors = [];
                foreach ($body['ValidationErrors'] as $error) {
                    $errors[] = is_array($error) ? ($error['Message'] ?? $error) : $error;
                }
                $message .= ' Validation errors: ' . implode(', ', $errors);
            }

            throw new Exception($message);
        }
    }

    /**
     * Handle error responses based on HTTP status codes
     *
     * @param array $response
     *
     * @throws Exception
     */
    private function handleErrorResponse(array $response): void
    {
        $statusCode = $response['status_code'];
        $body = $response['body'];

        switch ($statusCode) {
            case 401:
                throw new Exception('HTTP 401: Invalid credentials or unauthorized access');
            case 404:
                throw new Exception('HTTP 404: Account not found or invalid endpoint');
            case 400:
                $message = 'HTTP 400: Bad request';
                if (is_array($body) && isset($body['Message'])) {
                    $message .= ' - ' . $body['Message'];
                }
                throw new Exception($message);
            case 403:
                throw new Exception('HTTP 403: Access forbidden');
            case 500:
                throw new Exception('HTTP 500: Internal server error');
            default:
                $message = "HTTP {$statusCode}: Request failed";
                if (is_array($body) && isset($body['Message'])) {
                    $message .= ' - ' . $body['Message'];
                }
                if (is_string($body)) {
                    $message .= ' - ' . $body;
                }
                throw new Exception($message);
        }
    }
}