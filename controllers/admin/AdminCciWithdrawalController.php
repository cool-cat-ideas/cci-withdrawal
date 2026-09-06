<?php
/**
 * Visible product entry in the Cool Cat Ideas menu.
 *
 * Its child controllers are rendered by PrestaShop as native header tabs.
 *
 * @license https://opensource.org/license/afl-3-0-php Academic Free License 3.0 (AFL-3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminCciWithdrawalController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();

        if (is_object($this->module) && method_exists($this->module, 'ensureAdminTab')) {
            $this->module->ensureAdminTab();
        }
    }

    public function initContent()
    {
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminCciWithdrawalSubmissions'));
    }
}
