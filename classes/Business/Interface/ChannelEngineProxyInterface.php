<?php

namespace ChannelEngine\Business\Interface;

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
}