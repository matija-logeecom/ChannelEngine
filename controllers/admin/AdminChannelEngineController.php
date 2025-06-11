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

    public function renderView(): string
    {
        $this->content = $this->renderLoginForm();
        return parent::renderView();
    }

    private function renderLoginForm(): string
    {
        $this->addCSS($this->module->getPathUri() . 'views/css/admin.css');
        $this->addJS($this->module->getPathUri() . 'views/js/admin.js');

        return $this->renderTemplate('welcome.tpl');
    }

    private function renderTemplate(string $template): string
    {
        $templatePath = $this->module->getLocalPath() . '/views/templates/admin/' . $template;

        if (file_exists($templatePath)) {
            ob_start();
            include $templatePath;

            return ob_get_clean();
        }

        return false;
    }
}