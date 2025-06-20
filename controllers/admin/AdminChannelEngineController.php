<?php

use ChannelEngine\Business\Interface\Repository\ConfigurationRepositoryInterface;
use ChannelEngine\Business\Interface\Service\AuthorizationServiceInterface;
use ChannelEngine\Business\Interface\Service\ProductSyncServiceInterface;
use ChannelEngine\Infrastructure\DI\ServiceRegistry;
use ChannelEngine\Infrastructure\Request\Request;
use ChannelEngine\Infrastructure\Response\JsonResponse;

class AdminChannelEngineController extends ModuleAdminController
{
    private AuthorizationServiceInterface $authService;
    private ProductSyncServiceInterface $productSyncService;
    private ConfigurationRepositoryInterface $configRepository;

    public function __construct()
    {
        $this->bootstrap = true;
        $this->display = 'view';

        parent::__construct();

        $this->meta_title = $this->trans('Channel Engine');

        try {
            $this->authService = ServiceRegistry::get(AuthorizationServiceInterface::class);
            $this->productSyncService = ServiceRegistry::get(ProductSyncServiceInterface::class);
            $this->configRepository = ServiceRegistry::get(ConfigurationRepositoryInterface::class);
        } catch (Exception $e) {
            PrestaShopLogger::addLog(
                'ChannelEngine: AdminChannelEngineController could not be initialized. Failed to get required services. Original error: ' . $e->getMessage(),
                3,
                null,
                'ChannelEngine'
            );
            throw new RuntimeException(
                "AdminChannelEngineController failed to initialize due to a missing critical dependency.",
                0,
                $e
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

            $action = $requestData['action'] ?? 'status';

            $response = match($action) {
                'connect' => $this->handleConnect($requestData),
                'disconnect' => $this->handleDisconnect(),
                'status' => $this->handleGetStatus(),
                'sync' => $this->handleSync(),
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
     * Render the welcome/connection page
     *
     * @return string
     *
     * @throws Exception
     */
    private function renderWelcomePage(): string
    {
        $this->addAssets();

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
     * Render the synchronization page
     *
     * @return string
     *
     * @throws Exception
     */
    private function renderSyncPage(): string
    {
        $this->addAssets();
        $this->context->controller->addCSS($this->module->getPathUri() . 'views/css/sync.css');

        $template_path = $this->module->getLocalPath() . 'views/templates/admin/sync.tpl';

        try {
            $connectionInfo = $this->authService->getConnectionStatus();
        } catch (Exception $e) {
            $connectionInfo = ['data' => []];
        }

        $this->context->smarty->assign(array(
            'module_dir' => $this->module->getPathUri(),
            'module_name' => $this->module->name,
            'connection_info' => $connectionInfo['data']
        ));

        return $this->context->smarty->fetch($template_path);
    }

    /**
     * Add common CSS and JS assets
     *
     * @return void
     */
    private function addAssets(): void
    {
        $this->context->controller->addCSS($this->module->getPathUri() . 'views/css/admin.css');
        $this->context->controller->addJS($this->module->getPathUri() . 'views/js/ChannelEngineAjax.js');
        $this->context->controller->addJS($this->module->getPathUri() . 'views/js/admin.js');
    }

    /**
     * Handle connection request with proper exception handling for proxy errors
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
            return $this->createErrorResponse(
                'Account name and API key are required',
                400,
                'MISSING_CREDENTIALS'
            );
        }

        try {
            $result = $this->authService->authorizeConnection($accountName, $apiKey);
            $statusCode = $result['success'] ? 200 : 400;

            return new JsonResponse($result, $statusCode);
        } catch (Exception $e) {
            $this->configRepository->updateConnectionStatus('failed');

            $message = $e->getMessage();
            $errorCode = 'API_ERROR';

            if (str_contains($message, 'HTTP 401')) {
                $message = 'Invalid account or API key. Please check your credentials.';
                $errorCode = 'INVALID_CREDENTIALS';
            }
            if (str_contains($message, 'HTTP 404')) {
                $message = 'Invalid account name. Please check your account name.';
                $errorCode = 'INVALID_ACCOUNT';
            }
            if (str_contains($message, 'HTTP 403')) {
                $message = 'Access forbidden. Please check your API permissions.';
                $errorCode = 'ACCESS_FORBIDDEN';
            }
            if (str_contains($message, 'HTTP 5')) {
                $message = 'ChannelEngine server error. Please try again later.';
                $errorCode = 'SERVER_ERROR';
            }

            return $this->createErrorResponse($message, 400, $errorCode);
        }
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
     * Create a standardized error response
     *
     * @param string $message
     * @param int $statusCode
     * @param string|null $errorCode
     *
     * @return JsonResponse
     */
    private function createErrorResponse(
        string $message,
        int $statusCode,
        ?string $errorCode = null
    ): JsonResponse {
        $data = [
            'success' => false,
            'message' => $message
        ];

        if ($errorCode) {
            $data['error_code'] = $errorCode;
        }

        return new JsonResponse($data, $statusCode);
    }
}