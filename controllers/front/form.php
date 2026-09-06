<?php

class Cci_WithdrawalFormModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public $auth = false;

    private const FIELD_LIMITS = [
        'full_name' => 160,
        'email' => 160,
        'order_reference' => 64,
        'message' => 2000,
    ];

    private const BUYER_STATUSES = ['consumer', 'sole_trader', 'business'];

    private $formValues = [
        'full_name' => '',
        'email' => '',
        'order_reference' => '',
        'withdrawal_scope' => 'full',
        'buyer_status' => 'consumer',
        'non_professional_purchase' => false,
        'message' => '',
    ];

    private $showPreview = false;
    private $showProductSelection = false;
    private $orderProducts = [];
    private $selectedProducts = [];
    private $excludedProducts = [];
    private $submissionToken = '';
    private $companyDetected = false;
    private $createdNewReturn = false;
    private $receipt = null;

    public function init()
    {
        parent::init();

        if (!Tools::isSubmit('downloadCciWithdrawalConfirmation')) {
            return;
        }

        $submissionId = (int) Tools::getValue('receipt');
        $receiptToken = (string) Tools::getValue('receipt_token');
        $receipt = $this->module->getWithdrawalReceipt($submissionId, $receiptToken);
        $attachment = $receipt ? $this->module->getConfirmationPdfAttachment($submissionId) : null;
        if (!$attachment || empty($attachment['content'])) {
            header('HTTP/1.1 404 Not Found');
            exit;
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . rawurlencode((string) $attachment['name']) . '"');
        header('Content-Length: ' . strlen((string) $attachment['content']));
        echo $attachment['content'];
        exit;
    }

    public function setMedia()
    {
        parent::setMedia();

        $cssFile = _PS_MODULE_DIR_ . $this->module->name . '/views/css/front.css';
        $version = (string) (@filemtime($cssFile) ?: $this->module->version);

        $this->registerStylesheet(
            'module-cci-withdrawal-front',
            __PS_BASE_URI__ . 'modules/' . $this->module->name . '/views/css/front.css',
            [
                'media' => 'all',
                'priority' => 200,
                'version' => $version,
                'server' => 'remote',
                'needRtl' => false,
            ]
        );
    }

    public function postProcess()
    {
        if (!$this->isFormSubmission()) {
            return;
        }

        $this->formValues = $this->readFormValues();
        $this->submissionToken = $this->readSubmissionToken();

        if (!$this->isValidToken()) {
            $this->errors[] = $this->trans('Your session has expired. Please submit the form again.', [], 'Shop.Notifications.Error');

            return;
        }

        if (Tools::isSubmit('editCciWithdrawal')) {
            return;
        }

        $this->validateFormValues($this->formValues);
        if ($this->errors) {
            return;
        }

        if ($this->submissionToken === '') {
            $this->errors[] = $this->trans('Your session has expired. Please submit the form again.', [], 'Shop.Notifications.Error');

            return;
        }

        if ($this->isLookupRateLimited()) {
            $this->errors[] = $this->trans(
                'Too many unsuccessful attempts. Wait 15 minutes and try again, or contact us.',
                [],
                'Modules.Cciwithdrawal.Shop'
            );

            return;
        }

        $order = $this->findOrderForCurrentValues();
        if (!$order) {
            $this->recordFailedLookup();
            $this->errors[] = $this->trans(
                'We could not match the order reference and email address. Check the details and try again.',
                [],
                'Modules.Cciwithdrawal.Shop'
            );

            return;
        }

        $this->clearLookupFailures();

        $this->companyDetected = $this->orderHasBusinessDetails($order);
        $this->validateBuyerEligibility($order);
        $this->validateOrderAccess($order);
        if ($this->errors) {
            return;
        }

        $this->orderProducts = $this->getReturnableOrderProducts($order);
        $this->excludedProducts = array_values(array_filter($this->orderProducts, static function (array $product): bool {
            return !$product['is_returnable'];
        }));

        if (!$this->hasReturnableProducts($this->orderProducts)) {
            $this->errors[] = $this->trans(
                'This order has no products available for withdrawal. Contact us if you believe this is incorrect.',
                [],
                'Modules.Cciwithdrawal.Shop'
            );

            return;
        }

        if ($this->formValues['withdrawal_scope'] === 'full') {
            $this->selectedProducts = $this->selectAllReturnableProducts($this->orderProducts);
        } else {
            $this->selectedProducts = $this->readSelectedProducts($this->orderProducts);
        }

        if (Tools::isSubmit('previewCciWithdrawal')) {
            if ($this->formValues['withdrawal_scope'] === 'partial' && !$this->selectedProducts) {
                $this->showProductSelection = true;

                return;
            }

            $this->showPreview = true;

            return;
        }

        if (!$this->selectedProducts) {
            $this->errors[] = $this->trans('Select at least one product from the order.', [], 'Modules.Cciwithdrawal.Shop');
            $this->showProductSelection = true;

            return;
        }

        try {
            $submission = $this->persistWithdrawalSubmission($order);
        } catch (Throwable $exception) {
            PrestaShopLogger::addLog(
                'CCI Withdrawal: ' . $exception->getMessage(),
                3,
                null,
                'Order',
                (int) $order->id,
                true
            );
            $this->errors[] = $this->trans(
                'We could not record your withdrawal statement. Please try again or contact us directly.',
                [],
                'Modules.Cciwithdrawal.Shop'
            );

            return;
        }

        $orderReturn = $this->synchronizeNativeOrderReturn((int) $submission['id'], $order);
        $mailStatus = ['customer' => true, 'shop' => true];
        if ($orderReturn && $this->createdNewReturn) {
            Hook::exec('actionOrderReturn', ['orderReturn' => $orderReturn]);
        }
        if ($submission['created'] || $submission['confirmation_status'] !== 'sent') {
            $mailStatus = $this->module->sendSubmissionEmails((int) $submission['id']);
        }

        if (!$mailStatus['customer']) {
            PrestaShopLogger::addLog(
                'CCI Withdrawal: customer confirmation email could not be sent for submission #' . (int) $submission['id'],
                2,
                null,
                'Order',
                (int) $order->id,
                true
            );
        }
        if (!$mailStatus['shop']) {
            PrestaShopLogger::addLog(
                'CCI Withdrawal: merchant notification email could not be sent for submission #' . (int) $submission['id'],
                2,
                null,
                'Order',
                (int) $order->id,
                true
            );
        }

        $redirectParams = [
            'receipt' => (int) $submission['id'],
            'receipt_token' => (string) $submission['receipt_token'],
        ];

        Tools::redirect($this->context->link->getModuleLink($this->module->name, 'form', $redirectParams, true));
    }

    public function initContent()
    {
        parent::initContent();
        $this->loadReceipt();
        $this->prefillOrderAccessValues();
        $this->prefillCustomerValues();

        $enabledBuyerStatuses = $this->module->getEnabledBuyerStatuses();
        if (!$this->isFormSubmission() && !in_array($this->formValues['buyer_status'], $enabledBuyerStatuses, true)) {
            $this->formValues['buyer_status'] = $enabledBuyerStatuses[0] ?? '';
        }

        if ($this->submissionToken === '') {
            $this->submissionToken = $this->generateSubmissionToken();
        }

        $this->context->smarty->assign([
            'form_action' => $this->context->link->getModuleLink($this->module->name, 'form', [], true),
            'form_token' => $this->module->getFormToken(),
            'submission_token' => $this->submissionToken,
            'form_values' => $this->formValues,
            'withdrawal_preview' => $this->showPreview,
            'withdrawal_product_selection' => $this->showProductSelection,
            'order_products' => $this->orderProducts,
            'selected_products' => $this->selectedProducts,
            'excluded_products' => $this->excludedProducts,
            'company_detected' => $this->companyDetected,
            'withdrawal_success' => is_array($this->receipt),
            'withdrawal_receipt' => $this->receipt,
            'confirmation_email_failed' => is_array($this->receipt)
                && ($this->receipt['confirmation_status'] ?? '') !== 'sent',
            'confirmation_pdf_url' => is_array($this->receipt) && $this->module->attachesConfirmationPdf()
                ? $this->context->link->getModuleLink($this->module->name, 'form', [
                    'receipt' => (int) $this->receipt['id_cci_withdrawal_submission'],
                    'receipt_token' => (string) Tools::getValue('receipt_token'),
                    'downloadCciWithdrawalConfirmation' => 1,
                ], true)
                : '',
            'guest_tracking_url' => $this->context->link->getPageLink('guest-tracking', true),
            'order_returns_url' => $this->context->link->getPageLink('order-follow', true),
            'contact_url' => $this->context->link->getPageLink('contact', true),
            'return_period_days' => (int) Configuration::get('PS_ORDER_RETURN_NB_DAYS'),
            'customer_logged_in' => $this->context->customer && $this->context->customer->isLogged(),
            'buyer_status_consumer_enabled' => in_array('consumer', $enabledBuyerStatuses, true),
            'buyer_status_sole_trader_enabled' => in_array('sole_trader', $enabledBuyerStatuses, true),
            'buyer_status_business_enabled' => in_array('business', $enabledBuyerStatuses, true),
            'guest_orders_enabled' => $this->module->allowsGuestOrders(),
            'additional_information_enabled' => $this->module->showsAdditionalInformation(),
        ]);

        $this->setTemplate('module:cci_withdrawal/views/templates/front/form.tpl');
    }

    public function getBreadcrumbLinks()
    {
        $breadcrumb = parent::getBreadcrumbLinks();
        $breadcrumb['links'][] = [
            'title' => $this->trans('Withdraw from contract', [], 'Modules.Cciwithdrawal.Shop'),
            'url' => $this->context->link->getModuleLink($this->module->name, 'form', [], true),
        ];

        return $breadcrumb;
    }

    private function isFormSubmission(): bool
    {
        return Tools::isSubmit('previewCciWithdrawal')
            || Tools::isSubmit('confirmCciWithdrawal')
            || Tools::isSubmit('editCciWithdrawal');
    }

    private function readFormValues(): array
    {
        return [
            'full_name' => $this->normalizeText(Tools::getValue('full_name'), self::FIELD_LIMITS['full_name']),
            'email' => Tools::strtolower($this->normalizeText(Tools::getValue('email'), self::FIELD_LIMITS['email'])),
            'order_reference' => Tools::strtoupper($this->normalizeText(Tools::getValue('order_reference'), self::FIELD_LIMITS['order_reference'])),
            'withdrawal_scope' => $this->normalizeText(Tools::getValue('withdrawal_scope'), 16),
            'buyer_status' => $this->normalizeText(Tools::getValue('buyer_status'), 32),
            'non_professional_purchase' => (bool) Tools::getValue('non_professional_purchase'),
            'message' => $this->module->showsAdditionalInformation()
                ? $this->normalizeText(Tools::getValue('message'), self::FIELD_LIMITS['message'])
                : '',
        ];
    }

    private function validateFormValues(array $values): void
    {
        if ($values['full_name'] === '') {
            $this->errors[] = $this->trans('Please enter your full name.', [], 'Modules.Cciwithdrawal.Shop');
        }

        if ($values['email'] === '' || !Validate::isEmail($values['email'])) {
            $this->errors[] = $this->trans('Please enter a valid email address.', [], 'Shop.Notifications.Error');
        }

        if ($values['order_reference'] === '') {
            $this->errors[] = $this->trans('Please enter your order reference.', [], 'Modules.Cciwithdrawal.Shop');
        }

        if (!in_array($values['withdrawal_scope'], ['full', 'partial'], true)) {
            $this->errors[] = $this->trans(
                'Please choose whether you withdraw from the whole order or only part of it.',
                [],
                'Modules.Cciwithdrawal.Shop'
            );
        }

        if (
            !in_array($values['buyer_status'], self::BUYER_STATUSES, true)
            || !in_array($values['buyer_status'], $this->module->getEnabledBuyerStatuses(), true)
        ) {
            $this->errors[] = $this->trans('Please select the capacity in which you made the purchase.', [], 'Modules.Cciwithdrawal.Shop');
        }
    }

    private function validateBuyerEligibility(Order $order): void
    {
        unset($order);

        if ($this->formValues['buyer_status'] === 'business') {
            $this->errors[] = $this->trans(
                'The statutory consumer withdrawal form is not available for a company, organisation or a purchase of a professional business nature. Contact us if you want to ask about a voluntary return.',
                [],
                'Modules.Cciwithdrawal.Shop'
            );

            return;
        }

        if ($this->formValues['buyer_status'] === 'sole_trader' && !$this->formValues['non_professional_purchase']) {
            $this->errors[] = $this->trans(
                'Confirm that the purchase was not of a professional nature for your sole proprietorship.',
                [],
                'Modules.Cciwithdrawal.Shop'
            );
        }

        if ($this->companyDetected && $this->formValues['buyer_status'] === 'consumer') {
            if (in_array('sole_trader', $this->module->getEnabledBuyerStatuses(), true)) {
                $this->errors[] = $this->trans(
                    'The invoice for this order contains company details. Select the sole trader option and make the required declaration, or contact us.',
                    [],
                    'Modules.Cciwithdrawal.Shop'
                );
            } else {
                $this->errors[] = $this->trans(
                    'The invoice for this order contains company details. The consumer withdrawal option cannot be used for this order. Contact us if you believe a statutory exception applies.',
                    [],
                    'Modules.Cciwithdrawal.Shop'
                );
            }
        }
    }

    private function validateOrderAccess(Order $order): void
    {
        if ((int) $order->id_shop !== (int) $this->context->shop->id) {
            $this->errors[] = $this->trans(
                'We could not match the order reference and email address. Check the details and try again.',
                [],
                'Modules.Cciwithdrawal.Shop'
            );

            return;
        }

        if (!$this->module->allowsGuestOrders() && $this->isGuestOrder($order)) {
            $this->errors[] = $this->trans(
                'This form is not available for guest orders. Please contact us directly.',
                [],
                'Modules.Cciwithdrawal.Shop'
            );

            return;
        }

    }

    private function findOrderForCurrentValues(): ?Order
    {
        if ($this->formValues['order_reference'] === '' || !Validate::isEmail($this->formValues['email'])) {
            return null;
        }

        $order = Order::getByReferenceAndEmail($this->formValues['order_reference'], $this->formValues['email']);

        return Validate::isLoadedObject($order) ? $order : null;
    }

    private function orderHasBusinessDetails(Order $order): bool
    {
        $invoiceAddress = new Address((int) $order->id_address_invoice);
        if (!Validate::isLoadedObject($invoiceAddress)) {
            return false;
        }

        return trim((string) $invoiceAddress->company) !== '' || trim((string) $invoiceAddress->vat_number) !== '';
    }

    private function isGuestOrder(Order $order): bool
    {
        $customer = new Customer((int) $order->id_customer);

        return Validate::isLoadedObject($customer) && (bool) $customer->is_guest;
    }

    private function getReturnableOrderProducts(Order $order): array
    {
        $products = $order->getProducts();
        if (!$products) {
            return [];
        }

        OrderReturn::addReturnedQuantity($products, (int) $order->id);

        $currency = new Currency((int) $order->id_currency);
        $currencyIsoCode = Validate::isLoadedObject($currency) ? $currency->iso_code : $this->context->currency->iso_code;
        $locale = Tools::getContextLocale($this->context);
        $excludedProductIds = array_flip($this->module->getExcludedProductIds());
        $excludeCustomized = $this->module->shouldExcludeCustomizedProducts();
        $formattedProducts = [];

        foreach ($products as $product) {
            $quantity = (int) ($product['product_quantity'] ?? 0);
            if ($quantity <= 0) {
                continue;
            }

            $returnedQuantity = max(
                (int) ($product['qty_returned'] ?? 0),
                (int) ($product['product_quantity_return'] ?? 0),
                (int) ($product['product_quantity_refunded'] ?? 0)
            );
            $availableQuantity = max(0, $quantity - $returnedQuantity);
            $unitPrice = (float) ($product['unit_price_tax_incl'] ?? $product['product_price_wt'] ?? 0);
            $idOrderDetail = (int) $product['id_order_detail'];
            $idProduct = (int) ($product['product_id'] ?? 0);
            $idCustomization = (int) ($product['id_customization'] ?? 0);
            $productObject = new Product($idProduct, false, (int) $this->context->language->id, (int) $order->id_shop);
            $isVirtual = Validate::isLoadedObject($productObject) && (bool) $productObject->is_virtual;
            $nonReturnableReason = '';

            if ($availableQuantity <= 0) {
                $nonReturnableReason = 'already_returned';
            } elseif ($excludeCustomized && $idCustomization > 0) {
                $nonReturnableReason = 'customized';
            } elseif (isset($excludedProductIds[$idProduct])) {
                $nonReturnableReason = 'configured';
            }

            $formattedProducts[$idOrderDetail] = [
                'id_order_detail' => $idOrderDetail,
                'id_product' => $idProduct,
                'name' => (string) ($product['product_name'] ?? $product['name'] ?? ''),
                'reference' => (string) ($product['product_reference'] ?? ''),
                'quantity' => $quantity,
                'returned_quantity' => $returnedQuantity,
                'available_quantity' => $availableQuantity,
                'unit_price' => $locale->formatPrice($unitPrice, $currencyIsoCode),
                'is_returnable' => $nonReturnableReason === '',
                'native_return_supported' => !$isVirtual,
                'non_returnable_reason' => $nonReturnableReason,
            ];
        }

        return $formattedProducts;
    }

    private function hasReturnableProducts(array $orderProducts): bool
    {
        foreach ($orderProducts as $product) {
            if ($product['is_returnable']) {
                return true;
            }
        }

        return false;
    }

    private function selectAllReturnableProducts(array $orderProducts): array
    {
        $selectedProducts = [];
        foreach ($orderProducts as $idOrderDetail => $product) {
            if (!$product['is_returnable']) {
                continue;
            }

            $selectedProducts[$idOrderDetail] = $product;
            $selectedProducts[$idOrderDetail]['selected_quantity'] = (int) $product['available_quantity'];
        }

        return $selectedProducts;
    }

    private function readSelectedProducts(array $orderProducts): array
    {
        $selectedIds = Tools::getValue('withdrawal_products', []);
        $selectedQuantities = Tools::getValue('withdrawal_product_qty', []);

        if (!is_array($selectedIds)) {
            $selectedIds = $selectedIds !== '' ? [$selectedIds] : [];
        }
        if (!is_array($selectedQuantities)) {
            $selectedQuantities = [];
        }

        $selectedProducts = [];
        foreach ($selectedIds as $selectedId) {
            $idOrderDetail = (int) $selectedId;
            if (!isset($orderProducts[$idOrderDetail]) || !$orderProducts[$idOrderDetail]['is_returnable']) {
                continue;
            }

            $requestedQuantity = isset($selectedQuantities[$idOrderDetail]) ? (int) $selectedQuantities[$idOrderDetail] : 1;
            $availableQuantity = (int) $orderProducts[$idOrderDetail]['available_quantity'];
            $requestedQuantity = max(1, min($requestedQuantity, $availableQuantity));

            $selectedProducts[$idOrderDetail] = $orderProducts[$idOrderDetail];
            $selectedProducts[$idOrderDetail]['selected_quantity'] = $requestedQuantity;
        }

        return $selectedProducts;
    }

    private function persistWithdrawalSubmission(Order $order): array
    {
        if (!$this->module->ensureDatabase()) {
            throw new RuntimeException('Could not prepare the withdrawal submission table.');
        }

        $submissionHash = hash('sha256', $this->submissionToken);
        $receiptToken = $this->module->getReceiptToken($this->submissionToken);
        $existing = Db::getInstance()->getRow(
            'SELECT `id_cci_withdrawal_submission`, `id_order`, `confirmation_status`'
            . ' FROM `' . _DB_PREFIX_ . 'cci_withdrawal_submission`'
            . ' WHERE `submission_hash` = \'' . pSQL($submissionHash) . '\''
        );
        if (is_array($existing)) {
            if ((int) $existing['id_order'] !== (int) $order->id) {
                throw new RuntimeException('The submission token has already been used.');
            }

            return [
                'id' => (int) $existing['id_cci_withdrawal_submission'],
                'created' => false,
                'receipt_token' => $receiptToken,
                'confirmation_status' => (string) $existing['confirmation_status'],
            ];
        }

        $date = date('Y-m-d H:i:s');
        $payload = $this->buildStatementPayload($order, $date);
        $encodedPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $saved = Db::getInstance()->insert('cci_withdrawal_submission', [
            'id_order' => (int) $order->id,
            'id_customer' => (int) $order->id_customer,
            'id_shop' => (int) $order->id_shop,
            'id_lang' => (int) $this->context->language->id,
            'submission_hash' => pSQL($submissionHash),
            'public_token_hash' => pSQL(hash('sha256', $receiptToken)),
            'buyer_status' => pSQL($this->formValues['buyer_status']),
            'non_professional_declaration' => (int) $this->formValues['non_professional_purchase'],
            'company_detected' => (int) $this->companyDetected,
            'customer_name' => pSQL($this->formValues['full_name']),
            'customer_email' => pSQL($this->formValues['email']),
            'order_reference' => pSQL((string) $order->reference),
            'statement_payload' => pSQL($encodedPayload, true),
            'processing_status' => 'received',
            'processing_message' => '',
            'confirmation_status' => 'pending',
            'confirmation_attempts' => 0,
            'confirmation_last_error' => '',
            'date_add' => pSQL($date),
            'date_upd' => pSQL($date),
        ]);

        if (!$saved) {
            $existingId = (int) Db::getInstance()->getValue(
                'SELECT `id_cci_withdrawal_submission` FROM `' . _DB_PREFIX_ . 'cci_withdrawal_submission`'
                . ' WHERE `submission_hash` = \'' . pSQL($submissionHash) . '\''
            );
            if ($existingId <= 0) {
                throw new RuntimeException('Could not save the withdrawal statement.');
            }

            return [
                'id' => $existingId,
                'created' => false,
                'receipt_token' => $receiptToken,
                'confirmation_status' => (string) Db::getInstance()->getValue(
                    'SELECT `confirmation_status` FROM `' . _DB_PREFIX_ . 'cci_withdrawal_submission`'
                    . ' WHERE `id_cci_withdrawal_submission` = ' . $existingId
                ),
            ];
        }

        return [
            'id' => (int) Db::getInstance()->Insert_ID(),
            'created' => true,
            'receipt_token' => $receiptToken,
            'confirmation_status' => 'pending',
        ];
    }

    private function buildStatementPayload(Order $order, string $date): array
    {
        $products = [];
        foreach ($this->selectedProducts as $product) {
            $products[] = [
                'id_order_detail' => (int) $product['id_order_detail'],
                'id_product' => (int) $product['id_product'],
                'name' => (string) $product['name'],
                'reference' => (string) $product['reference'],
                'quantity' => (int) $product['selected_quantity'],
                'native_return_supported' => (bool) $product['native_return_supported'],
            ];
        }

        return [
            'schema_version' => 1,
            'order_id' => (int) $order->id,
            'order_reference' => (string) $order->reference,
            'customer_name' => $this->formValues['full_name'],
            'customer_email' => $this->formValues['email'],
            'buyer_status' => $this->formValues['buyer_status'],
            'buyer_status_label' => $this->getBuyerStatusLabel(false),
            'non_professional_purchase' => (bool) $this->formValues['non_professional_purchase'],
            'withdrawal_scope' => $this->formValues['withdrawal_scope'],
            'scope_label' => $this->formValues['withdrawal_scope'] === 'partial'
                ? $this->trans('Part of the order', [], 'Modules.Cciwithdrawal.Shop')
                : $this->trans('All eligible products from the order', [], 'Modules.Cciwithdrawal.Shop'),
            'products' => $products,
            'message' => $this->formValues['message'],
            'statement_text' => $this->buildOrderReturnStatement(),
            'submitted_at' => $date,
            'submitted_at_display' => $this->formatSubmissionDate($date),
            'id_lang' => (int) $this->context->language->id,
            'locale' => (string) $this->context->language->locale,
            'id_shop' => (int) $order->id_shop,
        ];
    }

    private function synchronizeNativeOrderReturn(int $submissionId, Order $order): ?OrderReturn
    {
        $submission = $this->module->getWithdrawalSubmission($submissionId);
        if (!$submission) {
            return null;
        }

        $existingReturnId = (int) $submission['id_order_return'];
        if ($existingReturnId > 0) {
            $existingReturn = new OrderReturn($existingReturnId);
            if (Validate::isLoadedObject($existingReturn)) {
                $this->createdNewReturn = false;

                return $existingReturn;
            }
        }

        $blocker = $this->getNativeReturnBlocker($order);
        if ($blocker !== '') {
            $this->markSubmissionForManualReview($submissionId, $blocker);

            return null;
        }

        try {
            return $this->createNativeOrderReturn($submissionId, $order);
        } catch (Throwable $exception) {
            $this->markSubmissionForManualReview($submissionId, Tools::substr($exception->getMessage(), 0, 255));
            PrestaShopLogger::addLog(
                'CCI Withdrawal: RMA sync failed for submission #' . $submissionId . ': ' . $exception->getMessage(),
                3,
                null,
                'Order',
                (int) $order->id,
                true
            );

            return null;
        }
    }

    private function getNativeReturnBlocker(Order $order): string
    {
        if (!(bool) Configuration::get('PS_ORDER_RETURN', null, null, (int) $order->id_shop)) {
            return 'Native merchandise returns are disabled.';
        }
        if (!$order->isPaidAndShipped()) {
            return 'The current order status is not eligible for a native merchandise return.';
        }
        if (!$order->getNumberOfDays()) {
            return 'The native merchandise return period has expired.';
        }
        foreach ($this->selectedProducts as $product) {
            if (empty($product['native_return_supported'])) {
                return 'At least one selected item requires manual review and cannot be represented by native merchandise returns.';
            }
        }

        return '';
    }

    private function markSubmissionForManualReview(int $submissionId, string $message): void
    {
        Db::getInstance()->update('cci_withdrawal_submission', [
            'processing_status' => 'manual_review',
            'processing_message' => pSQL(Tools::substr($message, 0, 255)),
            'date_upd' => date('Y-m-d H:i:s'),
        ], 'id_cci_withdrawal_submission = ' . $submissionId);
    }

    private function createNativeOrderReturn(int $submissionId, Order $order): OrderReturn
    {
        $idsOrderDetail = [];
        $quantities = [];
        foreach ($this->selectedProducts as $idOrderDetail => $product) {
            $idsOrderDetail[$idOrderDetail] = (int) $idOrderDetail;
            $quantities[$idOrderDetail] = (int) $product['selected_quantity'];
        }

        $orderReturn = new OrderReturn();
        $orderReturn->id_customer = (int) $order->id_customer;
        $orderReturn->id_order = (int) $order->id;
        $orderReturn->state = 1;
        $orderReturn->question = $this->buildOrderReturnStatement();

        if (!$orderReturn->checkEnoughProduct($idsOrderDetail, $quantities)) {
            throw new RuntimeException('The requested product quantity is no longer available for return.');
        }

        $database = Db::getInstance();
        $database->execute('START TRANSACTION');

        try {
            if (!$orderReturn->add()) {
                throw new RuntimeException('Could not create the native merchandise return.');
            }

            $orderReturn->addReturnDetail($idsOrderDetail, $quantities);
            $createdDetails = (int) $database->getValue(
                'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'order_return_detail`'
                . ' WHERE `id_order_return` = ' . (int) $orderReturn->id
            );
            if ($createdDetails !== count($idsOrderDetail)) {
                throw new RuntimeException('Could not save all merchandise return details.');
            }

            if (!$database->update('cci_withdrawal_submission', [
                'id_order_return' => (int) $orderReturn->id,
                'processing_status' => 'rma_created',
                'processing_message' => '',
                'date_upd' => date('Y-m-d H:i:s'),
            ], 'id_cci_withdrawal_submission = ' . $submissionId)) {
                throw new RuntimeException('Could not link the native merchandise return.');
            }

            $database->execute('COMMIT');
            $this->createdNewReturn = true;
        } catch (Throwable $exception) {
            $database->execute('ROLLBACK');
            throw $exception;
        }

        return $orderReturn;
    }

    private function buildOrderReturnStatement(): string
    {
        $buyerLabel = $this->getBuyerStatusLabel(false);
        $scopeLabel = $this->formValues['withdrawal_scope'] === 'partial'
            ? $this->trans('Part of the order', [], 'Modules.Cciwithdrawal.Shop')
            : $this->trans('All eligible products from the order', [], 'Modules.Cciwithdrawal.Shop');

        $lines = [
            $this->trans('Online withdrawal statement', [], 'Modules.Cciwithdrawal.Shop'),
            $this->trans('Customer: %name%', ['%name%' => $this->formValues['full_name']], 'Modules.Cciwithdrawal.Shop'),
            $this->trans('Email: %email%', ['%email%' => $this->formValues['email']], 'Modules.Cciwithdrawal.Shop'),
            $this->trans('Purchase capacity: %status%', ['%status%' => $buyerLabel], 'Modules.Cciwithdrawal.Shop'),
            $this->trans('Scope: %scope%', ['%scope%' => $scopeLabel], 'Modules.Cciwithdrawal.Shop'),
            $this->trans('Products:', [], 'Modules.Cciwithdrawal.Shop'),
            $this->formatSelectedProducts(false),
        ];

        if ($this->formValues['buyer_status'] === 'sole_trader') {
            $lines[] = $this->trans(
                'Declaration: the purchase was not of a professional nature for the sole proprietorship.',
                [],
                'Modules.Cciwithdrawal.Shop'
            );
        }

        if ($this->formValues['message'] !== '') {
            $lines[] = $this->trans('Additional information:', [], 'Modules.Cciwithdrawal.Shop');
            $lines[] = $this->formValues['message'];
        }

        return implode("\n", $lines);
    }

    private function formatSelectedProducts(bool $html): string
    {
        if (!$this->selectedProducts) {
            return '-';
        }

        $items = [];
        foreach ($this->selectedProducts as $product) {
            $line = sprintf(
                '%d x %s%s',
                (int) $product['selected_quantity'],
                (string) $product['name'],
                $product['reference'] !== '' ? ' (' . $product['reference'] . ')' : ''
            );
            $items[] = $html ? Tools::htmlentitiesUTF8($line) : $line;
        }

        return $html ? implode('<br>', $items) : implode("\n", $items);
    }

    private function getBuyerStatusLabel(bool $short): string
    {
        if ($this->formValues['buyer_status'] === 'sole_trader') {
            return $short
                ? $this->trans('Sole trader with consumer rights', [], 'Modules.Cciwithdrawal.Shop')
                : $this->trans('Sole trader — purchase without a professional nature', [], 'Modules.Cciwithdrawal.Shop');
        }

        if ($this->formValues['buyer_status'] === 'business') {
            return $this->trans('Company or professional business purchase', [], 'Modules.Cciwithdrawal.Shop');
        }

        return $this->trans('Consumer purchase outside business activity', [], 'Modules.Cciwithdrawal.Shop');
    }

    private function formatSubmissionDate(string $date): string
    {
        try {
            $timezone = new DateTimeZone((string) Configuration::get('PS_TIMEZONE') ?: 'Europe/Warsaw');
            $dateTime = new DateTimeImmutable($date, $timezone);

            return $dateTime->format('Y-m-d H:i:s T');
        } catch (Throwable $exception) {
            return $date;
        }
    }

    private function loadReceipt(): void
    {
        $submissionId = (int) Tools::getValue('receipt');
        $receiptToken = (string) Tools::getValue('receipt_token');
        if ($submissionId <= 0 && $receiptToken === '') {
            return;
        }

        $this->receipt = $this->module->getWithdrawalReceipt($submissionId, $receiptToken);
        if (!$this->receipt) {
            $this->errors[] = $this->trans(
                'This confirmation link is invalid or incomplete. Check the link in your email or contact us.',
                [],
                'Modules.Cciwithdrawal.Shop'
            );
        }
    }

    private function prefillOrderAccessValues(): void
    {
        if ($this->isFormSubmission()) {
            return;
        }

        $orderId = (int) Tools::getValue('withdrawal_order');
        $accessToken = (string) Tools::getValue('withdrawal_access');
        $order = $this->module->resolveOrderAccessRequest($orderId, $accessToken);
        if (!$order) {
            return;
        }

        $customer = new Customer((int) $order->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            return;
        }

        $this->formValues['full_name'] = trim((string) $customer->firstname . ' ' . (string) $customer->lastname);
        $this->formValues['email'] = Tools::strtolower((string) $customer->email);
        $this->formValues['order_reference'] = (string) $order->reference;
    }

    private function isLookupRateLimited(): bool
    {
        if (!$this->module->ensureDatabase()) {
            return false;
        }

        $blockedUntil = (string) Db::getInstance()->getValue(
            'SELECT `blocked_until` FROM `' . _DB_PREFIX_ . 'cci_withdrawal_lookup_attempt`'
            . ' WHERE `attempt_hash` = \'' . pSQL($this->getLookupAttemptHash()) . '\''
        );

        return $blockedUntil !== '' && strtotime($blockedUntil) > time();
    }

    private function recordFailedLookup(): void
    {
        if (!$this->module->ensureDatabase()) {
            return;
        }

        $database = Db::getInstance();
        $attemptHash = $this->getLookupAttemptHash();
        $attempt = $database->getRow(
            'SELECT `attempt_count`, `window_started_at` FROM `' . _DB_PREFIX_ . 'cci_withdrawal_lookup_attempt`'
            . ' WHERE `attempt_hash` = \'' . pSQL($attemptHash) . '\''
        );
        $now = date('Y-m-d H:i:s');
        $windowExpired = !is_array($attempt)
            || strtotime((string) $attempt['window_started_at']) < time() - 900;
        $attemptCount = $windowExpired ? 1 : (int) $attempt['attempt_count'] + 1;
        $data = [
            'attempt_count' => $attemptCount,
            'window_started_at' => $windowExpired ? $now : (string) $attempt['window_started_at'],
            'blocked_until' => $attemptCount >= 5 ? date('Y-m-d H:i:s', time() + 900) : null,
        ];

        if (is_array($attempt)) {
            $database->update(
                'cci_withdrawal_lookup_attempt',
                $data,
                '`attempt_hash` = \'' . pSQL($attemptHash) . '\'',
                0,
                true
            );
        } else {
            $data['attempt_hash'] = pSQL($attemptHash);
            $database->insert('cci_withdrawal_lookup_attempt', $data, true, true, Db::INSERT_IGNORE);
        }
    }

    private function clearLookupFailures(): void
    {
        if ($this->module->ensureDatabase()) {
            Db::getInstance()->delete(
                'cci_withdrawal_lookup_attempt',
                '`attempt_hash` = \'' . pSQL($this->getLookupAttemptHash()) . '\''
            );
        }
    }

    private function getLookupAttemptHash(): string
    {
        return hash_hmac(
            'sha256',
            implode('|', [
                (string) Tools::getRemoteAddr(),
                Tools::strtolower((string) $this->formValues['email']),
                (int) $this->context->shop->id,
            ]),
            (string) _COOKIE_KEY_
        );
    }

    private function prefillCustomerValues(): void
    {
        if ($this->isFormSubmission() || !$this->context->customer || !$this->context->customer->isLogged()) {
            return;
        }

        $this->formValues['full_name'] = trim($this->context->customer->firstname . ' ' . $this->context->customer->lastname);
        $this->formValues['email'] = Tools::strtolower((string) $this->context->customer->email);
    }

    private function readSubmissionToken(): string
    {
        $token = (string) Tools::getValue('cci_withdrawal_submission_token');

        return preg_match('/^[a-f0-9]{32}$/D', $token) ? $token : '';
    }

    private function generateSubmissionToken(): string
    {
        try {
            return bin2hex(random_bytes(16));
        } catch (Throwable $exception) {
            return md5(Tools::passwdGen(64, 'RANDOM') . microtime(true));
        }
    }

    private function isValidToken(): bool
    {
        $expectedToken = (string) $this->module->getFormToken();
        $submittedToken = (string) Tools::getValue('cci_withdrawal_token');

        return $submittedToken !== '' && hash_equals($expectedToken, $submittedToken);
    }

    private function normalizeText($value, int $maxLength): string
    {
        $value = trim((string) $value);
        $value = strip_tags($value);

        if (Tools::strlen($value) > $maxLength) {
            $value = Tools::substr($value, 0, $maxLength);
        }

        return $value;
    }
}
