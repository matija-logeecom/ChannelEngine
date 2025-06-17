<?php

namespace ChannelEngine\Business\Interface;

interface ProductSyncServiceInterface
{
    /**
     * Synchronize all products from PrestaShop to ChannelEngine
     *
     * @return array
     */
    public function syncAllProducts(): array;

    /**
     * Get current synchronization status
     *
     * @return array
     */
    public function getSyncStatus(): array;

    /**
     * Update synchronization status
     *
     * @param string $status
     * @param array $data
     *
     * @return bool
     */
    public function updateSyncStatus(string $status, array $data = []): bool;
}