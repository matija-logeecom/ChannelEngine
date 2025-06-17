<?php

use ChannelEngine\Business\Interface\AuthorizationServiceInterface;
use ChannelEngine\Business\Interface\ProductSyncServiceInterface;
use ChannelEngine\Infrastructure\DI\ServiceRegistry;
use ChannelEngine\Infrastructure\Request\Request;
use ChannelEngine\Infrastructure\Response\JsonResponse;

class AdminChannelEngineController extends ModuleAdminController
{
    private AuthorizationServiceInterface $authService;
    private ProductSyncServiceInterface $productSyncService;

    public function __construct()
    {
        $this->bootstrap = true;
        $this->display = 'view';

        parent::__construct();

        $this->meta_title = $this->trans('Channel Engine');

        try {
            $this->authService = ServiceRegistry::get(AuthorizationServiceInterface::class);
            $this->productSyncService = ServiceRegistry::get(ProductSyncServiceInterface::class);
        } catch (Exception $e) {
            error_log("CRITICAL: AdminChannelEngineController could not be initialized.
             Failed to get required services. Original error: " . $e->getMessage());
            throw new RuntimeException(
                "AdminChannelEngineController failed to initialize due to a
                 missing critical dependency.", 0, $e
            );
        }
    }

    /**
     * Main render method - determines which page to show based on connection status
     *
     * @return string
     *
     * @throws Exception
     */
    public function renderView(): string
    {
        try {
            $isConnected = $this->authService->isConnected();

            if ($isConnected) {
                $this->content = $this->renderSyncPage();
            } else {
                $this->content = $this->renderWelcomePage();
            }
        } catch (Exception $e) {
            $this->content = $this->renderWelcomePage();
        }

        return parent::renderView();
    }

    /**
     * Render the welcome/connection page
     *
     * @return string
     *
     * @throws Exception
     */
    private function renderWelcomePage(): string
    {
        $this->context->controller->addCSS($this->module->getPathUri() . 'views/css/admin.css');
        $this->context->controller->addJS($this->module->getPathUri() . 'views/js/ChannelEngineAjax.js');
        $this->context->controller->addJS($this->module->getPathUri() . 'views/js/admin.js');

        $template_path = $this->module->getLocalPath() . 'views/templates/admin/welcome.tpl';

        try {
            $connectionInfo = $this->authService->getConnectionStatus();
        } catch (Exception $e) {
            $connectionInfo = ['data' => ['is_connected' => false]];
        }

        $this->context->smarty->assign(array(
            'module_dir' => $this->module->getPathUri(),
            'module_name' => $this->module->name,
            'module_version' => $this->module->version,
            'connection_info' => $connectionInfo['data']
        ));

        return $this->context->smarty->fetch($template_path);
    }

    /**
     * Handle AJAX requests - routes to appropriate action handlers
     *
     * @return void
     *
     * @throws Exception
     */
    public function ajaxProcess(): void
    {
        try {
            $request = new Request();
            $requestData = $request->getBody();

            $action = $requestData['action'] ?? 'connect';

            $response = match($action) {
                'connect' => $this->handleConnect($requestData),
                'disconnect' => $this->handleDisconnect(),
                'status' => $this->handleGetStatus(),
                'sync' => $this->handleSync(),
                'sync_status' => $this->handleGetSyncStatus(),
                default => $this->createErrorResponse('Invalid action', 400)
            };

            $response->view();
            exit;

        } catch (Exception $e) {
            $errorResponse = $this->createErrorResponse(
                'Internal server error: ' . $e->getMessage(),
                500,
                'INTERNAL_ERROR'
            );
            $errorResponse->view();
            exit;
        }
    }

    /**
     * Handle connection request
     *
     * @param array $requestData
     *
     * @return JsonResponse
     */
    private function handleConnect(array $requestData): JsonResponse
    {
        $accountName = $requestData['account_name'] ?? '';
        $apiKey = $requestData['api_key'] ?? '';

        if (empty($accountName) || empty($apiKey)) {
            return $this->createErrorResponse('Account name and API key are required', 400, 'MISSING_CREDENTIALS');
        }

        $result = $this->authService->authorizeConnection($accountName, $apiKey);
        $statusCode = $result['success'] ? 200 : 400;

        return new JsonResponse($result, $statusCode);
    }

    /**
     * Handle disconnect request
     *
     * @return JsonResponse
     */
    private function handleDisconnect(): JsonResponse
    {
        $result = $this->authService->disconnect();
        $statusCode = $result['success'] ? 200 : 400;

        return new JsonResponse($result, $statusCode);
    }

    /**
     * Handle get status request
     *
     * @return JsonResponse
     */
    private function handleGetStatus(): JsonResponse
    {
        $result = $this->authService->getConnectionStatus();

        return new JsonResponse($result, 200);
    }

    /**
     * Handle product sync request
     *
     * @return JsonResponse
     */
    private function handleSync(): JsonResponse
    {
        $result = $this->productSyncService->syncAllProducts();
        $statusCode = $result['success'] ? 200 : 400;

        return new JsonResponse($result, $statusCode);
    }

    /**
     * Handle get sync status request
     *
     * @return JsonResponse
     */
    private function handleGetSyncStatus(): JsonResponse
    {
        $result = $this->productSyncService->getSyncStatus();

        return new JsonResponse([
            'success' => true,
            'data' => $result
        ], 200);
    }

    /**
     * Create a standardized error response
     *
     * @param string $message
     * @param int $statusCode
     * @param string|null $errorCode
     *
     * @return JsonResponse
     */
    private function createErrorResponse(string $message, int $statusCode, ?string $errorCode = null): JsonResponse
    {
        $data = [
            'success' => false,
            'message' => $message
        ];

        if ($errorCode) {
            $data['error_code'] = $errorCode;
        }

        return new JsonResponse($data, $statusCode);
    }

    /**
     * Render the synchronization page
     *
     * @return string
     *
     * @throws Exception
     */
    private function renderSyncPage(): string
    {
        $this->context->controller->addCSS($this->module->getPathUri() . 'views/css/admin.css');
        $this->context->controller->addCSS($this->module->getPathUri() . 'views/css/sync.css');
        $this->context->controller->addJS($this->module->getPathUri() . 'views/js/ChannelEngineAjax.js');
        $this->context->controller->addJS($this->module->getPathUri() . 'views/js/admin.js');

        $template_path = $this->module->getLocalPath() . 'views/templates/admin/sync.tpl';

        try {
            $connectionInfo = $this->authService->getConnectionStatus();
            $syncStatus = $this->productSyncService->getSyncStatus();
        } catch (Exception $e) {
            $connectionInfo = ['data' => []];
            $syncStatus = ['status' => 'error'];
        }

        $this->context->smarty->assign(array(
            'module_dir' => $this->module->getPathUri(),
            'module_name' => $this->module->name,
            'connection_info' => $connectionInfo['data'],
            'sync_status' => $syncStatus
        ));

        return $this->context->smarty->fetch($template_path);
    }
}