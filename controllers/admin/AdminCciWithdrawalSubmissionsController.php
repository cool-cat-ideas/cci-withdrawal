<?php

/**
 * Canonical withdrawal-statement inbox with links to native merchandise returns.
 *
 * @license https://opensource.org/license/afl-3-0-php Academic Free License 3.0 (AFL-3.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminCciWithdrawalSubmissionsController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'cci_withdrawal_submission';
        $this->identifier = 'id_cci_withdrawal_submission';
        $this->lang = false;
        $this->list_no_link = true;

        parent::__construct();

        if (is_object($this->module) && method_exists($this->module, 'ensureDatabase')) {
            $this->module->ensureDatabase();
        }

        $this->toolbar_title = $this->trans('Submitted withdrawal statements', [], 'Modules.Cciwithdrawal.Admin');
        $this->_select = '
            o.`reference` AS `order_reference_live`,
            CONCAT(c.`firstname`, " ", c.`lastname`) AS `customer_name_live`,
            a.`id_cci_withdrawal_submission` AS `submission_action`,
            a.`id_order_return` AS `native_return_action`';
        $this->_join = '
            LEFT JOIN `' . _DB_PREFIX_ . 'orders` o ON o.`id_order` = a.`id_order`
            LEFT JOIN `' . _DB_PREFIX_ . 'customer` c ON c.`id_customer` = a.`id_customer`';
        $this->_where = ' AND a.`id_shop` = ' . (int) $this->context->shop->id;
        $this->_orderBy = 'date_add';
        $this->_orderWay = 'DESC';

        $this->fields_list = [
            'id_cci_withdrawal_submission' => [
                'title' => $this->trans('Request ID', [], 'Modules.Cciwithdrawal.Admin'),
                'align' => 'center',
                'width' => 70,
            ],
            'date_add' => [
                'title' => $this->trans('Submission date', [], 'Modules.Cciwithdrawal.Admin'),
                'type' => 'datetime',
                'callback' => 'renderSubmissionDate',
                'filter_key' => 'a!date_add',
            ],
            'order_reference_live' => [
                'title' => $this->trans('Order', [], 'Admin.Global'),
                'callback' => 'renderOrderLink',
                'havingFilter' => true,
            ],
            'customer_name_live' => [
                'title' => $this->trans('Customer', [], 'Admin.Global'),
                'havingFilter' => true,
            ],
            'buyer_status' => [
                'title' => $this->trans('Purchase capacity', [], 'Modules.Cciwithdrawal.Admin'),
                'callback' => 'renderBuyerStatus',
                'filter_key' => 'a!buyer_status',
            ],
            'processing_status' => [
                'title' => $this->trans('Handling', [], 'Modules.Cciwithdrawal.Admin'),
                'callback' => 'renderProcessingStatus',
                'filter_key' => 'a!processing_status',
            ],
            'native_return_action' => [
                'title' => $this->trans('Native return', [], 'Modules.Cciwithdrawal.Admin'),
                'callback' => 'renderNativeReturnLink',
                'search' => false,
                'orderby' => false,
            ],
            'confirmation_status' => [
                'title' => $this->trans('Confirmation', [], 'Modules.Cciwithdrawal.Admin'),
                'callback' => 'renderConfirmationStatus',
                'filter_key' => 'a!confirmation_status',
            ],
            'submission_action' => [
                'title' => $this->trans('Action', [], 'Admin.Global'),
                'callback' => 'renderConfirmationAction',
                'search' => false,
                'orderby' => false,
            ],
        ];
    }

    public function postProcess()
    {
        if (Tools::isSubmit('resendCciWithdrawalConfirmation')) {
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !$this->access('edit') || !$this->checkToken()) {
                http_response_code(403);
                $this->errors[] = $this->trans('You do not have permission to resend this confirmation.', [], 'Modules.Cciwithdrawal.Admin');
                return;
            }

            $submissionId = (int) Tools::getValue('resendCciWithdrawalConfirmation');
            $submission = $this->module->getWithdrawalSubmission($submissionId);
            $result = $submission && (int) $submission['id_shop'] === (int) $this->context->shop->id
                ? $this->module->sendSubmissionEmails($submissionId, false)
                : ['customer' => false];
            $url = $this->context->link->getAdminLink('AdminCciWithdrawalSubmissions')
                . '&cci_confirmation=' . ($result['customer'] ? 'sent' : 'failed');
            Tools::redirectAdmin($url);
        }

        return parent::postProcess();
    }

    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);

        if (is_object($this->module) && method_exists($this->module, 'registerAdminAssets')) {
            $this->module->registerAdminAssets();
        }
    }

    public function renderList()
    {
        if (Tools::isSubmit('viewCciWithdrawalSubmission')) {
            return $this->renderSubmissionDetail((int) Tools::getValue($this->identifier));
        }

        $notice = '';
        if (Tools::getValue('cci_confirmation') === 'sent') {
            $notice = $this->module->displayConfirmation($this->trans(
                'The confirmation email was sent again.',
                [],
                'Modules.Cciwithdrawal.Admin'
            ));
        } elseif (Tools::getValue('cci_confirmation') === 'failed') {
            $notice = $this->module->displayError($this->trans(
                'The confirmation email could not be sent. Check the shop mail configuration and the PrestaShop logs.',
                [],
                'Modules.Cciwithdrawal.Admin'
            ));
        }

        $list = $notice . parent::renderList();

        return is_object($this->module) && method_exists($this->module, 'renderWithdrawalsPage')
            ? $this->module->renderWithdrawalsPage($list)
            : $list;
    }

    /** Format inbox and detail dates without changing the immutable statement snapshot. */
    public function renderSubmissionDate($date): string
    {
        $value = trim((string) $date);
        if ($value === '' || str_starts_with($value, '0000-00-00')) {
            return '-';
        }

        try {
            $timezone = new DateTimeZone((string) Configuration::get('PS_TIMEZONE') ?: 'Europe/Warsaw');
            $dateTime = new DateTimeImmutable($value, $timezone);
            $errors = DateTimeImmutable::getLastErrors();
            if ($errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
                return '-';
            }

            // SQL dates already use shop time; explicit offsets are converted to that same zone.
            return $dateTime->setTimezone($timezone)->format('Y-m-d H:i');
        } catch (Throwable $exception) {
            return '-';
        }
    }

    public function renderOrderLink($reference, array $row): string
    {
        if (empty($row['id_order'])) {
            return $this->escapeHtml($reference ?: '-');
        }

        $url = $this->context->link->getAdminLink('AdminOrders')
            . '&id_order=' . (int) $row['id_order'] . '&vieworder=1';

        return '<a href="' . $this->escapeHtml($url) . '">' . $this->escapeHtml($reference ?: $row['order_reference']) . '</a>';
    }

    public function renderBuyerStatus($buyerStatus): string
    {
        return match ((string) $buyerStatus) {
            'consumer' => $this->trans('Consumer', [], 'Modules.Cciwithdrawal.Admin'),
            'sole_trader' => $this->trans('Sole trader without professional purchase purpose', [], 'Modules.Cciwithdrawal.Admin'),
            'business' => $this->trans('Company / professional purchase', [], 'Modules.Cciwithdrawal.Admin'),
            default => $buyerStatus !== '' ? $this->escapeHtml($buyerStatus) : '-',
        };
    }

    public function renderProcessingStatus($status, array $row): string
    {
        if ($status === 'rma_created') {
            return '<span class="label label-success">'
                . $this->escapeHtml($this->trans('Native return created', [], 'Modules.Cciwithdrawal.Admin'))
                . '</span>';
        }

        $message = trim((string) ($row['processing_message'] ?? ''));

        return '<span class="label label-warning">'
            . $this->escapeHtml($this->trans('Manual review', [], 'Modules.Cciwithdrawal.Admin'))
            . '</span>'
            . ($message !== '' ? '<br><small>' . $this->escapeHtml($message) . '</small>' : '');
    }

    public function renderNativeReturnLink($returnId): string
    {
        if ((int) $returnId <= 0) {
            return '-';
        }

        $url = $this->context->link->getAdminLink('AdminReturn')
            . '&id_order_return=' . (int) $returnId . '&vieworder_return=1';

        return '<a href="' . $this->escapeHtml($url) . '">#' . (int) $returnId . '</a>';
    }

    public function renderConfirmationStatus($status, array $row): string
    {
        $attempts = (int) ($row['confirmation_attempts'] ?? 0);
        $label = $status === 'sent'
            ? $this->trans('Sent', [], 'Admin.Global')
            : $this->trans('Failed', [], 'Admin.Global');
        $class = $status === 'sent' ? 'label-success' : 'label-danger';

        $error = trim((string) ($row['confirmation_last_error'] ?? ''));

        return '<span class="label ' . $class . '">' . $this->escapeHtml($label) . '</span>'
            . '<br><small>' . sprintf(
                $this->escapeHtml($this->trans('Attempts: %d', [], 'Modules.Cciwithdrawal.Admin')),
                $attempts
            ) . '</small>'
            . ($error !== '' ? '<br><small>' . $this->escapeHtml($error) . '</small>' : '');
    }

    public function renderConfirmationAction($submissionId, array $row): string
    {
        $viewUrl = $this->context->link->getAdminLink('AdminCciWithdrawalSubmissions')
            . '&viewCciWithdrawalSubmission=1&' . $this->identifier . '=' . (int) $submissionId;
        $actions = '<a class="btn btn-default" href="' . $this->escapeHtml($viewUrl) . '">'
            . '<i class="icon-eye"></i> '
            . $this->escapeHtml($this->trans('View statement', [], 'Modules.Cciwithdrawal.Admin'))
            . '</a>';

        if (($row['confirmation_status'] ?? '') === 'sent' || !$this->access('edit')) {
            return $actions;
        }

        $url = $this->context->link->getAdminLink('AdminCciWithdrawalSubmissions');
        // HelperList already wraps the table in a POST form with its CSRF token.
        return $actions . ' <button type="submit" name="resendCciWithdrawalConfirmation" value="' . (int) $submissionId
            . '" formaction="' . $this->escapeHtml($url) . '" formmethod="post" class="btn btn-default">'
            . '<i class="icon-envelope"></i> '
            . $this->escapeHtml($this->trans('Send again', [], 'Modules.Cciwithdrawal.Admin'))
            . '</button>';

    }

    private function renderSubmissionDetail(int $submissionId): string
    {
        $submission = $this->module->getWithdrawalSubmission($submissionId);
        if (!$submission || (int) $submission['id_shop'] !== (int) $this->context->shop->id) {
            return $this->module->renderWithdrawalsPage($this->module->displayError($this->trans(
                'The withdrawal statement could not be found in this shop.',
                [],
                'Modules.Cciwithdrawal.Admin'
            )));
        }

        $payload = json_decode((string) $submission['statement_payload'], true);
        if (!is_array($payload)) {
            $payload = [];
        }

        $products = '';
        foreach ((array) ($payload['products'] ?? []) as $product) {
            $products .= '<li>' . (int) ($product['quantity'] ?? 0) . ' × '
                . $this->escapeHtml($product['name'] ?? '')
                . (!empty($product['reference']) ? ' <small>(' . $this->escapeHtml($product['reference']) . ')</small>' : '')
                . '</li>';
        }
        if ($products === '') {
            $products = '<li>-</li>';
        }

        $backUrl = $this->context->link->getAdminLink('AdminCciWithdrawalSubmissions');
        $orderUrl = $this->context->link->getAdminLink('AdminOrders')
            . '&id_order=' . (int) $submission['id_order'] . '&vieworder=1';
        $content = '<section class="panel cci-withdrawal-submission-detail">'
            . '<div class="panel-heading"><i class="icon-file-text"></i> '
            . $this->escapeHtml($this->module->getWithdrawalReference($submissionId)) . '</div>'
            . '<dl class="cci-withdrawal-submission-facts">'
            . '<dt>' . $this->escapeHtml($this->trans('Submission date', [], 'Modules.Cciwithdrawal.Admin')) . '</dt>'
            . '<dd>' . $this->renderSubmissionDate($payload['submitted_at'] ?? $submission['date_add']) . '</dd>'
            . '<dt>' . $this->escapeHtml($this->trans('Order', [], 'Admin.Global')) . '</dt>'
            . '<dd><a href="' . $this->escapeHtml($orderUrl) . '">'
            . $this->escapeHtml($submission['order_reference']) . '</a></dd>'
            . '<dt>' . $this->escapeHtml($this->trans('Customer', [], 'Admin.Global')) . '</dt>'
            . '<dd>' . $this->escapeHtml($submission['customer_name']) . ' &lt;'
            . $this->escapeHtml($submission['customer_email']) . '&gt;</dd>'
            . '<dt>' . $this->escapeHtml($this->trans('Purchase capacity', [], 'Modules.Cciwithdrawal.Admin')) . '</dt>'
            . '<dd>' . $this->escapeHtml($payload['buyer_status_label'] ?? $submission['buyer_status']) . '</dd>'
            . '<dt>' . $this->escapeHtml($this->trans('Scope', [], 'Modules.Cciwithdrawal.Admin')) . '</dt>'
            . '<dd>' . $this->escapeHtml($payload['scope_label'] ?? '-') . '</dd>'
            . '<dt>' . $this->escapeHtml($this->trans('Handling', [], 'Modules.Cciwithdrawal.Admin')) . '</dt>'
            . '<dd>' . $this->renderProcessingStatus((string) $submission['processing_status'], $submission) . '</dd>'
            . '<dt>' . $this->escapeHtml($this->trans('Native return', [], 'Modules.Cciwithdrawal.Admin')) . '</dt>'
            . '<dd>' . $this->renderNativeReturnLink((int) $submission['id_order_return']) . '</dd>'
            . '<dt>' . $this->escapeHtml($this->trans('Confirmation', [], 'Modules.Cciwithdrawal.Admin')) . '</dt>'
            . '<dd>' . $this->renderConfirmationStatus((string) $submission['confirmation_status'], $submission) . '</dd>'
            . '</dl>'
            . '<h3>' . $this->escapeHtml($this->trans('Products covered by the statement', [], 'Modules.Cciwithdrawal.Admin')) . '</h3>'
            . '<ul>' . $products . '</ul>'
            . '<h3>' . $this->escapeHtml($this->trans('Immutable statement snapshot', [], 'Modules.Cciwithdrawal.Admin')) . '</h3>'
            . '<pre class="cci-withdrawal-statement-snapshot">'
            . $this->escapeHtml($payload['statement_text'] ?? '-') . '</pre>'
            . '<p><a class="btn btn-default" href="' . $this->escapeHtml($backUrl) . '">'
            . '<i class="icon-arrow-left"></i> '
            . $this->escapeHtml($this->trans('Back to statements', [], 'Modules.Cciwithdrawal.Admin'))
            . '</a></p></section>';

        return $this->module->renderWithdrawalsPage($content);
    }

    private function escapeHtml($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
