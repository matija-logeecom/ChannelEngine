<?php

namespace ChannelEngine\Infrastructure;

use ChannelEngine\Data\Repository\ConfigurationRepository;
use ChannelEngine\Service\Interface\ConfigurationRepositoryInterface;
use ChannelEngine\Infrastructure\DI\ServiceRegistry;
use ChannelEngine\Infrastructure\Response\HtmlResponse;
use ChannelEngine\Service\AuthorizationService;
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
            ServiceRegistry::set(ConfigurationRepositoryInterface::class, new ConfigurationRepository());

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
