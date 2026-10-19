<?php

declare(strict_types=1);

$moduleRoot = dirname(__DIR__);
$menuController = (string) file_get_contents($moduleRoot . '/controllers/admin/AdminCciWithdrawalController.php');
$submissionsController = (string) file_get_contents($moduleRoot . '/controllers/admin/AdminCciWithdrawalSubmissionsController.php');
$settingsController = (string) file_get_contents($moduleRoot . '/controllers/admin/AdminCciWithdrawalSettingsController.php');
$module = (string) file_get_contents($moduleRoot . '/cci_withdrawal.php');

if (strpos($menuController, "getAdminLink('AdminCciWithdrawalSubmissions')") === false) {
    fwrite(STDERR, "FAIL: the visible product entry must open the submissions tab.\n");
    exit(1);
}

if (strpos($submissionsController, 'extends ModuleAdminController') === false
    || strpos($submissionsController, "\$this->table = 'cci_withdrawal_submission'") === false
    || strpos($submissionsController, 'sendSubmissionEmails') === false
    || strpos($submissionsController, 'renderNativeReturnLink') === false) {
    fwrite(STDERR, "FAIL: the submissions tab must expose the canonical statement inbox, RMA link and email retry.\n");
    exit(1);
}

if (strpos($settingsController, 'extends ModuleAdminController') === false
    || strpos($settingsController, '$this->module->getContent()') === false) {
    fwrite(STDERR, "FAIL: the settings tab must render the module configuration.\n");
    exit(1);
}

if (strpos($module, "'AdminCciWithdrawalSubmissions'") === false
    || strpos($module, "'AdminCciWithdrawalSettings'") === false
    || strpos($module, 'renderAdminTabsContainer') !== false) {
    fwrite(STDERR, "FAIL: native PrestaShop child tabs must replace content-level tab markup.\n");
    exit(1);
}

echo "CCI Withdrawal admin menu context smoke test passed.\n";
