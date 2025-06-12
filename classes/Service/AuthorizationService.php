<?php

namespace ChannelEngine\Service;

use ChannelEngine\Service\Interface\ConfigurationRepositoryInterface;
use ChannelEngine\Infrastructure\DI\ServiceRegistry;
use Exception;
use RuntimeException;

class AuthorizationService
{
    private ConfigurationRepositoryInterface $configRepository;

    public function __construct()
    {
        try {
            $this->configRepository = ServiceRegistry::get(ConfigurationRepositoryInterface::class);
        } catch (Exception $e) {
            error_log("CRITICAL: AuthorizationService could not be initialized. " .
                "Failed to get a required service. Original error: " . $e->getMessage());
            throw new RuntimeException(
                "AuthorizationService failed to initialize due to a " .
                "missing critical dependency.", 0, $e
            );
        }
    }

    /**
     * Authorize connection with ChannelEngine
     * Validates credentials via API and saves them if successful
     *
     * @param string $accountName
     * @param string $apiKey
     *
     * @return array
     */
    public function authorizeConnection(string $accountName, string $apiKey): array
    {
        if (empty(trim($accountName)) || empty(trim($apiKey))) {
            return [
                'success' => false,
                'message' => 'Account name and API key are required',
                'error_code' => 'MISSING_CREDENTIALS'
            ];
        }

        try {
            $apiResponse = $this->validateCredentialsWithApi($accountName, $apiKey);
            if (!$apiResponse['success']) {
                return $apiResponse;
            }

            $saveResult = $this->configRepository->saveCredentials($accountName, $apiKey, $apiResponse['data']);
            if (!$saveResult) {
                return [
                    'success' => false,
                    'message' => 'Failed to save configuration',
                    'error_code' => 'SAVE_FAILED',
                ];
            }

            return [
                'success' => true,
                'message' => 'Successfully connected to ChannelEngine',
                'data' => $apiResponse['data'],
                'redirect' => true,
                'redirect_url' => 'sync'
            ];
        } catch (Exception $e) {
            $this->configRepository->updateConnectionStatus('failed');

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => 'API_ERROR'
            ];
        }
    }

    /**
     * Make API call to validate credentials
     *
     * @param string $accountName
     * @param string $apiKey
     *
     * @return array
     *
     * @throws Exception
     */
    private function validateCredentialsWithApi(string $accountName, string $apiKey): array
    {
        $url = "https://{$accountName}.channelengine.net/api/v2/settings";

        $headers = [
            'Content-Type: application/json',
            'X-CE-KEY: ' . $apiKey,
        ];

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
                'timeout' => 5,
                'ignore_errors' => true
            ]
        ]);

        $response = file_get_contents($url, false, $context);
        if ($response === false) {
            throw new Exception('Failed to connect to ChannelEngine API. Please check your account name and network connection.');
        }

        $httpCode = $this->getHttpResponseCode($http_response_header ?? []);

        $responseData = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid response from ChannelEngine API: ' . json_last_error_msg());
        }

        switch ($httpCode) {
            case 200:
                if (!($responseData['Success'] ?? false)) {
                    throw new Exception($responseData['Message'] ?? 'API call failed');
                }

                return [
                    'success' => true,
                    'data' => [
                        'account_name' => $responseData['Content']['Name'] ?? $accountName,
                        'company_name' => $responseData['Content']['CompanyName'] ?? '',
                        'currency_code' => $responseData['Content']['CurrencyCode'] ?? '',
                        'settings' => $responseData['Content'] ?? []
                    ]
                ];

            case 401:
                throw new Exception('Invalid account or API key. Please check your credentials.');

            case 404:
                throw new Exception('Invalid account name. Please check your account name.');

            default:
                $message = $responseData['Message'] ?? 'Unknown error occurred';
                throw new Exception("ChannelEngine API error (HTTP {$httpCode}): {$message}");
        }
    }

    /**
     * Get current connection status
     *
     * @return array
     */
    public function getConnectionStatus(): array
    {
        return [
            'success' => true,
            'data' => array_merge($this->configRepository->getAccountData(), [
                'is_connected' => $this->isConnected()
            ])
        ];
    }

    /**
     * Check if currently connected
     *
     * @return bool
     */
    public function isConnected(): bool
    {
        return $this->configRepository->getConnectionStatus() === 'connected' &&
            $this->configRepository->hasCredentials();
    }

    /**
     * Disconnect and clear credentials
     *
     * @return array
     */
    public function disconnect(): array
    {
        $success = $this->configRepository->clearCredentials();

        return [
            'success' => $success,
            'message' => $success ? 'Successfully disconnected' : 'Failed to disconnect'
        ];
    }

    /**
     * Extract HTTP response code from headers
     *
     * @param array $headers
     * @return int
     */
    private function getHttpResponseCode(array $headers): int
    {
        if (empty($headers)) {
            return 0;
        }

        $statusLine = $headers[0] ?? '';
        if (preg_match('/HTTP\/\d\.\d\s+(\d+)/', $statusLine, $matches)) {
            return (int)$matches[1];
        }

        return 0;
    }
}