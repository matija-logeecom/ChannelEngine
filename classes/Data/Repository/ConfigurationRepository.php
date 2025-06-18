<?php

namespace ChannelEngine\Data\Repository;

use ChannelEngine\Business\DTO\AccountData;
use ChannelEngine\Business\Interface\Repository\ConfigurationRepositoryInterface;
use Configuration;

class ConfigurationRepository implements ConfigurationRepositoryInterface
{
    private const ACCOUNT_NAME_KEY = 'CHANNELENGINE_ACCOUNT_NAME';
    private const API_KEY_KEY = 'CHANNELENGINE_API_KEY';
    private const CONNECTION_STATUS_KEY = 'CHANNELENGINE_CONNECTION_STATUS';
    private const LAST_VALIDATED_KEY = 'CHANNELENGINE_LAST_VALIDATED';
    private const COMPANY_NAME_KEY = 'CHANNELENGINE_COMPANY_NAME';
    private const CURRENCY_CODE_KEY = 'CHANNELENGINE_CURRENCY_CODE';
    private const SETTINGS_KEY = 'CHANNELENGINE_SETTINGS';

    /**
     * @inheritDoc
     */
    public function saveCredentials(AccountData $accountData): bool
    {
        $results = [
            Configuration::updateValue(self::ACCOUNT_NAME_KEY, $accountData->getAccountName()),
            Configuration::updateValue(self::API_KEY_KEY, $accountData->getApiKey()),
            Configuration::updateValue(self::CONNECTION_STATUS_KEY, 'connected'),
            Configuration::updateValue(self::LAST_VALIDATED_KEY, time()),
            Configuration::updateValue(self::COMPANY_NAME_KEY, $accountData->getCompanyName()),
            Configuration::updateValue(self::CURRENCY_CODE_KEY, $accountData->getCurrencyCode()),
            Configuration::updateValue(self::SETTINGS_KEY, json_encode($accountData->getSettings())),
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
    public function getAccountData(): ?AccountData
    {
        $accountName = Configuration::get(self::ACCOUNT_NAME_KEY);

        if (empty($accountName)) {
            return null;
        }

        $settingsJson = Configuration::get(self::SETTINGS_KEY);
        $settings = $settingsJson ? json_decode($settingsJson, true) : [];

        return new AccountData(
            $accountName,
            Configuration::get(self::API_KEY_KEY) ?: '',
            Configuration::get(self::COMPANY_NAME_KEY) ?: '',
            Configuration::get(self::CURRENCY_CODE_KEY) ?: '',
            is_array($settings) ? $settings : []
        );
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
            Configuration::deleteByName(self::CURRENCY_CODE_KEY),
            Configuration::deleteByName(self::SETTINGS_KEY)
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