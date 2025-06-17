<?php

namespace ChannelEngine\Business\Service;

use ChannelEngine\Business\Interface\ProductSyncServiceInterface;
use ChannelEngine\Business\Interface\ChannelEngineProxyInterface;
use ChannelEngine\Business\Interface\ConfigurationRepositoryInterface;
use ChannelEngine\Infrastructure\DI\ServiceRegistry;
use Exception;
use RuntimeException;

class ProductSyncService implements ProductSyncServiceInterface
{
    private ConfigurationRepositoryInterface $configRepository;
    private ChannelEngineProxyInterface $channelEngineProxy;

    public function __construct()
    {
        try {
            $this->configRepository = ServiceRegistry::get(ConfigurationRepositoryInterface::class);
            $this->channelEngineProxy = ServiceRegistry::get(ChannelEngineProxyInterface::class);
        } catch (Exception $e) {
            error_log("CRITICAL: ProductSyncService could not be initialized. " .
                "Failed to get a required service. Original error: " . $e->getMessage());
            throw new RuntimeException(
                "ProductSyncService failed to initialize due to a " .
                "missing critical dependency.", 0, $e
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function syncAllProducts(): array
    {
        if (!$this->configRepository->hasCredentials()) {
            return [
                'success' => false,
                'message' => 'Not connected to ChannelEngine',
                'error_code' => 'NOT_CONNECTED'
            ];
        }

        try {
            // Update status to "in progress"
            $this->updateSyncStatus('in_progress', [
                'started_at' => time(),
                'total_products' => 0,
                'synced_products' => 0
            ]);

            // TODO: Implement actual product synchronization logic
            // This will be implemented after discussion with mentor
            // For now, we'll simulate a successful sync

            // Placeholder implementation
            $result = $this->performProductSync();

            if ($result['success']) {
                $this->updateSyncStatus('done', [
                    'completed_at' => time(),
                    'total_products' => $result['total_products'] ?? 0,
                    'synced_products' => $result['synced_products'] ?? 0
                ]);
            } else {
                $this->updateSyncStatus('error', [
                    'error_at' => time(),
                    'error_message' => $result['message'] ?? 'Unknown error'
                ]);
            }

            return $result;

        } catch (Exception $e) {
            $this->updateSyncStatus('error', [
                'error_at' => time(),
                'error_message' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Synchronization failed: ' . $e->getMessage(),
                'error_code' => 'SYNC_ERROR'
            ];
        }
    }

    /**
     * @inheritDoc
     */
    public function getSyncStatus(): array
    {
        // TODO: Implement getting sync status from configuration
        // For now, return a default status
        return [
            'status' => 'done', // 'done', 'in_progress', 'error'
            'last_sync' => null,
            'total_products' => 0,
            'synced_products' => 0,
            'error_message' => null
        ];
    }

    /**
     * @inheritDoc
     */
    public function updateSyncStatus(string $status, array $data = []): bool
    {
        // TODO: Implement saving sync status to configuration
        // For now, just return true
        return true;
    }

    /**
     * Placeholder method for actual product synchronization
     * This will be implemented after discussion with mentor
     *
     * @return array
     */
    private function performProductSync(): array
    {
        // TODO:
        // 1. Get all products from PrestaShop
        // 2. Transform products to ChannelEngine format
        // 3. Call ChannelEngine API POST v2/products
        // 4. Handle response and errors

        // For now, simulate a successful sync
        return [
            'success' => true,
            'message' => 'Products synchronized successfully',
            'total_products' => 0,
            'synced_products' => 0
        ];
    }
}