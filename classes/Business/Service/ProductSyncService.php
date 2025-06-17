<?php

namespace ChannelEngine\Business\Service;

use ChannelEngine\Business\DTO\Product;
use ChannelEngine\Business\Interface\Proxy\ChannelEngineProxyInterface;
use ChannelEngine\Business\Interface\Repository\ConfigurationRepositoryInterface;
use ChannelEngine\Business\Interface\Repository\ProductRepositoryInterface;
use ChannelEngine\Business\Interface\Service\ProductSyncServiceInterface;
use ChannelEngine\Infrastructure\DI\ServiceRegistry;
use Configuration;
use Exception;
use PrestaShopLogger;
use RuntimeException;

class ProductSyncService implements ProductSyncServiceInterface
{
    private ConfigurationRepositoryInterface $configRepository;
    private ChannelEngineProxyInterface $channelEngineProxy;
    private ProductRepositoryInterface $productRepository;

    private const BATCH_SIZE = 100;

    public function __construct()
    {
        try {
            $this->configRepository = ServiceRegistry::get(ConfigurationRepositoryInterface::class);
            $this->channelEngineProxy = ServiceRegistry::get(ChannelEngineProxyInterface::class);
            $this->productRepository = ServiceRegistry::get(ProductRepositoryInterface::class);
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
            return $this->createErrorResponse('Not connected to ChannelEngine', 'NOT_CONNECTED');
        }

        try {
            $credentials = $this->getCredentials();
            $this->startSync();

            $totalProducts = $this->productRepository->getActiveProductsCount();
            if ($totalProducts === 0) {
                return $this->handleNoProducts();
            }

            $syncResult = $this->syncProductBatches($credentials, $totalProducts);
            return $this->finalizeSyncResult($syncResult, $totalProducts);

        } catch (Exception $e) {
            return $this->handleSyncError($e);
        }
    }

    /**
     * @inheritDoc
     */
    public function getSyncStatus(): array
    {
        try {
            $status = Configuration::get('CHANNELENGINE_SYNC_STATUS') ?: 'done';
            $lastSync = Configuration::get('CHANNELENGINE_LAST_SYNC');
            $syncData = Configuration::get('CHANNELENGINE_SYNC_DATA');

            $data = [
                'status' => $status,
                'last_sync' => $lastSync ? date('Y-m-d H:i:s', (int)$lastSync) : null,
                'total_products' => 0,
                'synced_products' => 0,
                'error_message' => null
            ];

            if ($syncData) {
                $decodedData = json_decode($syncData, true);
                if ($decodedData) {
                    $data = array_merge($data, $decodedData);
                }
            }

            return $data;
        } catch (Exception $e) {
            return $this->getDefaultSyncStatus();
        }
    }

    /**
     * @inheritDoc
     */
    public function updateSyncStatus(string $status, array $data = []): bool
    {
        try {
            Configuration::updateValue('CHANNELENGINE_SYNC_STATUS', $status);

            if ($status === 'done' || $status === 'error') {
                Configuration::updateValue('CHANNELENGINE_LAST_SYNC', time());
            }

            if (!empty($data)) {
                Configuration::updateValue('CHANNELENGINE_SYNC_DATA', json_encode($data));
            }

            return true;
        } catch (Exception $e) {
            $this->logError('Failed to update sync status: ' . $e->getMessage());

            return false;
        }
    }


    /**
     * Get credentials from configuration
     *
     * @return array
     *
     * @throws Exception
     */
    private function getCredentials(): array
    {
        $credentials = $this->configRepository->getCredentials();
        if (!$credentials) {
            throw new Exception('Failed to retrieve credentials');
        }
        return $credentials;
    }


    /**
     * Create new scratch file from selection
     *
     * @return void
     */
    private function startSync(): void
    {
        $this->updateSyncStatus('in_progress', [
            'started_at' => time(),
            'total_products' => 0,
            'synced_products' => 0
        ]);
    }

    /**
     * Handle case when there are no products to sync
     *
     * @return array
     */
    private function handleNoProducts(): array
    {
        $this->updateSyncStatus('done', [
            'completed_at' => time(),
            'total_products' => 0,
            'synced_products' => 0
        ]);

        return [
            'success' => true,
            'message' => 'No active products to synchronize',
            'total_products' => 0,
            'synced_products' => 0
        ];
    }

    /**
     * Sync products in batches
     *
     * @param array $credentials
     * @param int $totalProducts
     *
     * @return array
     */
    private function syncProductBatches(array $credentials, int $totalProducts): array
    {
        $syncedCount = 0;
        $errors = [];

        for ($offset = 0; $offset < $totalProducts; $offset += self::BATCH_SIZE) {
            $batchResult = $this->syncBatch($credentials, $offset, $totalProducts);
            $syncedCount += $batchResult['synced'];
            $errors = array_merge($errors, $batchResult['errors']);
        }

        return ['synced_count' => $syncedCount, 'errors' => $errors];
    }

    /**
     * Sync a single batch of products
     *
     * @param array $credentials
     * @param int $offset
     * @param int $totalProducts
     *
     * @return array
     */
    private function syncBatch(array $credentials, int $offset, int $totalProducts): array
    {
        $synced = 0;
        $errors = [];

        try {
            $products = $this->productRepository->getAllActiveProducts(self::BATCH_SIZE, $offset);
            if (empty($products)) {
                return ['synced' => 0, 'errors' => []];
            }

            $productDtos = $this->convertProductsToDto($products, $errors);

            if (!empty($productDtos)) {
                $synced = $this->sendProductsToChannelEngine($credentials, $productDtos);
                $this->updateProgress($totalProducts, $synced);
            }
        } catch (Exception $e) {
            $errors[] = "Batch error at offset $offset: " . $e->getMessage();
            $this->logError("Batch sync error at offset $offset: " . $e->getMessage());
        }

        return ['synced' => $synced, 'errors' => $errors];
    }

    /**
     * Convert products to DTOs
     *
     * @param array $products
     * @param array $errors
     *
     * @return array
     */
    private function convertProductsToDto(array $products, array &$errors): array
    {
        $productDtos = [];

        foreach ($products as $product) {
            try {
                $dto = Product::fromPrestashopProduct($product);
                $productDtos[] = $dto->toArray();
            } catch (Exception $e) {
                $errors[] = "Product ID {$product['id_product']}: " . $e->getMessage();
                $this->logError("Failed to convert product {$product['id_product']}: " . $e->getMessage());
            }
        }

        return $productDtos;
    }


    /**
     * Send products to ChannelEngine
     *
     * @param array $credentials
     * @param array $productDtos
     *
     * @return int
     *
     * @throws Exception
     */
    private function sendProductsToChannelEngine(array $credentials, array $productDtos): int
    {
        $result = $this->channelEngineProxy->syncProducts(
            $credentials['account_name'],
            $credentials['api_key'],
            $productDtos
        );

        return $result['success'] ? count($productDtos) : 0;
    }

    /**
     * Update sync progress
     *
     * @param int $totalProducts
     * @param int $syncedProducts
     *
     * @return void
     */
    private function updateProgress(int $totalProducts, int $syncedProducts): void
    {
        $this->updateSyncStatus('in_progress', [
            'total_products' => $totalProducts,
            'synced_products' => $syncedProducts
        ]);
    }

    /**
     * Finalize sync result based on outcome
     *
     * @param array $syncResult
     * @param int $totalProducts
     *
     * @return array
     */
    private function finalizeSyncResult(array $syncResult, int $totalProducts): array
    {
        $syncedCount = $syncResult['synced_count'];
        $errors = $syncResult['errors'];

        if ($syncedCount === 0 && !empty($errors)) {
            return $this->handleCompleteFailure($errors, $totalProducts);
        } elseif (!empty($errors)) {
            return $this->handlePartialSuccess($syncedCount, $totalProducts, $errors);
        } else {
            return $this->handleCompleteSuccess($syncedCount, $totalProducts);
        }
    }

    /**
     * Handle complete sync failure
     *
     * @param array $errors
     * @param int $totalProducts
     *
     * @return array
     */
    private function handleCompleteFailure(array $errors, int $totalProducts): array
    {
        $this->updateSyncStatus('error', [
            'error_at' => time(),
            'error_message' => 'Failed to sync any products. Errors: ' . implode('; ', array_slice($errors, 0, 5))
        ]);

        return [
            'success' => false,
            'message' => 'Product synchronization failed',
            'error_code' => 'SYNC_FAILED',
            'errors' => $errors,
            'total_products' => $totalProducts,
            'synced_products' => 0
        ];
    }

    /**
     * Handle partial sync success
     *
     * @param int $syncedCount
     * @param int $totalProducts
     * @param array $errors
     *
     * @return array
     */
    private function handlePartialSuccess(int $syncedCount, int $totalProducts, array $errors): array
    {
        $this->updateSyncStatus('done', [
            'completed_at' => time(),
            'total_products' => $totalProducts,
            'synced_products' => $syncedCount,
            'has_errors' => true,
            'error_count' => count($errors)
        ]);

        $this->logWarning("Product sync completed with errors. Synced: $syncedCount/$totalProducts");

        return [
            'success' => true,
            'message' => "Products synchronized with some errors. Synced: $syncedCount/$totalProducts",
            'total_products' => $totalProducts,
            'synced_products' => $syncedCount,
            'errors' => array_slice($errors, 0, 10)
        ];
    }

    /**
     * Handle complete sync success
     *
     * @param int $syncedCount
     * @param int $totalProducts
     *
     * @return array
     */
    private function handleCompleteSuccess(int $syncedCount, int $totalProducts): array
    {
        $this->updateSyncStatus('done', [
            'completed_at' => time(),
            'total_products' => $totalProducts,
            'synced_products' => $syncedCount
        ]);

        $this->logInfo("Product sync completed successfully. Synced: $syncedCount products");

        return [
            'success' => true,
            'message' => "All products synchronized successfully. Total: $syncedCount",
            'total_products' => $totalProducts,
            'synced_products' => $syncedCount
        ];
    }

    /**
     * Handle sync error
     *
     * @param Exception $e
     *
     * @return array
     */
    private function handleSyncError(Exception $e): array
    {
        $this->updateSyncStatus('error', [
            'error_at' => time(),
            'error_message' => $e->getMessage()
        ]);

        $this->logError("Product sync failed: " . $e->getMessage());

        return [
            'success' => false,
            'message' => 'Synchronization failed: ' . $e->getMessage(),
            'error_code' => 'SYNC_ERROR'
        ];
    }

    /**
     * Create error response
     *
     * @param string $message
     * @param string $errorCode
     *
     * @return array
     */
    private function createErrorResponse(string $message, string $errorCode): array
    {
        return [
            'success' => false,
            'message' => $message,
            'error_code' => $errorCode
        ];
    }

    /**
     * Get default sync status
     *
     * @return array
     */
    private function getDefaultSyncStatus(): array
    {
        return [
            'status' => 'error',
            'last_sync' => null,
            'total_products' => 0,
            'synced_products' => 0,
            'error_message' => 'Failed to get sync status'
        ];
    }

    /**
     * Log error message
     *
     * @param string $message
     *
     * @return void
     */
    private function logError(string $message): void
    {
        PrestaShopLogger::addLog('ChannelEngine: ' . $message, 3,
            null, 'ChannelEngine');
    }

    /**
     * Log warning message
     *
     * @param string $message
     *
     * @return void
     */
    private function logWarning(string $message): void
    {
        PrestaShopLogger::addLog('ChannelEngine: ' . $message,
            2, null, 'ChannelEngine');
    }

    /**
     * Log info message
     *
     * @param string $message
     *
     * @return void
     */
    private function logInfo(string $message): void
    {
        PrestaShopLogger::addLog('ChannelEngine: ' . $message, 1,
            null, 'ChannelEngine');
    }
}