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
        return '
        <div> Hello world! </div>
        ';
    }
}