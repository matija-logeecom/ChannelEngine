<?php

use ChannelEngine\Infrastructure\Bootstrap;
use ChannelEngine\Infrastructure\Response\HtmlResponse;
use ChannelEngine\Presentation\Controller\ProductHookController;

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

try {
    Bootstrap::init();
} catch (Throwable $e) {
    HtmlResponse::createInternalServerError()->view();
}

class ChannelEngine extends Module
{
    private ?ProductHookController $productHookController = null;

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

    public function install(): bool
    {
        return parent::install() &&
            $this->installTab() &&
            $this->registerHooks();
    }

    public function uninstall(): bool
    {
        return parent::uninstall() && $this->uninstallTab();
    }

    /**
     * Register hooks for product synchronization
     */
    private function registerHooks(): bool
    {
        return $this->registerHook('actionProductAdd') &&
            $this->registerHook('actionProductUpdate');
    }

    /**
     * Get or create the product hook controller
     */
    private function getProductHookController(): ProductHookController
    {
        if ($this->productHookController === null) {
            try {
                $this->productHookController = new ProductHookController();
            } catch (Exception $e) {
                PrestaShopLogger::addLog(
                    'ChannelEngine: Failed to initialize ProductHookController: ' . $e->getMessage(),
                    3,
                    null,
                    'ChannelEngine'
                );
                throw $e;
            }
        }

        return $this->productHookController;
    }

    /**
     * Hook: Product added - sync to ChannelEngine
     */
    public function hookActionProductAdd($params): void
    {
        try {
            $this->getProductHookController()->handleProductAdd($params);
        } catch (Exception $e) {
            return;
        }
    }

    /**
     * Hook: Product updated - sync to ChannelEngine
     */
    public function hookActionProductUpdate($params): void
    {
        try {
            $this->getProductHookController()->handleProductUpdate($params);
        } catch (Exception $e) {
            return;
        }
    }

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
            PrestaShopLogger::addLog('ChannelEngine: Tab installation failed: ' . $e->getMessage(), 3);
            return false;
        }
    }

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
            PrestaShopLogger::addLog('ChannelEngine: Tab uninstall failed: ' . $e->getMessage(), 3);
            return false;
        }
    }
}