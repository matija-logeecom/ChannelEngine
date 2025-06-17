<?php

namespace ChannelEngine\Presentation\Controller;

use ChannelEngine\Business\Interface\Service\ProductSyncServiceInterface;
use ChannelEngine\Infrastructure\DI\ServiceRegistry;
use Exception;
use PrestaShopLogger;

class ProductHookController
{
    private ProductSyncServiceInterface $productSyncService;

    public function __construct()
    {
        try {
            $this->productSyncService = ServiceRegistry::get(ProductSyncServiceInterface::class);
        } catch (Exception $e) {
            PrestaShopLogger::addLog(
                'ChannelEngine: ProductHookController initialization failed: ' . $e->getMessage(),
                3,
                null,
                'ChannelEngine'
            );
        }
    }

    /**
     * Handle product addition hook
     *
     * @param array $params
     *
     * @return void
     */
    public function handleProductAdd(array $params): void
    {
        try {
            $productId = $params['id_product'];
            if (!$productId) {
                return;
            }

            $this->productSyncService->syncSingleProduct($productId);
        } catch (Exception $e) {
            PrestaShopLogger::addLog(
                'ChannelEngine: Failed to handle product add: ' . $e->getMessage(),
                3,
                null,
                'ChannelEngine'
            );
        }
    }

    /**
     * Handle product update hook
     *
     * @param array $params
     *
     * @return void
     */
    public function handleProductUpdate(array $params): void
    {
        try {
            $productId = $params['id_product'];
            if (!$productId) {
                return;
            }

            $this->productSyncService->syncSingleProduct($productId);
        } catch (Exception $e) {
            PrestaShopLogger::addLog(
                'ChannelEngine: Failed to handle product update: ' . $e->getMessage(),
                3,
                null,
                'ChannelEngine'
            );
        }
    }
}