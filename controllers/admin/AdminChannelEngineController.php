<?php

class AdminChannelEngineController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->display = 'view';

        parent::__construct();

        $this->meta_title = $this->trans('Channel Engine');
    }

    public function renderView()
    {
        $this->content = $this->renderWelcomePage();
        return parent::renderView();
    }

    private function renderWelcomePage()
    {
        // Add CSS and JS files to the page
        $this->context->controller->addCSS($this->module->getPathUri() . 'views/css/admin.css');
        $this->context->controller->addJS($this->module->getPathUri() . 'views/js/ChannelEngineAjax.js');
        $this->context->controller->addJS($this->module->getPathUri() . 'views/js/admin.js');

        // Get the template path
        $template_path = $this->module->getLocalPath() . 'views/templates/admin/welcome.tpl';

        // Assign template variables
        $this->context->smarty->assign(array(
            'module_dir' => $this->module->getPathUri(),
            'module_name' => $this->module->name,
            'module_version' => $this->module->version,
        ));

        // Render the template
        return $this->context->smarty->fetch($template_path);
    }

    /**
     * Handle AJAX requests
     */
    public function ajaxProcess()
    {
        // Get JSON input for requests
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            $this->ajaxDie(json_encode(array(
                'success' => false,
                'message' => 'Invalid request data'
            )));
        }

        // Simple connection handler
        if (isset($input['account_name']) && isset($input['api_key'])) {
            $this->handleConnect($input['account_name'], $input['api_key']);
        } else {
            $this->ajaxDie(json_encode(array(
                'success' => false,
                'message' => 'Account name and API key required'
            )));
        }
    }

    /**
     * Handle connection
     */
    private function handleConnect($account_name, $api_key)
    {
        // TODO: Add actual ChannelEngine API validation here

        // For now, just simulate success
        $this->ajaxDie(json_encode(array(
            'success' => true,
            'message' => 'Connected successfully to ChannelEngine!'
        )));
    }
}