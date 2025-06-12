<?php

namespace ChannelEngine\Data\Repository;

use ChannelEngine\Service\Interface\ConfigurationRepositoryInterface;
use Configuration;

class ConfigurationRepository implements ConfigurationRepositoryInterface
{
    private const ACCOUNT_NAME_KEY = 'CHANNELENGINE_ACCOUNT_NAME';
    private const API_KEY_KEY = 'CHANNELENGINE_API_KEY';
    private const CONNECTION_STATUS_KEY = 'CHANNELENGINE_CONNECTION_STATUS';
    private const LAST_VALIDATED_KEY = 'CHANNELENGINE_LAST_VALIDATED';
    private const COMPANY_NAME_KEY = 'CHANNELENGINE_COMPANY_NAME';
    private const CURRENCY_CODE_KEY = 'CHANNELENGINE_CURRENCY_CODE';


    /**
     * @inheritDoc
     */
    public function saveCredentials(string $accountName, string $apiKey, array $accountData = []): bool
    {
        $results = [
            Configuration::updateValue(self::ACCOUNT_NAME_KEY, $accountName),
            Configuration::updateValue(self::API_KEY_KEY, $apiKey),
            Configuration::updateValue(self::CONNECTION_STATUS_KEY, 'connected'),
            Configuration::updateValue(self::LAST_VALIDATED_KEY, time()),
            Configuration::updateValue(self::COMPANY_NAME_KEY, $accountData['company_name'] ?? ''),
            Configuration::updateValue(self::CURRENCY_CODE_KEY, $accountData['currency_code'] ?? ''),
        ];

        return !in_array(false, $results, true);
    }

    /**
     * @inheritDoc
     */
    public function getCredentials(): ?array
    {
        $accountName = Configuration::get(self::ACCOUNT_NAME_KEY);
        $apiKey = Configuration::get(self::API_KEY_KEY);

        if (empty($accountName) || empty($apiKey)) {
            return null;
        }

        return [
            'account_name' => $accountName,
            'api_key' => $apiKey
        ];
    }

    /**
     * @inheritDoc
     */
    public function hasCredentials(): bool
    {
        return $this->getCredentials() !== null;
    }

    /**
     * @inheritDoc
     */
    public function getConnectionStatus(): string
    {
        return Configuration::get(self::CONNECTION_STATUS_KEY) ?: 'disconnected';
    }

    /**
     * @inheritDoc
     */
    public function getLastValidatedTimestamp(): ?int
    {
        $timestamp = Configuration::get(self::LAST_VALIDATED_KEY);
        return $timestamp ? (int)$timestamp : null;
    }

    /**
     * @inheritDoc
     */
    public function getAccountData(): array
    {
        return [
            'account_name' => Configuration::get(self::ACCOUNT_NAME_KEY) ?: null,
            'company_name' => Configuration::get(self::COMPANY_NAME_KEY) ?: null,
            'currency_code' => Configuration::get(self::CURRENCY_CODE_KEY) ?: null,
            'status' => $this->getConnectionStatus(),
            'last_validated' => $this->getLastValidatedTimestamp() ?
                date('Y-m-d H:i:s', $this->getLastValidatedTimestamp()) : null
        ];
    }

    /**
     * @inheritDoc
     */
    public function clearCredentials(): bool
    {
        $results = [
            Configuration::deleteByName(self::ACCOUNT_NAME_KEY),
            Configuration::deleteByName(self::API_KEY_KEY),
            Configuration::updateValue(self::CONNECTION_STATUS_KEY, 'disconnected'),
            Configuration::deleteByName(self::LAST_VALIDATED_KEY),
            Configuration::deleteByName(self::COMPANY_NAME_KEY),
            Configuration::deleteByName(self::CURRENCY_CODE_KEY)
        ];

        return !in_array(false, $results, true);
    }

    /**
     * @inheritDoc
     */
    public function updateConnectionStatus(string $status): bool
    {
        return Configuration::updateValue(self::CONNECTION_STATUS_KEY, $status);
    }
}