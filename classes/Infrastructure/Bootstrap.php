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
use ChannelEngine\Data\Repository\ProductRepository;
use ChannelEngine\Infrastructure\DI\ServiceRegistry;
use ChannelEngine\Infrastructure\HTTP\HttpClient;
use ChannelEngine\Infrastructure\Response\HtmlResponse;
use Exception;

/*
 * Responsible for initializing dependencies
 */

class Bootstrap
{
    /**
     * Initializes dependencies
     *
     * @throws Exception
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

            ServiceRegistry::set(ProductRepositoryInterface::class, new ProductRepository());

            ServiceRegistry::set(ProductSyncServiceInterface::class, new ProductSyncService());
        } catch (\Throwable $e) {
            error_log("CRITICAL BOOTSTRAP FAILURE in init(): " .
                $e->getMessage() . "\n" . $e->getTraceAsString());
            if (!headers_sent()) {
                HtmlResponse::createInternalServerError(
                    "A critical error occurred during application startup.")->view();
            }

            exit;
        }
    }
}
