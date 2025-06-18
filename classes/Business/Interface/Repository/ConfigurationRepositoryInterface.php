<?php

namespace ChannelEngine\Business\Interface\Repository;

use ChannelEngine\Business\DTO\AccountData;

interface ConfigurationRepositoryInterface
{
    /**
     * Save authorization credentials and account data to configuration
     *
     * @param AccountData $accountData
     * @return bool
     */
    public function saveCredentials(AccountData $accountData): bool;

    /**
     * Get stored credentials
     *
     * @return array|null
     */
    public function getCredentials(): ?array;

    /**
     * Check if credentials are stored
     *
     * @return bool
     */
    public function hasCredentials(): bool;

    /**
     * Get connection status
     *
     * @return string
     */
    public function getConnectionStatus(): string;

    /**
     * Get last validation timestamp
     *
     * @return int|null
     */
    public function getLastValidatedTimestamp(): ?int;

    /**
     * Get stored account data as DTO
     *
     * @return AccountData|null
     */
    public function getAccountData(): ?AccountData;

    /**
     * Clear stored credentials and account data
     *
     * @return bool
     */
    public function clearCredentials(): bool;

    /**
     * Update connection status only
     *
     * @param string $status
     * @return bool
     */
    public function updateConnectionStatus(string $status): bool;
}