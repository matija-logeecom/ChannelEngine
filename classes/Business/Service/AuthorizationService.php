<?php

namespace ChannelEngine\Business\Service;

use ChannelEngine\Business\Interface\ChannelEngineProxyInterface;
use ChannelEngine\Business\Interface\ConfigurationRepositoryInterface;
use ChannelEngine\Infrastructure\DI\ServiceRegistry;
use Exception;
use RuntimeException;

class AuthorizationService
{
    private ConfigurationRepositoryInterface $configRepository;
    private ChannelEngineProxyInterface $channelEngineProxy;

    public function __construct()
    {
        try {
            $this->configRepository = ServiceRegistry::get(ConfigurationRepositoryInterface::class);
            $this->channelEngineProxy = ServiceRegistry::get(ChannelEngineProxyInterface::class);
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
            $settings = $this->channelEngineProxy->getSettings($accountName, $apiKey);

            $accountData = [
                'account_name' => $settings['Name'] ?? $accountName,
                'company_name' => $settings['CompanyName'] ?? '',
                'currency_code' => $settings['CurrencyCode'] ?? '',
                'settings' => $settings
            ];

            $saveResult = $this->configRepository->saveCredentials($accountName, $apiKey, $accountData);
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
                'data' => $accountData,
                'redirect' => true,
                'redirect_url' => 'sync'
            ];
        } catch (Exception $e) {
            $this->configRepository->updateConnectionStatus('failed');

            $message = $e->getMessage();
            $errorCode = 'API_ERROR';

            if (str_contains($message, 'HTTP 401')) {
                $message = 'Invalid account or API key. Please check your credentials.';
                $errorCode = 'INVALID_CREDENTIALS';
            }
            if (str_contains($message, 'HTTP 404')) {
                $message = 'Invalid account name. Please check your account name.';
                $errorCode = 'INVALID_ACCOUNT';
            }

            return [
                'success' => false,
                'message' => $message,
                'error_code' => $errorCode
            ];
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
}