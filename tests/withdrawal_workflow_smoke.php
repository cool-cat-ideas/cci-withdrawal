<?php

declare(strict_types=1);

$moduleRoot = dirname(__DIR__);
$module = (string) file_get_contents($moduleRoot . '/cci_withdrawal.php');
$controller = (string) file_get_contents($moduleRoot . '/controllers/front/form.php');
$template = (string) file_get_contents($moduleRoot . '/views/templates/front/form.tpl');
$adminController = (string) file_get_contents($moduleRoot . '/controllers/admin/AdminCciWithdrawalSubmissionsController.php');

$fail = static function (string $message): void {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$persistPosition = strpos($controller, '$submission = $this->persistWithdrawalSubmission($order)');
$syncPosition = strpos($controller, '$this->synchronizeNativeOrderReturn(');
if ($persistPosition === false || $syncPosition === false || $persistPosition >= $syncPosition) {
    $fail('the immutable statement must be persisted before the native RMA adapter runs.');
}

foreach (['statement_payload', 'public_token_hash', 'processing_status', 'confirmation_attempts', 'id_lang', 'id_shop'] as $field) {
    if (strpos($module, $field) === false || strpos($controller, $field) === false) {
        $fail("the canonical snapshot is missing {$field}.");
    }
}

if (strpos($module, '`id_order_return` INT UNSIGNED NULL DEFAULT NULL') === false) {
    $fail('a native RMA must be optional for the canonical statement.');
}

$accessStart = strpos($controller, 'private function validateOrderAccess');
$accessEnd = strpos($controller, 'private function findOrderForCurrentValues', $accessStart ?: 0);
$accessMethod = $accessStart !== false && $accessEnd !== false
    ? substr($controller, $accessStart, $accessEnd - $accessStart)
    : '';
if ($accessMethod === '' || strpos($accessMethod, 'isPaidAndShipped') !== false || strpos($accessMethod, 'getNumberOfDays') !== false) {
    $fail('native status and return-period rules must not block receipt of the statement.');
}

if (strpos($controller, "'native_return_supported' => !\$isVirtual") === false
    || strpos($controller, "'is_returnable' => \$nonReturnableReason === ''") === false) {
    $fail('digital products must remain selectable and be marked for adapter review.');
}

if (strpos($template, 'name="gdpr_consent"') !== false
    || strpos($template, 'additional_information_required') !== false
    || strpos($controller, 'Please enter additional information.') !== false) {
    $fail('the statutory path must not require a marketing consent or a reason.');
}

if (strpos($controller, "'receipt_token'") === false
    || strpos($controller, "'submitted' => 1") !== false
    || strpos($module, 'hash_equals') === false) {
    $fail('the success screen must use a signed receipt instead of a query flag.');
}

foreach (['isLookupRateLimited', 'recordFailedLookup', 'cci_withdrawal_lookup_attempt'] as $rateLimitContract) {
    if (strpos($controller . $module, $rateLimitContract) === false) {
        $fail("the order lookup rate limit is missing {$rateLimitContract}.");
    }
}

foreach (['moduleRoutes', 'registerGDPRConsent', 'actionFrontControllerSetMedia', 'displayFooter', 'displayCustomerAccount', 'displayOrderDetail', 'displayOrderConfirmation2', 'actionValidateOrder'] as $hook) {
    if (strpos($module, "'{$hook}'") === false) {
        $fail("the access-point hook {$hook} is missing.");
    }
}

if (strpos($module, 'ensureFrontendGroupRestrictions') === false
    || strpos($module, 'INSERT IGNORE INTO') === false
    || strpos($module, 'module_group') === false) {
    $fail('existing installations must repair customer-group access for the new storefront hooks.');
}

if (stripos($module . $controller, 'email_queue') !== false
    || strpos($adminController, 'sendSubmissionEmails') === false
    || strpos($adminController, 'View statement') === false) {
    $fail('email delivery must be synchronous, logged and manually repeatable from the statement inbox.');
}

if (strpos($module, '`date_upd` = NOW()') !== false
    || strpos($module, "\$date = date('Y-m-d H:i:s')") === false) {
    $fail('confirmation timestamps must use the same configured PHP/store timezone as the statement snapshot.');
}

echo "CCI Withdrawal workflow smoke test passed.\n";
