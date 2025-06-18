<?php

namespace ChannelEngine\Business\Service;

use ChannelEngine\Business\DTO\AccountData;
use ChannelEngine\Business\DTO\Product;
use ChannelEngine\Business\Interface\Proxy\ChannelEngineProxyInterface;
use ChannelEngine\Business\Interface\Repository\ConfigurationRepositoryInterface;
use ChannelEngine\Business\Interface\Service\ProductSyncServiceInterface;
use ChannelEngine\Infrastructure\DI\ServiceRegistry;
use Configuration;
use Context;
use Exception;
use Image;
use Manufacturer;
use PrestaShopLogger;
use Product as PrestaShopProduct;
use RuntimeException;
use StockAvailable;
use Validate;

class ProductSyncService implements ProductSyncServiceInterface
{
    private ConfigurationRepositoryInterface $configRepository;
    private ChannelEngineProxyInterface $channelEngineProxy;

    private const BATCH_SIZE = 100;

    public function __construct()
    {
        try {
            $this->configRepository = ServiceRegistry::get(ConfigurationRepositoryInterface::class);
            $this->channelEngineProxy = ServiceRegistry::get(ChannelEngineProxyInterface::class);
        } catch (Exception $e) {
            throw new RuntimeException(
                "ProductSyncService failed to initialize due to a missing critical dependency.",
                0,
                $e
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
            $accountData = $this->getCredentials();
            $this->startSync();

            $syncResult = $this->syncProductBatches($accountData);
            return $this->finalizeSyncResult($syncResult);

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
     * @inheritDoc
     */
    public function syncSingleProduct(int $productId): array
    {
        if (!$this->configRepository->hasCredentials()) {
            return $this->createErrorResponse('Not connected to ChannelEngine', 'NOT_CONNECTED');
        }

        try {
            $accountData = $this->getCredentials();
            $product = $this->getProductById($productId);

            if (!$product) {
                return $this->createErrorResponse(
                    "Product with ID $productId not found or inactive",
                    'PRODUCT_NOT_FOUND'
                );
            }

            $productDto = Product::fromPrestashopProduct($product);
            $productData = [$productDto->toArray()];

            $result = $this->channelEngineProxy->syncProducts(
                $accountData->getAccountName(),
                $accountData->getApiKey(),
                $productData
            );

            if ($result['success']) {
                $this->logInfo("Single product sync successful for product ID: $productId");
                return [
                    'success' => true,
                    'message' => "Product $productId synchronized successfully",
                    'product_id' => $productId,
                    'product_name' => $product['name'] ?? ''
                ];
            } else {
                throw new Exception($result['message'] ?? 'Unknown sync error');
            }

        } catch (Exception $e) {
            $this->logError("Single product sync failed for product ID $productId: " . $e->getMessage());
            return $this->createErrorResponse(
                "Failed to sync product $productId: " . $e->getMessage(),
                'SYNC_ERROR'
            );
        }
    }

    /**
     * Get credentials from configuration
     *
     * @return AccountData
     *
     * @throws Exception
     */
    private function getCredentials(): AccountData
    {
        $accountData = $this->configRepository->getAccountData();
        if (!$accountData) {
            throw new Exception('Failed to retrieve account data');
        }
        return $accountData;
    }

    /**
     * Start sync process
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
     * Sync products in batches using PrestaShop's native methods
     *
     * @param AccountData $accountData
     *
     * @return array
     */
    private function syncProductBatches(AccountData $accountData): array
    {
        $syncedCount = 0;
        $errors = [];
        $offset = 0;
        $totalProducts = 0;

        while (true) {
            $products = PrestaShopProduct::getProducts(
                Context::getContext()->language->id,
                $offset,
                self::BATCH_SIZE,
                'id_product',
                'ASC',
                false,
                true,
                Context::getContext()
            );

            if (empty($products)) {
                break;
            }

            $batchResult = $this->syncBatch($accountData, $products);
            $syncedCount += $batchResult['synced'];
            $errors = array_merge($errors, $batchResult['errors']);
            $totalProducts += count($products);

            $offset += self::BATCH_SIZE;
        }

        return [
            'synced_count' => $syncedCount,
            'errors' => $errors,
            'total_products' => $totalProducts
        ];
    }

    /**
     * Sync a single batch of products
     *
     * @param AccountData $accountData
     * @param array $products
     *
     * @return array
     */
    private function syncBatch(AccountData $accountData, array $products): array
    {
        $synced = 0;
        $errors = [];

        try {
            $productDtos = $this->convertProductsToDto($products, $errors);

            if (!empty($productDtos)) {
                $synced = $this->sendProductsToChannelEngine($accountData, $productDtos);
            }
        } catch (Exception $e) {
            $errors[] = "Batch sync error: " . $e->getMessage();
            $this->logError("Batch sync error: " . $e->getMessage());
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

        foreach ($products as $productData) {
            try {
                $productId = (int)$productData['id_product'];
                $product = $this->getProductById($productId);

                if ($product) {
                    $dto = Product::fromPrestashopProduct($product);
                    $productDtos[] = $dto->toArray();
                }
            } catch (Exception $e) {
                $errors[] = "Product ID {$productData['id_product']}: " . $e->getMessage();
                $this->logError("Failed to convert product {$productData['id_product']}: " . $e->getMessage());
            }
        }

        return $productDtos;
    }

    /**
     * Get product by ID using PrestaShop's native methods
     *
     * @param int $productId
     *
     * @return array|null
     */
    private function getProductById(int $productId): ?array
    {
        $context = Context::getContext();
        $product = new PrestaShopProduct($productId, false, $context->language->id, $context->shop->id);

        if (!Validate::isLoadedObject($product) || !$product->active) {
            return null;
        }

        $manufacturerName = '';
        if ($product->id_manufacturer) {
            $manufacturer = new Manufacturer($product->id_manufacturer, $context->language->id);
            if (Validate::isLoadedObject($manufacturer)) {
                $manufacturerName = $manufacturer->name;
            }
        }

        $stockQuantity = StockAvailable::getQuantityAvailableByProduct($productId, null, $context->shop->id);

        $imageUrl = $this->getProductImageUrl($productId, $product);

        $specificPriceOutput =  null;
        $price = PrestaShopProduct::getPriceStatic(
            $productId,
            true,
            null,
            6,
            null,
            false,
            true,
            1,
            false,
            null,
            null,
            null,
            $specificPriceOutput,
            true,
            true,
            $context
        );

        return [
            'id_product' => $product->id,
            'reference' => $product->reference,
            'name' => $product->name,
            'description' => $product->description,
            'description_short' => $product->description_short,
            'manufacturer_name' => $manufacturerName,
            'price' => (float)$price,
            'stock_quantity' => (int)$stockQuantity,
            'image_url' => $imageUrl
        ];
    }

    /**
     * Get product image URL using PrestaShop's native Image class
     *
     * @param int $productId
     * @param PrestaShopProduct $product
     *
     * @return string
     */
    private function getProductImageUrl(int $productId, PrestaShopProduct $product): string
    {
        try {
            $context = Context::getContext();
            $images = Image::getImages($context->language->id, $productId);

            if (!empty($images)) {
                $image = $images[0];
                $imageId = (int) $image['id_image'];

                $imageUrl = $context->link->getImageLink(
                    $product->link_rewrite ?: 'product',
                    $productId . '-' . $imageId,
                    'large_default'
                );

                if (!empty($imageUrl) && !str_starts_with($imageUrl, 'http')) {
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://';
                    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    $imageUrl = $protocol . $host . '/' . ltrim($imageUrl, '/');
                }

                PrestaShopLogger::addLog(
                    'ChannelEngine: Generated image URL for product ' . $productId . ': ' . $imageUrl,
                    1,
                    null,
                    'ChannelEngine'
                );

                return $imageUrl;
            }

            return '';
        } catch (\Exception $e) {
            PrestaShopLogger::addLog(
                'ChannelEngine: Failed to get product image URL for product ID ' . $productId . ': ' . $e->getMessage(),
                3,
                null,
                'ChannelEngine'
            );

            return '';
        }
    }

    /**
     * Send products to ChannelEngine
     *
     * @param AccountData $accountData
     * @param array $productDtos
     * @return int
     *
     * @throws Exception
     */
    private function sendProductsToChannelEngine(AccountData $accountData, array $productDtos): int
    {
        $result = $this->channelEngineProxy->syncProducts(
            $accountData->getAccountName(),
            $accountData->getApiKey(),
            $productDtos
        );

        return $result['success'] ? count($productDtos) : 0;
    }

    /**
     * Finalize sync result based on outcome
     *
     * @param array $syncResult
     *
     * @return array
     */
    private function finalizeSyncResult(array $syncResult): array
    {
        $syncedCount = $syncResult['synced_count'];
        $errors = $syncResult['errors'];
        $totalProducts = $syncResult['total_products'];

        if ($totalProducts === 0) {
            return $this->handleNoProducts();
        }

        if ($syncedCount === 0 && !empty($errors)) {
            return $this->handleCompleteFailure($errors, $totalProducts);
        }
        if ($syncedCount !== 0 && !empty($errors)) {
            return $this->handlePartialSuccess($syncedCount, $totalProducts, $errors);
        }

        return $this->handleCompleteSuccess($syncedCount, $totalProducts);
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
        PrestaShopLogger::addLog('ChannelEngine: ' . $message, 3, null, 'ChannelEngine');
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
        PrestaShopLogger::addLog('ChannelEngine: ' . $message, 2, null, 'ChannelEngine');
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
        PrestaShopLogger::addLog('ChannelEngine: ' . $message, 1, null, 'ChannelEngine');
    }
}