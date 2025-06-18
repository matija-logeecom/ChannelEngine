<?php

namespace ChannelEngine\Business\Service;

use ChannelEngine\Business\DTO\AccountData;
use ChannelEngine\Business\Interface\Proxy\ChannelEngineProxyInterface;
use ChannelEngine\Business\Interface\Repository\ConfigurationRepositoryInterface;
use ChannelEngine\Business\Interface\Service\AuthorizationServiceInterface;
use ChannelEngine\Infrastructure\DI\ServiceRegistry;
use Exception;
use PrestaShopLogger;
use RuntimeException;

class AuthorizationService implements AuthorizationServiceInterface
{
    private ConfigurationRepositoryInterface $configRepository;
    private ChannelEngineProxyInterface $channelEngineProxy;

    public function __construct()
    {
        try {
            $this->configRepository = ServiceRegistry::get(ConfigurationRepositoryInterface::class);
            $this->channelEngineProxy = ServiceRegistry::get(ChannelEngineProxyInterface::class);
        } catch (Exception $e) {
            PrestaShopLogger::addLog(
                'ChannelEngine: AuthorizationService could not be initialized. Failed to get a required service. Original error: ' . $e->getMessage(),
                3,
                null,
                'ChannelEngine'
            );
            throw new RuntimeException(
                "AuthorizationService failed to initialize due to a missing critical dependency.",
                0,
                $e
            );
        }
    }

    /**
     * @inheritDoc
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

        $settings = $this->channelEngineProxy->getSettings($accountName, $apiKey);

        $accountData = AccountData::fromChannelEngineSettings($settings, $accountName, $apiKey);

        $saveResult = $this->configRepository->saveCredentials($accountData);
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
            'data' => $accountData->toPublicArray(),
            'redirect' => true,
            'redirect_url' => 'sync'
        ];
    }

    /**
     * @inheritDoc
     */
    public function getConnectionStatus(): array
    {
        $accountData = $this->configRepository->getAccountData();

        $data = [
            'is_connected' => $this->isConnected()
        ];

        if ($accountData) {
            $data = array_merge($data, [
                'account_name' => $accountData->getAccountName(),
                'company_name' => $accountData->getCompanyName(),
                'currency_code' => $accountData->getCurrencyCode(),
                'status' => $this->configRepository->getConnectionStatus(),
                'last_validated' => $this->configRepository->getLastValidatedTimestamp() ?
                    date('Y-m-d H:i:s', $this->configRepository->getLastValidatedTimestamp()) : null
            ]);
        }

        return [
            'success' => true,
            'data' => $data
        ];
    }

    /**
     * @inheritDoc
     */
    public function isConnected(): bool
    {
        return $this->configRepository->getConnectionStatus() === 'connected' &&
            $this->configRepository->hasCredentials();
    }

    /**
     * @inheritDoc
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