<?php

namespace ChannelEngine\Business\Interface;

interface AuthorizationServiceInterface
{
    /**
     * Authorize connection with ChannelEngine
     * Validates credentials via API and saves them if successful
     *
     * @param string $accountName
     * @param string $apiKey
     *
     * @return array
     */
    public function authorizeConnection(string $accountName, string $apiKey): array;

    /**
     * Get current connection status
     *
     * @return array
     */
    public function getConnectionStatus(): array;

    /**
     * Check if currently connected
     *
     * @return bool
     */
    public function isConnected(): bool;

    /**
     * Disconnect and clear credentials
     *
     * @return array
     */
    public function disconnect(): array;
}