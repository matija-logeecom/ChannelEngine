<?php

namespace ChannelEngine\Infrastructure;

use ChannelEngine\Business\Interface\Proxy\ChannelEngineProxyInterface;
use ChannelEngine\Business\Interface\Repository\ConfigurationRepositoryInterface;
use ChannelEngine\Business\Interface\Repository\ProductRepositoryInterface;
use ChannelEngine\Business\Interface\Service\AuthorizationServiceInterface;
use ChannelEngine\Business\Interface\Service\ProductSyncServiceInterface;
use ChannelEngine\Business\Proxy\ChannelEngineProxy;
use ChannelEngine\Business\Service\AuthorizationService;
use ChannelEngine\Business\Service\ProductSyncService;
use ChannelEngine\Data\Repository\ConfigurationRepository;
use ChannelEngine\Infrastructure\DI\ServiceRegistry;
use ChannelEngine\Infrastructure\HTTP\HttpClient;
use PrestaShopLogger;

/*
 * Responsible for initializing dependencies
 */

class Bootstrap
{
    /**
     * Initializes dependencies
     */
    public static function init(): void
    {
        try {
            if (!defined('VIEWS_PATH')) {
                define('VIEWS_PATH', __DIR__ . '/views');
            }

            ServiceRegistry::set(HttpClient::class, new HttpClient());

            ServiceRegistry::set(ConfigurationRepositoryInterface::class, new ConfigurationRepository());

            ServiceRegistry::set(ChannelEngineProxyInterface::class, new ChannelEngineProxy());

            ServiceRegistry::set(AuthorizationServiceInterface::class, new AuthorizationService());

            ServiceRegistry::set(ProductSyncServiceInterface::class, new ProductSyncService());
        } catch (\Throwable $e) {
            PrestaShopLogger::addLog(
                'Critical error: Bootstrap failed! ' . $e->getMessage(),
                4,
                null,
                'ChannelEngine'
            );

            exit;
        }
    }
}
