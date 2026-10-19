<?php

/** Back-office presentation; installation, hooks and business rules stay in the module. */
if (!defined('_PS_VERSION_')) {
    exit;
}

final class CciWithdrawalAdminPresentation
{
    public function __construct(
        private readonly Module $module,
        private readonly Context $context,
        private readonly string $submissionsTabClass,
    ) {
    }

    public function renderHeroSection(): string
    {
        $heroImagePath = $this->module->getPathUri() . 'views/img/cci-hello.png';
        $heroStyle = '';

        if (is_file($this->module->getLocalPath() . 'views/img/cci-hello.png')) {
            $heroStyle = ' style="--cci-hero-image:url(\'' . $this->escapeHtml($heroImagePath) . '\')"';
        }

        $requestsUrl = $this->context->link->getAdminLink($this->submissionsTabClass);
        $formUrl = $this->module->getWithdrawalLink();

        return '<section class="cci-card cci-hero"' . $heroStyle . '>'
            . '<div class="cci-card-inner"><div class="cci-hero-content">'
            . '<div class="cci-title-row"><span class="cci-title">' . $this->escapeHtml($this->module->displayName) . '</span>'
            . '<span class="cci-version">v' . $this->escapeHtml($this->module->version) . '</span></div>'
            . '<p class="cci-subtitle">'
            . $this->escapeHtml($this->module->getTranslator()->trans(
                'Record withdrawal statements reliably, then use native PrestaShop returns whenever the order can be represented there.',
                [],
                'Modules.Cciwithdrawal.Admin'
            ))
            . '</p>'
            . '<div class="cci-hero-actions">'
            . '<a class="cci-btn-primary" href="' . $this->escapeHtml($requestsUrl) . '">'
            . '<i class="material-icons" aria-hidden="true">assignment_return</i>'
            . $this->escapeHtml($this->module->getTranslator()->trans('Open withdrawal requests', [], 'Modules.Cciwithdrawal.Admin'))
            . '</a>'
            . '<a class="cci-btn-secondary" href="' . $this->escapeHtml($formUrl) . '" target="_blank" rel="noopener noreferrer">'
            . $this->escapeHtml($this->module->getTranslator()->trans('Open public form', [], 'Modules.Cciwithdrawal.Admin'))
            . '</a>'
            . '</div></div></div></section>';
    }

    public function renderBrandPanel(): string
    {
        return '<aside class="cci-brand-panel"><div class="cci-brand-card">'
            . '<div class="cci-brand-title">' . $this->escapeHtml($this->module->getTranslator()->trans('About us', [], 'Modules.Cciwithdrawal.Admin')) . '</div>'
            . '<div class="cci-brand-logos">'
            . '<img src="' . $this->module->getPathUri() . 'views/img/cool-cat-ideas-logo.svg" alt="Cool Cat Ideas">'
            . '<img src="' . $this->module->getPathUri() . 'views/img/teastudio-logo.png" alt="teastudio">'
            . '</div>'
            . '<p class="cci-brand-text">'
            . $this->escapeHtml($this->module->getTranslator()->trans(
                'This module is only a fraction of what we do. We deliver custom IT solutions that grow with your business. We ensure complete code optimization and security.',
                [],
                'Modules.Cciwithdrawal.Admin'
            ))
            . '</p><div class="cci-brand-heading">'
            . $this->escapeHtml($this->module->getTranslator()->trans('We implement and develop:', [], 'Modules.Cciwithdrawal.Admin'))
            . '</div><ul class="cci-check-list">'
            . '<li><span>' . $this->renderBrandFeature('Fast e-commerce on %highlight% (from implementations to custom modules)', 'PrestaShop') . '</span></li>'
            . '<li><span>' . $this->renderBrandFeature('Flexible and modern %highlight% websites', 'WordPress') . '</span></li>'
            . '<li><span>' . $this->renderBrandFeature('Scalable web applications in %highlight%', 'Next.js') . '</span></li>'
            . '<li><span>' . $this->renderBrandFeature('Process automation and %highlight%', 'API integrations') . '</span></li>'
            . '</ul><a class="cci-btn-primary" href="https://coolcatideas.com" target="_blank" rel="noopener noreferrer">'
            . $this->escapeHtml($this->module->getTranslator()->trans('See our offer', [], 'Modules.Cciwithdrawal.Admin'))
            . '</a></div></aside>';
    }

    private function renderBrandFeature(string $source, string $highlight): string
    {
        $translated = $this->escapeHtml($this->module->getTranslator()->trans($source, [], 'Modules.Cciwithdrawal.Admin'));
        $translatedHighlight = $this->escapeHtml($this->module->getTranslator()->trans($highlight, [], 'Modules.Cciwithdrawal.Admin'));

        return str_replace('%highlight%', '<strong>' . $translatedHighlight . '</strong>', $translated);
    }

    public function renderNativeReturnsNotice(): string
    {
        $nativeReturnsUrl = $this->context->link->getAdminLink('AdminReturn');

        return '<div class="cci-native-settings-note">'
            . '<div class="cci-native-settings-copy">'
            . '<strong>' . $this->escapeHtml($this->module->getTranslator()->trans('Built on PrestaShop returns', [], 'Modules.Cciwithdrawal.Admin')) . '</strong>'
            . '<span>'
            . $this->escapeHtml($this->module->getTranslator()->trans(
                'The withdrawal statement is stored independently. Native return activation, return period and RMA prefix only control the optional RMA adapter in PrestaShop settings.',
                [],
                'Modules.Cciwithdrawal.Admin'
            ))
            . '</span></div>'
            . '<a class="cci-btn-secondary" href="' . $this->escapeHtml($nativeReturnsUrl) . '">'
            . '<i class="material-icons" aria-hidden="true">settings</i>'
            . $this->escapeHtml($this->module->getTranslator()->trans('Open PrestaShop return settings', [], 'Modules.Cciwithdrawal.Admin'))
            . '</a>'
            . '</div>';
    }

    private function escapeHtml($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
