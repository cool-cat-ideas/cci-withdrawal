<?php

/**
 * CCI Withdrawal Form settings rendered as a native PrestaShop header tab.
 *
 * @license https://opensource.org/license/afl-3-0-php Academic Free License 3.0 (AFL-3.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminCciWithdrawalSettingsController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
        $this->meta_title = $this->module->displayName;
    }

    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);
        $this->module->registerAdminAssets();
    }

    public function initContent()
    {
        $this->content = $this->module->getContent();
        parent::initContent();
    }
}
