<?php

/**
 * CCI Withdrawal Form for PrestaShop.
 *
 * @author    Cool Cat Ideas <info@coolcatideas.com>
 * @copyright 2026 Cool Cat Ideas
 * @license   https://opensource.org/license/afl-3-0-php Academic Free License 3.0 (AFL-3.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/Admin/CciWithdrawalAdminPresentation.php';

class Cci_Withdrawal extends Module
{
    private const ROUTE_RULE = 'odstapienie-od-umowy';
    private const EN_ROUTE_RULE = 'withdrawal-form';
    private const ADMIN_PARENT_TAB_CLASS = 'AdminCciParent';
    private const ADMIN_TAB_CLASS = 'AdminCciWithdrawal';
    private const ADMIN_SUBMISSIONS_TAB_CLASS = 'AdminCciWithdrawalSubmissions';
    private const ADMIN_SETTINGS_TAB_CLASS = 'AdminCciWithdrawalSettings';
    private const CONFIG_EXCLUDED_PRODUCTS = 'CCI_WITHDRAWAL_EXCLUDED_PRODUCTS';
    private const CONFIG_EXCLUDE_CUSTOMIZED = 'CCI_WITHDRAWAL_EXCLUDE_CUSTOMIZED';
    private const CONFIG_BUYER_CONSUMER = 'CCI_WITHDRAWAL_BUYER_CONSUMER';
    private const CONFIG_BUYER_SOLE_TRADER = 'CCI_WITHDRAWAL_BUYER_SOLE_TRADER';
    private const CONFIG_BUYER_BUSINESS = 'CCI_WITHDRAWAL_BUYER_BUSINESS';
    private const CONFIG_ALLOW_GUEST_ORDERS = 'CCI_WITHDRAWAL_ALLOW_GUEST_ORDERS';
    private const CONFIG_SHOW_ADDITIONAL_INFO = 'CCI_WITHDRAWAL_SHOW_ADDITIONAL_INFO';
    private const CONFIG_REQUIRE_ADDITIONAL_INFO = 'CCI_WITHDRAWAL_REQUIRE_ADDITIONAL_INFO';
    private const CONFIG_SHOW_FOOTER_LINK = 'CCI_WITHDRAWAL_SHOW_FOOTER_LINK';
    private const CONFIG_SHOW_ACCOUNT_LINK = 'CCI_WITHDRAWAL_SHOW_ACCOUNT_LINK';
    private const CONFIG_SHOW_ORDER_LINKS = 'CCI_WITHDRAWAL_SHOW_ORDER_LINKS';
    private const CONFIG_SHOW_CONFIRMATION_LINK = 'CCI_WITHDRAWAL_SHOW_CONFIRMATION_LINK';
    private const CONFIG_SEND_ORDER_EMAIL = 'CCI_WITHDRAWAL_SEND_ORDER_EMAIL';
    private const CONFIG_ATTACH_PDF = 'CCI_WITHDRAWAL_ATTACH_PDF';
    private const FRONT_HOOKS = [
        'moduleRoutes',
        'registerGDPRConsent',
        'actionFrontControllerSetMedia',
        'displayHeader',
        'displayFooter',
        'displayCustomerAccount',
        'displayOrderDetail',
        'displayOrderConfirmation2',
        'actionValidateOrder',
    ];

    private ?CciWithdrawalAdminPresentation $adminPresentation = null;

    public function __construct()
    {
        $this->name = 'cci_withdrawal';
        $this->tab = 'front_office_features';
        $this->version = '1.0.1';
        $this->author = 'Cool Cat Ideas';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = [
            'min' => '9.0.0',
            'max' => '9.2.0',
        ];

        parent::__construct();

        $this->displayName = 'CCI Withdrawal Form';
        $this->description = $this->trans(
            'Records full or partial withdrawal statements independently and links compatible requests to the native PrestaShop returns workflow.',
            [],
            'Modules.Cciwithdrawal.Admin'
        );
    }

    public function install()
    {
        return parent::install()
            && $this->registerHook('actionAdminBreadcrumbModifier')
            && $this->registerHook(self::FRONT_HOOKS)
            && Configuration::updateValue(self::CONFIG_EXCLUDED_PRODUCTS, '')
            && Configuration::updateValue(self::CONFIG_EXCLUDE_CUSTOMIZED, 1)
            && Configuration::updateValue(self::CONFIG_BUYER_CONSUMER, 1)
            && Configuration::updateValue(self::CONFIG_BUYER_SOLE_TRADER, 1)
            && Configuration::updateValue(self::CONFIG_BUYER_BUSINESS, 1)
            && Configuration::updateValue(self::CONFIG_ALLOW_GUEST_ORDERS, 1)
            && Configuration::updateValue(self::CONFIG_SHOW_ADDITIONAL_INFO, 1)
            && Configuration::updateValue(self::CONFIG_REQUIRE_ADDITIONAL_INFO, 0)
            && Configuration::updateValue(self::CONFIG_SHOW_FOOTER_LINK, 1)
            && Configuration::updateValue(self::CONFIG_SHOW_ACCOUNT_LINK, 1)
            && Configuration::updateValue(self::CONFIG_SHOW_ORDER_LINKS, 1)
            && Configuration::updateValue(self::CONFIG_SHOW_CONFIRMATION_LINK, 1)
            && Configuration::updateValue(self::CONFIG_SEND_ORDER_EMAIL, 1)
            && Configuration::updateValue(self::CONFIG_ATTACH_PDF, 1)
            && $this->installDatabase()
            && $this->ensureAdminTab();
    }

    public function uninstall()
    {
        return $this->uninstallAdminTab()
            && $this->uninstallDatabase()
            && Configuration::deleteByName(self::CONFIG_EXCLUDED_PRODUCTS)
            && Configuration::deleteByName(self::CONFIG_EXCLUDE_CUSTOMIZED)
            && Configuration::deleteByName(self::CONFIG_BUYER_CONSUMER)
            && Configuration::deleteByName(self::CONFIG_BUYER_SOLE_TRADER)
            && Configuration::deleteByName(self::CONFIG_BUYER_BUSINESS)
            && Configuration::deleteByName(self::CONFIG_ALLOW_GUEST_ORDERS)
            && Configuration::deleteByName(self::CONFIG_SHOW_ADDITIONAL_INFO)
            && Configuration::deleteByName(self::CONFIG_REQUIRE_ADDITIONAL_INFO)
            && Configuration::deleteByName(self::CONFIG_SHOW_FOOTER_LINK)
            && Configuration::deleteByName(self::CONFIG_SHOW_ACCOUNT_LINK)
            && Configuration::deleteByName(self::CONFIG_SHOW_ORDER_LINKS)
            && Configuration::deleteByName(self::CONFIG_SHOW_CONFIRMATION_LINK)
            && Configuration::deleteByName(self::CONFIG_SEND_ORDER_EMAIL)
            && Configuration::deleteByName(self::CONFIG_ATTACH_PDF)
            && parent::uninstall();
    }

    public function hookModuleRoutes($params)
    {
        unset($params);

        return [
            'module-cci_withdrawal-form-en' => [
                'controller' => 'form',
                'rule' => self::EN_ROUTE_RULE,
                'keywords' => [],
                'params' => [
                    'fc' => 'module',
                    'module' => $this->name,
                ],
            ],
            'module-cci_withdrawal-form' => [
                'controller' => 'form',
                'rule' => self::ROUTE_RULE,
                'keywords' => [],
                'params' => [
                    'fc' => 'module',
                    'module' => $this->name,
                ],
            ],
        ];
    }

    /**
     * Marker hook used by the official PrestaShop GDPR module to discover
     * modules which expose a customer-facing form.
     */
    public function hookRegisterGDPRConsent(array $params): string
    {
        unset($params);

        return '';
    }

    private function canSaveAdminSettings(): bool
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
            || !Validate::isLoadedObject($this->context->employee)
            || !$this->context->employee->hasAuthOnShop((int) $this->context->shop->id)) {
            return false;
        }
        $controller = $this->context->controller;
        if ($controller instanceof AdminController) {
            return $controller->access('edit') && $controller->checkToken();
        }
        // The module manager in PrestaShop 9 uses a Symfony legacy context.
        if (!$controller instanceof \PrestaShop\PrestaShop\Core\Context\LegacyControllerContext
            || !$this->getPermission('configure')) {
            return false;
        }
        $container = \PrestaShop\PrestaShop\Adapter\SymfonyContainer::getInstance();
        return $container !== null
            && $container->get(\PrestaShopBundle\Security\Admin\UserTokenManager::class)->isTokenValid();
    }

    public function getContent()
    {
        $this->ensureDatabase();
        $this->ensureAdminTab();
        $this->ensureFrontendHooks();
        $this->registerAdminAssets();

        $output = '';

        if (Tools::isSubmit('submitCciWithdrawalConfiguration')) {
            if (!$this->canSaveAdminSettings()) {
                http_response_code(403);
                return $this->displayError($this->l('You do not have permission to change these settings.'));
            }

            $excludedProducts = $this->normalizeProductIds((string) Tools::getValue(self::CONFIG_EXCLUDED_PRODUCTS));
            $excludeCustomized = (bool) Tools::getValue(self::CONFIG_EXCLUDE_CUSTOMIZED);
            $buyerOptions = [
                self::CONFIG_BUYER_CONSUMER => (bool) Tools::getValue(self::CONFIG_BUYER_CONSUMER),
                self::CONFIG_BUYER_SOLE_TRADER => (bool) Tools::getValue(self::CONFIG_BUYER_SOLE_TRADER),
                self::CONFIG_BUYER_BUSINESS => (bool) Tools::getValue(self::CONFIG_BUYER_BUSINESS),
            ];
            $allowGuestOrders = (bool) Tools::getValue(self::CONFIG_ALLOW_GUEST_ORDERS);
            $showAdditionalInfo = (bool) Tools::getValue(self::CONFIG_SHOW_ADDITIONAL_INFO);
            $placementOptions = [
                self::CONFIG_SHOW_FOOTER_LINK,
                self::CONFIG_SHOW_ACCOUNT_LINK,
                self::CONFIG_SHOW_ORDER_LINKS,
                self::CONFIG_SHOW_CONFIRMATION_LINK,
                self::CONFIG_SEND_ORDER_EMAIL,
                self::CONFIG_ATTACH_PDF,
            ];

            if (!in_array(true, $buyerOptions, true)) {
                $output .= $this->displayError($this->trans(
                    'Enable at least one purchase capacity option.',
                    [],
                    'Modules.Cciwithdrawal.Admin'
                ));
            } else {
                Configuration::updateValue(self::CONFIG_EXCLUDED_PRODUCTS, implode(',', $excludedProducts));
                Configuration::updateValue(self::CONFIG_EXCLUDE_CUSTOMIZED, (int) $excludeCustomized);
                foreach ($buyerOptions as $configurationKey => $enabled) {
                    Configuration::updateValue($configurationKey, (int) $enabled);
                }
                Configuration::updateValue(self::CONFIG_ALLOW_GUEST_ORDERS, (int) $allowGuestOrders);
                Configuration::updateValue(self::CONFIG_SHOW_ADDITIONAL_INFO, (int) $showAdditionalInfo);
                Configuration::updateValue(self::CONFIG_REQUIRE_ADDITIONAL_INFO, 0);
                foreach ($placementOptions as $configurationKey) {
                    Configuration::updateValue($configurationKey, (int) (bool) Tools::getValue($configurationKey));
                }
                $output .= $this->displayConfirmation($this->trans('Settings updated.', [], 'Admin.Notifications.Success'));
            }
        }

        if (!(bool) Configuration::get('PS_ORDER_RETURN')) {
            $output .= $this->displayWarning($this->trans(
                'PrestaShop merchandise returns are disabled. Statements are still recorded and listed for manual review; enable native returns to create RMA records automatically.',
                [],
                'Modules.Cciwithdrawal.Admin'
            ));
        }

        return '<div class="cci-admin-shell cci-withdrawal-admin-shell bootstrap">'
            . '<main class="cci-admin-main">'
            . $output
            . $this->getAdminPresentation()->renderHeroSection()
            . '<section class="cci-card cci-card--settings">'
            . '<div class="cci-card-inner">'
            . '<div class="cci-section-title"><h3>'
            . $this->escapeHtml($this->trans('Withdrawal form settings', [], 'Modules.Cciwithdrawal.Admin'))
            . '</h3></div>'
            . '<p class="cci-subtitle">'
            . $this->escapeHtml($this->trans(
                'Control who can use the form and which goods are excluded from the statutory withdrawal workflow.',
                [],
                'Modules.Cciwithdrawal.Admin'
            ))
            . '</p>'
            . $this->getAdminPresentation()->renderNativeReturnsNotice()
            . '<div class="cci-form-wrapper">' . $this->renderConfigurationForm() . '</div>'
            . '</div>'
            . '</section>'
            . '</main>'
            . $this->getAdminPresentation()->renderBrandPanel()
            . '</div>';
    }

    /**
     * Creates or repairs the visible shortcut for already installed 1.0.0 copies.
     */
    public function ensureAdminTab(): bool
    {
        if (!$this->isRegisteredInHook('actionAdminBreadcrumbModifier')
            && !$this->registerHook('actionAdminBreadcrumbModifier')) {
            return false;
        }

        $parentId = $this->ensureCciParentTab();
        if ($parentId <= 0) {
            return false;
        }

        if (!$this->upsertAdminTab(
            self::ADMIN_TAB_CLASS,
            'CCI Withdrawal Form',
            $parentId,
            'assignment_return'
        )) {
            return false;
        }

        $moduleTabId = (int) Tab::getIdFromClassName(self::ADMIN_TAB_CLASS);
        if ($moduleTabId <= 0
            || !$this->upsertAdminTab(
                self::ADMIN_SUBMISSIONS_TAB_CLASS,
                'Submitted withdrawals',
                $moduleTabId,
                '',
                'Modules.Cciwithdrawal.Admin'
            )
            || !$this->upsertAdminTab(
                self::ADMIN_SETTINGS_TAB_CLASS,
                'Settings',
                $moduleTabId,
                '',
                'Admin.Global'
            )) {
            return false;
        }

        return $this->normalizeCciMenuPositions($parentId);
    }

    private function upsertAdminTab(
        string $className,
        string $wording,
        int $parentId,
        string $icon = '',
        ?string $wordingDomain = null,
    ): bool {
        $tabId = (int) Tab::getIdFromClassName($className);
        $tab = $tabId > 0 ? new Tab($tabId) : new Tab();
        $tab->active = 1;
        $tab->class_name = $className;
        $tab->id_parent = $parentId;
        $tab->icon = $icon;
        $tab->module = $this->name;
        $tab->wording = $wording;
        $tab->wording_domain = $wordingDomain;

        foreach (Language::getLanguages(false) as $language) {
            $tab->name[(int) $language['id_lang']] = $wordingDomain === null
                ? $wording
                : $this->trans($wording, [], $wordingDomain, (string) $language['locale']);
        }

        return $tabId > 0 ? (bool) $tab->update() : (bool) $tab->add();
    }

    public function hookActionAdminBreadcrumbModifier(array $params): void
    {
        $controllerName = (string) Tools::getValue('controller');
        if (!in_array($controllerName, [self::ADMIN_SUBMISSIONS_TAB_CLASS, self::ADMIN_SETTINGS_TAB_CLASS], true)
            || !isset($params['breadcrumb'])
            || !is_array($params['breadcrumb'])) {
            return;
        }

        $tabLink = $this->context->link->getAdminLink(self::ADMIN_SUBMISSIONS_TAB_CLASS);
        if (is_array($params['breadcrumb']['tab'] ?? null)) {
            $params['breadcrumb']['container'] = [
                'name' => 'Cool Cat Ideas',
                'href' => '',
                'icon' => 'pets',
            ];
            $params['breadcrumb']['tab'] = [
                'name' => $this->displayName,
                'href' => $tabLink,
                'icon' => 'assignment_return',
            ];

            return;
        }

        $params['breadcrumb']['container'] = new PrestaShopBundle\Twig\Layout\MenuLink(
            'Cool Cat Ideas',
            '',
            'pets'
        );
        $params['breadcrumb']['tab'] = new PrestaShopBundle\Twig\Layout\MenuLink(
            $this->displayName,
            $tabLink,
            'assignment_return'
        );
    }

    public function renderWithdrawalsPage(string $list): string
    {
        return '<div class="cci-admin-shell cci-withdrawal-admin-shell bootstrap">'
            . '<main class="cci-admin-main">'
            . $this->getAdminPresentation()->renderHeroSection()
            . '<div class="cci-native-admin-content">' . $list . '</div>'
            . '</main>'
            . $this->getAdminPresentation()->renderBrandPanel()
            . '</div>';
    }

    private function ensureCciParentTab(): int
    {
        $improveTabId = (int) Tab::getIdFromClassName('IMPROVE');
        if ($improveTabId <= 0) {
            return 0;
        }

        $tabId = (int) Tab::getIdFromClassName(self::ADMIN_PARENT_TAB_CLASS);
        $tab = $tabId > 0 ? new Tab($tabId) : new Tab();
        $tab->active = 1;
        $tab->class_name = self::ADMIN_PARENT_TAB_CLASS;
        $tab->id_parent = $improveTabId;
        $tab->icon = 'pets';
        $tab->module = '';

        foreach (Language::getLanguages(false) as $language) {
            $tab->name[(int) $language['id_lang']] = 'Cool Cat Ideas';
        }

        $saved = $tabId > 0 ? (bool) $tab->update() : (bool) $tab->add();
        if (!$saved) {
            return 0;
        }

        $modulesTabId = (int) Tab::getIdFromClassName('AdminParentModulesSf');
        if ($modulesTabId > 0) {
            $modulesTab = new Tab($modulesTabId);
            $tab = new Tab((int) $tab->id);
            if ((int) $modulesTab->id_parent === $improveTabId) {
                $desiredPosition = (int) $modulesTab->position + 1;
                if ((int) $tab->position !== $desiredPosition) {
                    $tab->updatePosition((int) $tab->position < $desiredPosition, $desiredPosition);
                }
            }
        }

        $tab->cleanPositions($improveTabId);

        return (int) $tab->id;
    }

    private function normalizeCciMenuPositions(int $parentId): bool
    {
        $tabs = Db::getInstance()->executeS(
            'SELECT `id_tab`, `class_name`, `position` FROM `' . _DB_PREFIX_ . 'tab`'
            . ' WHERE `id_parent` = ' . $parentId
        );
        if (!is_array($tabs)) {
            return false;
        }

        $priorities = [
            'AdminCciBlog' => 10,
            'AdminCciWithdrawal' => 20,
            'AdminCciConsentHistory' => 25,
            'AdminCciNiceMenu' => 30,
            'AdminCciCategoryGrid' => 40,
            'AdminCciCourierCashOnDelivery' => 50,
        ];
        usort($tabs, static function (array $left, array $right) use ($priorities): int {
            $leftPriority = $priorities[$left['class_name']] ?? 1000 + (int) $left['position'];
            $rightPriority = $priorities[$right['class_name']] ?? 1000 + (int) $right['position'];

            return $leftPriority <=> $rightPriority ?: (int) $left['id_tab'] <=> (int) $right['id_tab'];
        });

        foreach ($tabs as $index => $tabData) {
            if (!Db::getInstance()->update('tab', ['position' => $index + 1], 'id_tab = ' . (int) $tabData['id_tab'])) {
                return false;
            }
        }

        return true;
    }

    public function getWithdrawalLink(): string
    {
        return $this->buildWithdrawalLink([], (int) $this->context->language->id, (int) $this->context->shop->id);
    }

    private function buildWithdrawalLink(array $params, int $idLang, int $idShop): string
    {
        // Module routes are shared by all languages, so select the named route
        // explicitly instead of always using the Polish getModuleLink default.
        $route = Language::getIsoById($idLang) === 'pl'
            ? 'module-cci_withdrawal-form'
            : 'module-cci_withdrawal-form-en';

        return $this->context->link->getPageLink($route, true, $idLang, $params, false, $idShop);
    }

    public function getFormToken(): string
    {
        return Tools::hash($this->name . '|' . Tools::getToken(false));
    }

    public function ensureFrontendHooks(): bool
    {
        foreach (self::FRONT_HOOKS as $hookName) {
            if (!$this->isRegisteredInHook($hookName) && !$this->registerHook($hookName)) {
                return false;
            }
        }

        return $this->ensureFrontendGroupRestrictions();
    }

    private function ensureFrontendGroupRestrictions(): bool
    {
        $shopIds = Shop::getShops(true, null, true);
        if (!$this->id || !$shopIds) {
            return false;
        }

        foreach ($shopIds as $shopId) {
            $sql = 'INSERT IGNORE INTO `' . _DB_PREFIX_ . 'module_group` (`id_module`, `id_shop`, `id_group`) '
                . 'SELECT ' . (int) $this->id . ', ' . (int) $shopId . ', `id_group` '
                . 'FROM `' . _DB_PREFIX_ . 'group`';
            if (!Db::getInstance()->execute($sql)) {
                return false;
            }
        }

        Cache::clean(Hook::MODULE_LIST_BY_HOOK_KEY . '*');

        return true;
    }

    public function getOrderWithdrawalLink(Order $order): string
    {
        return $this->buildWithdrawalLink([
            'withdrawal_order' => (int) $order->id,
            'withdrawal_access' => $this->getOrderAccessToken($order),
        ], (int) $order->id_lang, (int) $order->id_shop);
    }

    public function resolveOrderAccessRequest(int $orderId, string $accessToken = ''): ?Order
    {
        if ($orderId <= 0) {
            return null;
        }

        $order = new Order($orderId);
        if (!Validate::isLoadedObject($order) || (int) $order->id_shop !== (int) $this->context->shop->id) {
            return null;
        }

        if ($accessToken !== '' && hash_equals($this->getOrderAccessToken($order), $accessToken)) {
            return $order;
        }

        if ($this->context->customer
            && $this->context->customer->isLogged()
            && (int) $order->id_customer === (int) $this->context->customer->id) {
            return $order;
        }

        return null;
    }

    public function hookDisplayHeader(array $params): void
    {
        $this->registerFrontendAssets($params);
    }

    public function hookActionFrontControllerSetMedia(array $params = []): void
    {
        $this->registerFrontendAssets($params);
    }

    private function registerFrontendAssets(array $params = []): void
    {
        unset($params);

        $controller = $this->context->controller;
        $cssFile = __DIR__ . '/views/css/front.css';
        $version = (string) (@filemtime($cssFile) ?: $this->version);

        if (method_exists($controller, 'registerStylesheet')) {
            $controller->registerStylesheet(
                'module-cci-withdrawal-front',
                __PS_BASE_URI__ . 'modules/' . $this->name . '/views/css/front.css',
                ['media' => 'all', 'priority' => 200, 'version' => $version]
            );
        }

        if (!$this->showsOrderLinks() || !method_exists($controller, 'registerJavascript')) {
            return;
        }

        $jsFile = __DIR__ . '/views/js/front-access.js';
        $controller->registerJavascript(
            'module-cci-withdrawal-order-links',
            __PS_BASE_URI__ . 'modules/' . $this->name . '/views/js/front-access.js',
            ['position' => 'bottom', 'priority' => 200, 'version' => (string) (@filemtime($jsFile) ?: $this->version)]
        );
        Media::addJsDef([
            'cciWithdrawalAccess' => [
                'formUrl' => $this->getWithdrawalLink(),
                'label' => $this->trans('Withdraw from contract', [], 'Modules.Cciwithdrawal.Shop'),
                'orderParameter' => 'withdrawal_order',
            ],
        ]);
    }

    public function hookDisplayFooter(array $params): string
    {
        unset($params);

        return $this->showsFooterLink() ? $this->renderAccessLink('footer') : '';
    }

    public function hookDisplayCustomerAccount(array $params): string
    {
        unset($params);

        return $this->showsAccountLink() ? $this->renderAccessLink('account') : '';
    }

    public function hookDisplayOrderDetail(array $params): string
    {
        if (!$this->showsOrderLinks()) {
            return '';
        }

        $order = $this->extractOrderFromHookParams($params);

        // Some themes execute displayOrderDetail without passing the Order object,
        // including on guest tracking. Keep the statutory entry point available;
        // authenticated customers still receive the signed, prefilled order link.
        return $this->renderAccessLink($order ? 'order' : 'order-generic', $order);
    }

    public function hookDisplayOrderConfirmation2(array $params): string
    {
        if (!$this->showsOrderConfirmationLink()) {
            return '';
        }

        $order = $this->extractOrderFromHookParams($params);

        return $order ? $this->renderAccessLink('confirmation', $order) : '';
    }

    public function hookActionValidateOrder(array $params): void
    {
        if (!$this->sendsOrderAccessEmail()) {
            return;
        }

        $order = $this->extractOrderFromHookParams($params);
        if (!$order) {
            return;
        }

        $customer = isset($params['customer']) && $params['customer'] instanceof Customer
            ? $params['customer']
            : new Customer((int) $order->id_customer);
        if (!Validate::isLoadedObject($customer) || !Validate::isEmail((string) $customer->email)) {
            return;
        }

        $shopName = (string) Configuration::get('PS_SHOP_NAME', null, null, (int) $order->id_shop);
        $shopEmail = (string) Configuration::get('PS_SHOP_EMAIL', null, null, (int) $order->id_shop);
        $sent = Mail::Send(
            (int) $order->id_lang,
            'withdrawal_access',
            $this->trans(
                'Withdrawal access for order %reference%',
                ['%reference%' => (string) $order->reference],
                'Modules.Cciwithdrawal.Shop'
            ),
            [
                '{customer_name}' => trim((string) $customer->firstname . ' ' . (string) $customer->lastname),
                '{order_reference}' => (string) $order->reference,
                '{withdrawal_url}' => $this->getOrderWithdrawalLink($order),
                '{shop_name}' => $shopName,
                '{shop_email}' => $shopEmail,
            ],
            (string) $customer->email,
            trim((string) $customer->firstname . ' ' . (string) $customer->lastname),
            Validate::isEmail($shopEmail) ? $shopEmail : null,
            $shopName,
            null,
            null,
            _PS_MODULE_DIR_ . $this->name . '/mails/',
            false,
            (int) $order->id_shop
        );

        if (!$sent) {
            PrestaShopLogger::addLog(
                'CCI Withdrawal: order access email could not be sent for order #' . (int) $order->id,
                2,
                null,
                'Order',
                (int) $order->id,
                true
            );
        }
    }

    private function getOrderAccessToken(Order $order): string
    {
        return hash_hmac(
            'sha256',
            implode('|', [(int) $order->id, (string) $order->reference, (int) $order->id_customer, (int) $order->id_shop]),
            (string) _COOKIE_KEY_
        );
    }

    private function extractOrderFromHookParams(array $params): ?Order
    {
        foreach (['order', 'objOrder'] as $key) {
            if (isset($params[$key]) && $params[$key] instanceof Order && Validate::isLoadedObject($params[$key])) {
                return $params[$key];
            }
        }

        $orderId = (int) ($params['id_order'] ?? 0);
        if ($orderId <= 0) {
            return null;
        }

        $order = new Order($orderId);

        return Validate::isLoadedObject($order) ? $order : null;
    }

    private function renderAccessLink(string $surface, ?Order $order = null): string
    {
        $this->context->smarty->assign([
            'cci_withdrawal_link_active' => $this->context->controller instanceof ModuleFrontController
                && $this->context->controller->module->name === $this->name,
            'cci_withdrawal_surface' => $surface,
            'cci_withdrawal_url' => $order ? $this->getOrderWithdrawalLink($order) : $this->getWithdrawalLink(),
        ]);

        $template = $surface === 'account'
            ? 'module:' . $this->name . '/views/templates/hook/account-link.tpl'
            : 'module:' . $this->name . '/views/templates/hook/access-link.tpl';

        return $this->fetch($template);
    }

    public function ensureDatabase(): bool
    {
        return $this->installDatabase() && $this->upgradeDatabaseSchema();
    }

    public function getReceiptToken(string $submissionToken): string
    {
        return hash_hmac('sha256', 'receipt|' . $submissionToken, (string) _COOKIE_KEY_);
    }

    public function getWithdrawalReference(int $submissionId): string
    {
        return sprintf('CCI-WD-%06d', $submissionId);
    }

    public function getWithdrawalSubmission(int $submissionId): ?array
    {
        if ($submissionId <= 0 || !$this->ensureDatabase()) {
            return null;
        }

        $submission = Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'cci_withdrawal_submission`'
            . ' WHERE `id_cci_withdrawal_submission` = ' . $submissionId
        );

        return is_array($submission) ? $submission : null;
    }

    public function getWithdrawalReceipt(int $submissionId, string $receiptToken): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $receiptToken)) {
            return null;
        }

        $submission = $this->getWithdrawalSubmission($submissionId);
        if (!$submission
            || (int) $submission['id_shop'] !== (int) $this->context->shop->id
            || empty($submission['public_token_hash'])
            || !hash_equals((string) $submission['public_token_hash'], hash('sha256', $receiptToken))) {
            return null;
        }

        $submission['payload'] = json_decode((string) $submission['statement_payload'], true) ?: [];
        $submission['reference'] = $this->getWithdrawalReference($submissionId);

        return $submission;
    }

    public function sendSubmissionEmails(int $submissionId, bool $sendMerchantNotification = true): array
    {
        $submission = $this->getWithdrawalSubmission($submissionId);
        if (!$submission) {
            return ['customer' => false, 'shop' => false];
        }

        $payload = json_decode((string) $submission['statement_payload'], true);
        if (!is_array($payload)) {
            $this->recordConfirmationResult($submissionId, false, 'The stored statement payload is invalid.');

            return ['customer' => false, 'shop' => false];
        }

        $shopEmail = (string) Configuration::get('PS_SHOP_EMAIL', null, null, (int) $submission['id_shop']);
        $shopName = (string) Configuration::get('PS_SHOP_NAME', null, null, (int) $submission['id_shop']);
        if ($shopEmail === '' || !Validate::isEmail($shopEmail)) {
            $this->recordConfirmationResult($submissionId, false, 'The shop email address is invalid.');

            return ['customer' => false, 'shop' => false];
        }

        $productsText = [];
        $productsHtml = [];
        foreach ((array) ($payload['products'] ?? []) as $product) {
            $line = sprintf(
                '%d x %s%s',
                (int) ($product['quantity'] ?? 0),
                (string) ($product['name'] ?? ''),
                !empty($product['reference']) ? ' (' . (string) $product['reference'] . ')' : ''
            );
            $productsText[] = $line;
            $productsHtml[] = Tools::htmlentitiesUTF8($line);
        }

        $language = new Language((int) $submission['id_lang']);
        $languageId = Validate::isLoadedObject($language)
            ? (int) $language->id
            : (int) Configuration::get('PS_LANG_DEFAULT');
        $attachment = $this->attachesConfirmationPdf()
            ? $this->buildConfirmationPdfAttachment($submission, $payload, $productsText, $language)
            : null;
        $templateVars = [
            '{shop_name}' => $shopName,
            '{customer_name}' => (string) $submission['customer_name'],
            '{customer_email}' => (string) $submission['customer_email'],
            '{order_reference}' => (string) $submission['order_reference'],
            '{return_reference}' => $this->getWithdrawalReference($submissionId),
            '{withdrawal_scope}' => (string) ($payload['scope_label'] ?? ''),
            '{buyer_status}' => (string) ($payload['buyer_status_label'] ?? ''),
            '{selected_products}' => implode('<br>', $productsHtml),
            '{selected_products_txt}' => implode("\n", $productsText),
            '{message}' => !empty($payload['message']) ? nl2br(Tools::htmlentitiesUTF8((string) $payload['message'])) : '-',
            '{message_txt}' => !empty($payload['message']) ? (string) $payload['message'] : '-',
            '{submitted_at}' => (string) ($payload['submitted_at_display'] ?? $submission['date_add']),
            '{processing_note}' => $submission['processing_status'] === 'rma_created'
                ? $this->trans(
                    'A native PrestaShop merchandise return was also created for this statement.',
                    [],
                    'Modules.Cciwithdrawal.Shop',
                    Validate::isLoadedObject($language) ? (string) $language->locale : null
                )
                : $this->trans(
                    'The statement was recorded independently and is awaiting manual review by the store team.',
                    [],
                    'Modules.Cciwithdrawal.Shop',
                    Validate::isLoadedObject($language) ? (string) $language->locale : null
                ),
            '{shop_email}' => $shopEmail,
        ];

        $customerSent = false;
        $shopSent = true;
        $lastError = '';

        try {
            $customerSent = (bool) Mail::Send(
                $languageId,
                'withdrawal_received',
                $this->trans('We received your withdrawal statement', [], 'Modules.Cciwithdrawal.Shop'),
                $templateVars,
                (string) $submission['customer_email'],
                (string) $submission['customer_name'],
                $shopEmail,
                $shopName,
                $attachment,
                null,
                _PS_MODULE_DIR_ . $this->name . '/mails/',
                false,
                (int) $submission['id_shop']
            );

            if ($sendMerchantNotification) {
                $nativeMerchantNotification = Module::isEnabled('ps_emailalerts')
                    && (bool) Configuration::getGlobalValue('MA_RETURN_SLIP')
                    && trim((string) Configuration::get('MA_RETURN_SLIP_EMAILS')) !== ''
                    && !empty($submission['id_order_return']);

                if (!$nativeMerchantNotification) {
                    $shopSent = (bool) Mail::Send(
                        $languageId,
                        'withdrawal_merchant',
                        $this->trans('New withdrawal statement', [], 'Modules.Cciwithdrawal.Shop'),
                        $templateVars,
                        $shopEmail,
                        $shopName,
                        (string) $submission['customer_email'],
                        (string) $submission['customer_name'],
                        null,
                        null,
                        _PS_MODULE_DIR_ . $this->name . '/mails/',
                        false,
                        (int) $submission['id_shop'],
                        null,
                        (string) $submission['customer_email']
                    );
                }
            }
        } catch (Throwable $exception) {
            $lastError = Tools::substr($exception->getMessage(), 0, 255);
            PrestaShopLogger::addLog(
                'CCI Withdrawal: confirmation error for submission #' . $submissionId . ': ' . $lastError,
                3,
                null,
                'Order',
                (int) $submission['id_order'],
                true
            );
        }

        if (!$customerSent && $lastError === '') {
            $lastError = 'Mail::Send returned false.';
        }
        $this->recordConfirmationResult($submissionId, $customerSent, $lastError);

        return ['customer' => $customerSent, 'shop' => $shopSent];
    }

    public function getConfirmationPdfAttachment(int $submissionId): ?array
    {
        $submission = $this->getWithdrawalSubmission($submissionId);
        if (!$submission) {
            return null;
        }

        $payload = json_decode((string) $submission['statement_payload'], true);
        if (!is_array($payload)) {
            return null;
        }

        $products = [];
        foreach ((array) ($payload['products'] ?? []) as $product) {
            $products[] = sprintf(
                '%d x %s%s',
                (int) ($product['quantity'] ?? 0),
                (string) ($product['name'] ?? ''),
                !empty($product['reference']) ? ' (' . (string) $product['reference'] . ')' : ''
            );
        }

        return $this->buildConfirmationPdfAttachment(
            $submission,
            $payload,
            $products,
            new Language((int) $submission['id_lang'])
        );
    }

    private function recordConfirmationResult(int $submissionId, bool $sent, string $error): void
    {
        $date = date('Y-m-d H:i:s');
        Db::getInstance()->execute(
            'UPDATE `' . _DB_PREFIX_ . 'cci_withdrawal_submission` SET '
            . '`confirmation_status` = \'' . ($sent ? 'sent' : 'failed') . '\', '
            . '`confirmation_attempts` = `confirmation_attempts` + 1, '
            . '`confirmation_last_error` = \'' . pSQL(Tools::substr($error, 0, 255)) . '\', '
            . '`confirmation_date` = ' . ($sent ? '\'' . pSQL($date) . '\'' : 'NULL') . ', '
            . '`date_upd` = \'' . pSQL($date) . '\' '
            . 'WHERE `id_cci_withdrawal_submission` = ' . $submissionId
        );
    }

    private function buildConfirmationPdfAttachment(
        array $submission,
        array $payload,
        array $products,
        Language $language,
    ): ?array {
        try {
            $pdf = new PDFGenerator(false);
            $pdf->setFontForLang(Validate::isLoadedObject($language) ? (string) $language->iso_code : 'en');
            $pdf->createHeader('<h1>' . Tools::htmlentitiesUTF8($this->trans(
                'Withdrawal statement confirmation',
                [],
                'Modules.Cciwithdrawal.Shop'
            )) . '</h1>');
            $pdf->createFooter('<p>' . Tools::htmlentitiesUTF8((string) Configuration::get(
                'PS_SHOP_NAME',
                null,
                null,
                (int) $submission['id_shop']
            )) . '</p>');
            $pdf->createPagination('');
            $pdf->createContent(
                '<p><strong>' . Tools::htmlentitiesUTF8($this->getWithdrawalReference((int) $submission['id_cci_withdrawal_submission'])) . '</strong></p>'
                . '<p>' . nl2br(Tools::htmlentitiesUTF8((string) ($payload['statement_text'] ?? ''))) . '</p>'
                . '<p><strong>' . Tools::htmlentitiesUTF8($this->trans('Products', [], 'Modules.Cciwithdrawal.Shop')) . '</strong><br>'
                . implode('<br>', array_map([Tools::class, 'htmlentitiesUTF8'], $products)) . '</p>'
                . '<p>' . Tools::htmlentitiesUTF8($this->trans('Submission date and time', [], 'Modules.Cciwithdrawal.Shop')) . ': '
                . Tools::htmlentitiesUTF8((string) ($payload['submitted_at_display'] ?? $submission['date_add'])) . '</p>'
            );
            $pdf->writePage();
            $filename = $this->getWithdrawalReference((int) $submission['id_cci_withdrawal_submission']) . '.pdf';

            return [
                'content' => $pdf->render($filename, false),
                'name' => $filename,
                'mime' => 'application/pdf',
            ];
        } catch (Throwable $exception) {
            PrestaShopLogger::addLog(
                'CCI Withdrawal: PDF generation failed for submission #'
                . (int) $submission['id_cci_withdrawal_submission'] . ': ' . $exception->getMessage(),
                2
            );

            return null;
        }
    }

    public function getExcludedProductIds(): array
    {
        return $this->normalizeProductIds((string) Configuration::get(self::CONFIG_EXCLUDED_PRODUCTS));
    }

    public function shouldExcludeCustomizedProducts(): bool
    {
        return (bool) Configuration::get(self::CONFIG_EXCLUDE_CUSTOMIZED);
    }

    public function getEnabledBuyerStatuses(): array
    {
        $configuration = [
            'consumer' => self::CONFIG_BUYER_CONSUMER,
            'sole_trader' => self::CONFIG_BUYER_SOLE_TRADER,
            'business' => self::CONFIG_BUYER_BUSINESS,
        ];
        $enabledStatuses = [];

        foreach ($configuration as $status => $configurationKey) {
            if (!Configuration::hasKey($configurationKey) || (bool) Configuration::get($configurationKey)) {
                $enabledStatuses[] = $status;
            }
        }

        return $enabledStatuses;
    }

    public function allowsGuestOrders(): bool
    {
        return $this->getBooleanConfiguration(self::CONFIG_ALLOW_GUEST_ORDERS, true);
    }

    public function showsAdditionalInformation(): bool
    {
        return $this->getBooleanConfiguration(self::CONFIG_SHOW_ADDITIONAL_INFO, true);
    }

    public function requiresAdditionalInformation(): bool
    {
        return false;
    }

    public function showsFooterLink(): bool
    {
        return $this->getBooleanConfiguration(self::CONFIG_SHOW_FOOTER_LINK, true);
    }

    public function showsAccountLink(): bool
    {
        return $this->getBooleanConfiguration(self::CONFIG_SHOW_ACCOUNT_LINK, true);
    }

    public function showsOrderLinks(): bool
    {
        return $this->getBooleanConfiguration(self::CONFIG_SHOW_ORDER_LINKS, true);
    }

    public function showsOrderConfirmationLink(): bool
    {
        return $this->getBooleanConfiguration(self::CONFIG_SHOW_CONFIRMATION_LINK, true);
    }

    public function sendsOrderAccessEmail(): bool
    {
        return $this->getBooleanConfiguration(self::CONFIG_SEND_ORDER_EMAIL, true);
    }

    public function attachesConfirmationPdf(): bool
    {
        return $this->getBooleanConfiguration(self::CONFIG_ATTACH_PDF, true);
    }

    private function renderConfigurationForm(): string
    {
        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');
        $helper->allow_employee_form_lang = (int) Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitCciWithdrawalConfiguration';
        $controllerName = (string) Tools::getValue('controller');
        if ($controllerName === self::ADMIN_SETTINGS_TAB_CLASS) {
            $helper->currentIndex = $this->context->link->getAdminLink(self::ADMIN_SETTINGS_TAB_CLASS, false);
            $helper->token = Tools::getAdminTokenLite(self::ADMIN_SETTINGS_TAB_CLASS);
        } else {
            $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
                . '&configure=' . $this->name
                . '&tab_module=' . $this->tab
                . '&module_name=' . $this->name;
            $helper->token = Tools::getAdminTokenLite('AdminModules');
        }
        $helper->tpl_vars = [
            'fields_value' => [
                self::CONFIG_EXCLUDED_PRODUCTS => (string) Configuration::get(self::CONFIG_EXCLUDED_PRODUCTS),
                self::CONFIG_EXCLUDE_CUSTOMIZED => (bool) Configuration::get(self::CONFIG_EXCLUDE_CUSTOMIZED),
                self::CONFIG_BUYER_CONSUMER => $this->isBuyerStatusEnabled('consumer'),
                self::CONFIG_BUYER_SOLE_TRADER => $this->isBuyerStatusEnabled('sole_trader'),
                self::CONFIG_BUYER_BUSINESS => $this->isBuyerStatusEnabled('business'),
                self::CONFIG_ALLOW_GUEST_ORDERS => $this->allowsGuestOrders(),
                self::CONFIG_SHOW_ADDITIONAL_INFO => $this->showsAdditionalInformation(),
                self::CONFIG_SHOW_FOOTER_LINK => $this->showsFooterLink(),
                self::CONFIG_SHOW_ACCOUNT_LINK => $this->showsAccountLink(),
                self::CONFIG_SHOW_ORDER_LINKS => $this->showsOrderLinks(),
                self::CONFIG_SHOW_CONFIRMATION_LINK => $this->showsOrderConfirmationLink(),
                self::CONFIG_SEND_ORDER_EMAIL => $this->sendsOrderAccessEmail(),
                self::CONFIG_ATTACH_PDF => $this->attachesConfirmationPdf(),
            ],
        ];

        return $this->flattenHelperFormPanel($helper->generateForm([[
            'form' => [
                'legend' => [
                    'title' => $this->trans('Withdrawal eligibility', [], 'Modules.Cciwithdrawal.Admin'),
                    'icon' => 'icon-undo',
                ],
                'description' => $this->trans(
                    'The public form always records the statement first. When possible, the module then creates a native PrestaShop merchandise return. Use exclusions only for goods genuinely outside the statutory right.',
                    [],
                    'Modules.Cciwithdrawal.Admin'
                ),
                'input' => [
                    $this->createBooleanSwitch(
                        self::CONFIG_BUYER_CONSUMER,
                        'Show consumer purchases',
                        'Allows customers purchasing outside any business activity to use the statutory withdrawal form.'
                    ),
                    $this->createBooleanSwitch(
                        self::CONFIG_BUYER_SOLE_TRADER,
                        'Show non-professional sole trader purchases',
                        'Allows a sole trader to use the form after declaring that the purchase was not professional in nature.'
                    ),
                    $this->createBooleanSwitch(
                        self::CONFIG_BUYER_BUSINESS,
                        'Show company and professional purchases',
                        'Displays an informational option directing a business customer to ask about a voluntary return. It does not create a merchandise return.'
                    ),
                    $this->createBooleanSwitch(
                        self::CONFIG_ALLOW_GUEST_ORDERS,
                        'Allow guest orders',
                        'Allows a guest customer to submit the form using the order reference and email address.'
                    ),
                    $this->createBooleanSwitch(
                        self::CONFIG_SHOW_ADDITIONAL_INFO,
                        'Show additional information field',
                        'Displays an optional message field. It never blocks a statutory withdrawal statement.'
                    ),
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Exclude customized order lines', [], 'Modules.Cciwithdrawal.Admin'),
                        'name' => self::CONFIG_EXCLUDE_CUSTOMIZED,
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'active_on', 'value' => 1, 'label' => $this->trans('Enabled', [], 'Admin.Global')],
                            ['id' => 'active_off', 'value' => 0, 'label' => $this->trans('Disabled', [], 'Admin.Global')],
                        ],
                        'desc' => $this->trans(
                            'Automatically excludes order lines containing native PrestaShop customization data.',
                            [],
                            'Modules.Cciwithdrawal.Admin'
                        ),
                    ],
                    $this->createBooleanSwitch(
                        self::CONFIG_SHOW_FOOTER_LINK,
                        'Show the withdrawal link in the footer',
                        'Keeps the withdrawal function available from every storefront page.'
                    ),
                    $this->createBooleanSwitch(
                        self::CONFIG_SHOW_ACCOUNT_LINK,
                        'Show the withdrawal link in the customer account',
                        'Adds a dedicated shortcut to the customer account.'
                    ),
                    $this->createBooleanSwitch(
                        self::CONFIG_SHOW_ORDER_LINKS,
                        'Show withdrawal links with orders',
                        'Adds links to supported order-detail views and progressively enhances the standard order history.'
                    ),
                    $this->createBooleanSwitch(
                        self::CONFIG_SHOW_CONFIRMATION_LINK,
                        'Show the link after checkout',
                        'Adds an order-specific withdrawal link to the order confirmation page.'
                    ),
                    $this->createBooleanSwitch(
                        self::CONFIG_SEND_ORDER_EMAIL,
                        'Send the withdrawal access link after an order',
                        'Sends a separate transactional message with an order-specific access link because PrestaShop does not expose a safe universal slot inside every order confirmation template.'
                    ),
                    $this->createBooleanSwitch(
                        self::CONFIG_ATTACH_PDF,
                        'Attach a PDF confirmation',
                        'Attaches the exact withdrawal statement to the confirmation email.'
                    ),
                    [
                        'type' => 'text',
                        'label' => $this->trans('Other excluded product IDs', [], 'Modules.Cciwithdrawal.Admin'),
                        'name' => self::CONFIG_EXCLUDED_PRODUCTS,
                        'class' => 'fixed-width-xxl',
                        'desc' => $this->trans(
                            'Comma-separated product IDs, for example: 12,34,56. Use this for made-to-order goods not represented by native customization data.',
                            [],
                            'Modules.Cciwithdrawal.Admin'
                        ),
                    ],
                ],
                'submit' => [
                    'title' => $this->trans('Save', [], 'Admin.Actions'),
                    'icon' => 'process-icon-save',
                ],
            ],
        ]]));
    }

    private function createBooleanSwitch(string $name, string $label, string $description): array
    {
        return [
            'type' => 'switch',
            'label' => $this->trans($label, [], 'Modules.Cciwithdrawal.Admin'),
            'name' => $name,
            'is_bool' => true,
            'values' => [
                ['id' => $name . '_on', 'value' => 1, 'label' => $this->trans('Enabled', [], 'Admin.Global')],
                ['id' => $name . '_off', 'value' => 0, 'label' => $this->trans('Disabled', [], 'Admin.Global')],
            ],
            'desc' => $this->trans($description, [], 'Modules.Cciwithdrawal.Admin'),
        ];
    }

    private function isBuyerStatusEnabled(string $status): bool
    {
        return in_array($status, $this->getEnabledBuyerStatuses(), true);
    }

    private function getBooleanConfiguration(string $key, bool $default): bool
    {
        if (!Configuration::hasKey($key)) {
            return $default;
        }

        return (bool) Configuration::get($key);
    }

    private function normalizeProductIds(string $value): array
    {
        $productIds = preg_split('/[\s,;]+/', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $productIds = array_map('intval', $productIds);
        $productIds = array_values(array_unique(array_filter($productIds, static function (int $productId): bool {
            return $productId > 0;
        })));
        sort($productIds, SORT_NUMERIC);

        return $productIds;
    }

    private function flattenHelperFormPanel(string $formHtml): string
    {
        $flattened = preg_replace(
            '~<div class="panel" id="fieldset_[^"]*">\s*<div class="panel-heading">.*?</div>~s',
            '<div class="panel cci-helper-form-content">',
            $formHtml,
            1
        );

        return is_string($flattened) ? $flattened : $formHtml;
    }

    public function registerAdminAssets(): void
    {
        $cssFile = __DIR__ . '/views/css/admin.css';
        $version = (string) (@filemtime($cssFile) ?: $this->version);
        $controller = $this->context->controller;

        if (method_exists($controller, 'registerStylesheet')) {
            $controller->registerStylesheet(
                'cci-withdrawal-admin',
                'modules/' . $this->name . '/views/css/admin.css?v=' . rawurlencode($version),
                ['media' => 'all', 'priority' => 230]
            );
        } elseif (method_exists($controller, 'addCSS')) {
            $controller->addCSS($this->getPathUri() . 'views/css/admin.css?v=' . rawurlencode($version), 'all');
        }
    }

    private function escapeHtml($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    private function installDatabase(): bool
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'cci_withdrawal_submission` (
            `id_cci_withdrawal_submission` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_order_return` INT UNSIGNED NULL DEFAULT NULL,
            `id_order` INT UNSIGNED NOT NULL,
            `id_customer` INT UNSIGNED NOT NULL,
            `id_shop` INT UNSIGNED NOT NULL,
            `id_lang` INT UNSIGNED NOT NULL DEFAULT 0,
            `submission_hash` CHAR(64) NOT NULL,
            `public_token_hash` CHAR(64) NULL DEFAULT NULL,
            `buyer_status` VARCHAR(32) NOT NULL,
            `non_professional_declaration` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
            `company_detected` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
            `customer_name` VARCHAR(160) NOT NULL DEFAULT \'\',
            `customer_email` VARCHAR(255) NOT NULL,
            `order_reference` VARCHAR(64) NOT NULL DEFAULT \'\',
            `statement_payload` LONGTEXT NULL,
            `processing_status` VARCHAR(32) NOT NULL DEFAULT \'received\',
            `processing_message` VARCHAR(255) NOT NULL DEFAULT \'\',
            `confirmation_status` VARCHAR(32) NOT NULL DEFAULT \'pending\',
            `confirmation_attempts` INT UNSIGNED NOT NULL DEFAULT 0,
            `confirmation_last_error` VARCHAR(255) NOT NULL DEFAULT \'\',
            `confirmation_date` DATETIME NULL DEFAULT NULL,
            `date_add` DATETIME NOT NULL,
            `date_upd` DATETIME NOT NULL,
            PRIMARY KEY (`id_cci_withdrawal_submission`),
            UNIQUE KEY `uniq_cci_withdrawal_submission_hash` (`submission_hash`),
            UNIQUE KEY `uniq_cci_withdrawal_order_return` (`id_order_return`),
            KEY `idx_cci_withdrawal_public_token` (`public_token_hash`),
            KEY `idx_cci_withdrawal_order` (`id_order`),
            KEY `idx_cci_withdrawal_customer` (`id_customer`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $attemptSql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'cci_withdrawal_lookup_attempt` (
            `attempt_hash` CHAR(64) NOT NULL,
            `attempt_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `window_started_at` DATETIME NOT NULL,
            `blocked_until` DATETIME NULL DEFAULT NULL,
            PRIMARY KEY (`attempt_hash`),
            KEY `idx_cci_withdrawal_blocked_until` (`blocked_until`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        return Db::getInstance()->execute($sql) && Db::getInstance()->execute($attemptSql);
    }

    private function upgradeDatabaseSchema(): bool
    {
        $table = _DB_PREFIX_ . 'cci_withdrawal_submission';
        $columns = [
            'id_lang' => 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER `id_shop`',
            'public_token_hash' => 'CHAR(64) NULL DEFAULT NULL AFTER `submission_hash`',
            'customer_name' => 'VARCHAR(160) NOT NULL DEFAULT \'\' AFTER `company_detected`',
            'order_reference' => 'VARCHAR(64) NOT NULL DEFAULT \'\' AFTER `customer_email`',
            'statement_payload' => 'LONGTEXT NULL AFTER `order_reference`',
            'processing_status' => 'VARCHAR(32) NOT NULL DEFAULT \'received\' AFTER `statement_payload`',
            'processing_message' => 'VARCHAR(255) NOT NULL DEFAULT \'\' AFTER `processing_status`',
            'confirmation_status' => 'VARCHAR(32) NOT NULL DEFAULT \'pending\' AFTER `processing_message`',
            'confirmation_attempts' => 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER `confirmation_status`',
            'confirmation_last_error' => 'VARCHAR(255) NOT NULL DEFAULT \'\' AFTER `confirmation_attempts`',
            'confirmation_date' => 'DATETIME NULL DEFAULT NULL AFTER `confirmation_last_error`',
            'date_upd' => 'DATETIME NOT NULL DEFAULT \'1970-01-01 00:00:01\' AFTER `date_add`',
        ];

        foreach ($columns as $name => $definition) {
            if (!$this->databaseColumnExists($table, $name)
                && !Db::getInstance()->execute('ALTER TABLE `' . bqSQL($table) . '` ADD `' . bqSQL($name) . '` ' . $definition)) {
                return false;
            }
        }

        if (!Db::getInstance()->execute(
            'ALTER TABLE `' . bqSQL($table) . '` MODIFY `id_order_return` INT UNSIGNED NULL DEFAULT NULL'
        )) {
            return false;
        }

        if (!$this->databaseIndexExists($table, 'idx_cci_withdrawal_public_token')
            && !Db::getInstance()->execute(
                'ALTER TABLE `' . bqSQL($table) . '` ADD KEY `idx_cci_withdrawal_public_token` (`public_token_hash`)'
            )) {
            return false;
        }

        return Db::getInstance()->execute(
            'UPDATE `' . bqSQL($table) . '` SET '
            . '`processing_status` = IF(`id_order_return` IS NULL, \'manual_review\', \'rma_created\'), '
            . '`date_upd` = `date_add` '
            . 'WHERE `date_upd` <= \'1970-01-01 00:00:01\''
        );
    }

    private function databaseColumnExists(string $table, string $column): bool
    {
        return (bool) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `INFORMATION_SCHEMA`.`COLUMNS`'
            . ' WHERE `TABLE_SCHEMA` = DATABASE()'
            . ' AND `TABLE_NAME` = \'' . pSQL($table) . '\''
            . ' AND `COLUMN_NAME` = \'' . pSQL($column) . '\''
        );
    }

    private function databaseIndexExists(string $table, string $index): bool
    {
        return (bool) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `INFORMATION_SCHEMA`.`STATISTICS`'
            . ' WHERE `TABLE_SCHEMA` = DATABASE()'
            . ' AND `TABLE_NAME` = \'' . pSQL($table) . '\''
            . ' AND `INDEX_NAME` = \'' . pSQL($index) . '\''
        );
    }

    private function uninstallDatabase(): bool
    {
        return Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'cci_withdrawal_lookup_attempt`')
            && Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'cci_withdrawal_submission`');
    }

    private function uninstallAdminTab(): bool
    {
        foreach ([self::ADMIN_SETTINGS_TAB_CLASS, self::ADMIN_SUBMISSIONS_TAB_CLASS, self::ADMIN_TAB_CLASS] as $className) {
            $tabId = (int) Tab::getIdFromClassName($className);
            if ($tabId > 0 && !(bool) (new Tab($tabId))->delete()) {
                return false;
            }
        }

        $parentId = (int) Tab::getIdFromClassName(self::ADMIN_PARENT_TAB_CLASS);
        if ($parentId <= 0) {
            return true;
        }

        $remainingChildren = (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'tab` WHERE `id_parent` = ' . $parentId
        );

        return $remainingChildren > 0 || (bool) (new Tab($parentId))->delete();
    }

    private function getAdminPresentation(): CciWithdrawalAdminPresentation
    {
        return $this->adminPresentation ??= new CciWithdrawalAdminPresentation($this, $this->context, self::ADMIN_SUBMISSIONS_TAB_CLASS);
    }
}
