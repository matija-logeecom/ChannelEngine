<?php

namespace ChannelEngine\Infrastructure;

use ChannelEngine\Business\Interface\ChannelEngineProxyInterface;
use ChannelEngine\Business\Interface\ConfigurationRepositoryInterface;
use ChannelEngine\Business\Proxy\ChannelEngineProxy;
use ChannelEngine\Business\Service\AuthorizationService;
use ChannelEngine\Data\Repository\ConfigurationRepository;
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

            ServiceRegistry::set(AuthorizationService::class, new AuthorizationService());
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
