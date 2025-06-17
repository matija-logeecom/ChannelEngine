<?php

namespace ChannelEngine\Business\Interface\Proxy;

use Exception;

interface ChannelEngineProxyInterface
{
    /**
     * Get account settings from ChannelEngine API
     *
     * @param string $accountName
     * @param string $apiKey
     *
     * @return array
     *
     * @throws Exception
     */
    public function getSettings(string $accountName, string $apiKey): array;

    /**
     * Syncs products with Channel Engine
     *
     * @param string $accountName
     * @param string $apiKey
     * @param array $products
     *
     * @return array
     */
    public function syncProducts(string $accountName, string $apiKey, array $products): array;
}