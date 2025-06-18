<?php

use ChannelEngine\Business\Interface\Service\ProductSyncServiceInterface;
use ChannelEngine\Infrastructure\Bootstrap;
use ChannelEngine\Infrastructure\DI\ServiceRegistry;
use ChannelEngine\Infrastructure\Response\HtmlResponse;

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

Bootstrap::init();

class ChannelEngine extends Module
{
    public function __construct()
    {
        $this->name = 'channelengine';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'Matija Stankovic';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '1.7.0.0',
            'max' => '8.99.99',
        ];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('Channel Engine', [], 'Modules.ChannelEngine.Admin');
        $this->description = $this->trans('Channel Engine integration plugin.',
            [], 'Modules.ChannelEngine.Admin');
        $this->confirmUninstall = $this->trans('Are you sure you want to uninstall?',
            [], 'Modules.ChannelEngine.Admin');

        if (!Configuration::get('CHANNELENGINE_NAME')) {
            $this->warning = $this->trans('No name provided', [], 'Modules.ChannelEngine.Admin');
        }
    }

    /**
     * Install the module and register necessary hooks
     *
     * @return bool
     */
    public function install(): bool
    {
        return parent::install() &&
            $this->installTab() &&
            $this->registerHooks();
    }

    /**
     * Uninstall the module and clean up
     *
     * @return bool
     */
    public function uninstall(): bool
    {
        return parent::uninstall() && $this->uninstallTab();
    }

    /**
     * Register hooks for product synchronization
     *
     * @return bool
     */
    private function registerHooks(): bool
    {
        return $this->registerHook('actionProductAdd') &&
            $this->registerHook('actionProductUpdate');
    }

    /**
     * Hook: Product added - sync to ChannelEngine
     *
     * @param array $params Hook parameters containing product information
     *
     * @return void
     */
    public function hookActionProductAdd($params): void
    {
        $this->handleProductSync($params);
    }

    /**
     * Hook: Product updated - sync to ChannelEngine
     *
     * @param array $params Hook parameters containing product information
     *
     * @return void
     */
    public function hookActionProductUpdate($params): void
    {
        $this->handleProductSync($params);
    }

    /**
     * Handle product synchronization for both add and update operations
     *
     * @param array $params Hook parameters containing product information
     *
     * @return void
     */
    private function handleProductSync(array $params): void
    {
        try {
            $productId = $params['id_product'] ?? null;

            if (!$productId) {
                PrestaShopLogger::addLog(
                    'ChannelEngine: Product sync skipped - no product ID provided in hook parameters',
                    2,
                    null,
                    'ChannelEngine'
                );
                return;
            }

            $productSyncService = ServiceRegistry::get(ProductSyncServiceInterface::class);
            $result = $productSyncService->syncSingleProduct((int)$productId);

            if (!$result['success']) {
                PrestaShopLogger::addLog(
                    'ChannelEngine: Product sync failed for product ID ' . $productId . ': ' . ($result['message'] ?? 'Unknown error'),
                    3,
                    null,
                    'ChannelEngine'
                );
            } else {
                PrestaShopLogger::addLog(
                    'ChannelEngine: Product sync successful for product ID ' . $productId,
                    1,
                    null,
                    'ChannelEngine'
                );
            }

        } catch (Exception $e) {
            PrestaShopLogger::addLog(
                'ChannelEngine: Failed to handle product sync: ' . $e->getMessage(),
                3,
                null,
                'ChannelEngine'
            );
        }
    }

    /**
     * Install admin tab for ChannelEngine management
     *
     * @return bool
     */
    private function installTab(): bool
    {
        try {
            $parentTabCollection = new PrestaShopCollection('Tab');
            $parentTabCollection->where('class_name', '=', 'AdminParentOrders');
            $parentTab = $parentTabCollection->getFirst();

            if ($parentTab && Validate::isLoadedObject($parentTab)) {
                $parentTabId = (int)$parentTab->id;
            } else {
                $parentTabId = 2;
            }

            $tab = new Tab();
            $tab->active = 1;
            $tab->class_name = 'AdminChannelEngine';
            $tab->name = [];

            foreach (Language::getLanguages(true) as $lang) {
                $tab->name[$lang['id_lang']] = $this->trans('Channel Engine', [], 'Modules.ChannelEngine.Admin');
            }

            $tab->id_parent = $parentTabId;
            $tab->module = $this->name;

            return $tab->add();

        } catch (Exception $e) {
            PrestaShopLogger::addLog(
                'ChannelEngine: Tab installation failed: ' . $e->getMessage(),
                3,
                null,
                'ChannelEngine'
            );
            return false;
        }
    }

    /**
     * Uninstall admin tab
     *
     * @return bool
     */
    private function uninstallTab(): bool
    {
        try {
            $tabCollection = new PrestaShopCollection('Tab');
            $tabCollection->where('class_name', '=', 'AdminChannelEngine');
            $tabCollection->where('module', '=', $this->name);
            $tab = $tabCollection->getFirst();

            if ($tab && Validate::isLoadedObject($tab)) {
                return $tab->delete();
            }

            return true;

        } catch (Exception $e) {
            PrestaShopLogger::addLog(
                'ChannelEngine: Tab uninstall failed: ' . $e->getMessage(),
                3,
                null,
                'ChannelEngine'
            );

            return false;
        }
    }
}