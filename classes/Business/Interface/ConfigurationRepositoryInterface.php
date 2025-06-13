<?php

namespace ChannelEngine\Business\Interface;

interface ConfigurationRepositoryInterface
{
    /**
     * Save authorization credentials and account data to configuration
     *
     * @param string $accountName
     * @param string $apiKey
     * @param array $accountData
     *
     * @return bool
     */
    public function saveCredentials(string $accountName, string $apiKey, array $accountData = []): bool;

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
     * Get stored account data
     *
     * @return array
     */
    public function getAccountData(): array;

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
     *
     * @return bool
     */
    public function updateConnectionStatus(string $status): bool;
}