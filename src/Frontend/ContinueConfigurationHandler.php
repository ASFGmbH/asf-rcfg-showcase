<?php

declare(strict_types=1);

namespace Asf\RcfgShowcase\Frontend;

use Asf\RcfgShowcase\Admin\ProductFields;
use Asf\RcfgShowcase\Service\PresetCopyService;
use Throwable;

if (!defined('ABSPATH')) {
    exit;
}

final class ContinueConfigurationHandler
{
    private const ACTION = 'asf_rcfg_continue_config';

    public function __construct(
        private readonly PresetCopyService $presetCopyService = new PresetCopyService()
    ) {
    }

    public function register(): void
    {
        add_action('woocommerce_after_add_to_cart_button', [$this, 'renderButton']);

        add_action('admin_post_' . self::ACTION, [$this, 'handle']);
        add_action('admin_post_nopriv_' . self::ACTION, [$this, 'handleNotLoggedIn']);
    }

    public function renderButton(): void
    {
        global $product;

        if (!$product instanceof \WC_Product) {
            return;
        }

        if (!$this->isShowcaseProduct($product)) {
            return;
        }

        $url = $this->buildContinueUrl($product);

        echo '<div class="asf-rcfg-showcase-action" style="margin-top: 12px;">';
        echo '<a class="button alt asf-rcfg-showcase-button" href="' . esc_url($url) . '">';
        echo esc_html__('Weiter konfigurieren', 'asf-rcfg-showcase');
        echo '</a>';
        echo '</div>';
    }

    public function handle(): void
    {
        $productId = isset($_GET['product_id']) ? absint($_GET['product_id']) : 0;

        if ($productId <= 0) {
            $this->failAndRedirect(0, 'Es wurde kein gültiges Produkt übergeben.');
        }

        if (!$this->isValidNonce($productId)) {
            $this->failAndRedirect($productId, 'Die Anfrage ist abgelaufen oder ungültig. Bitte versuche es erneut.');
        }

        if (!is_user_logged_in()) {
            $this->redirectToLogin($productId);
        }

        $product = wc_get_product($productId);

        if (!$product instanceof \WC_Product) {
            $this->failAndRedirect(0, 'Das Produkt wurde nicht gefunden.');
        }

        if (!$this->isShowcaseProduct($product)) {
            $this->failAndRedirect($productId, 'Dieses Produkt ist nicht als RCFG Showcase-Produkt aktiviert.');
        }

        $templateId = $this->getTemplateId($product);

        try {
            $copyId = $this->presetCopyService->createWorkingCopy(
                templateId: $templateId,
                productId: $productId,
                userId: get_current_user_id(),
                sessionId: $this->getSessionId()
            );
        } catch (Throwable $exception) {
            $this->failAndRedirect($productId, $exception->getMessage());
        }

        $redirectUrl = add_query_arg(
            [
                'id' => $copyId,
                'showcase_product_id' => $productId,
            ],
            $this->getConfiguratorUrl()
        );

        wp_safe_redirect($redirectUrl);
        exit;
    }

    public function handleNotLoggedIn(): void
    {
        $productId = isset($_GET['product_id']) ? absint($_GET['product_id']) : 0;

        $this->redirectToLogin($productId);
    }

    private function isShowcaseProduct(\WC_Product $product): bool
    {
        $enabled = (string) $product->get_meta(ProductFields::META_SHOWCASE_ENABLED, true);
        $templateId = $this->getTemplateId($product);

        return $enabled === 'yes' && $templateId !== '';
    }

    private function getTemplateId(\WC_Product $product): string
    {
        return strtoupper(trim((string) $product->get_meta(ProductFields::META_TEMPLATE_ID, true)));
    }

    private function buildContinueUrl(\WC_Product $product): string
    {
        $productId = $product->get_id();

        $url = add_query_arg(
            [
                'action' => self::ACTION,
                'product_id' => $productId,
            ],
            admin_url('admin-post.php')
        );

        return wp_nonce_url($url, $this->getNonceAction($productId));
    }

    private function isValidNonce(int $productId): bool
    {
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash((string) $_GET['_wpnonce'])) : '';

        return $nonce !== '' && wp_verify_nonce($nonce, $this->getNonceAction($productId)) !== false;
    }

    private function getNonceAction(int $productId): string
    {
        return self::ACTION . '_' . $productId;
    }

    private function getConfiguratorUrl(): string
    {
        /**
         * Ermöglicht später eine andere Konfigurator-Seite ohne Codeänderung.
         *
         * Beispiel:
         * add_filter('asf_rcfg_showcase_configurator_url', fn() => home_url('/ringkonfiguration/'));
         */
        return (string) apply_filters(
            'asf_rcfg_showcase_configurator_url',
            home_url('/ringkonfiguration/')
        );
    }

    private function getSessionId(): ?string
    {
        if (!function_exists('WC') || !WC()->session) {
            return null;
        }

        $customerId = WC()->session->get_customer_id();

        return $customerId ? (string) $customerId : null;
    }

    private function redirectToLogin(int $productId): void
    {
        $redirectAfterLogin = $productId > 0 ? get_permalink($productId) : home_url('/');

        if ($redirectAfterLogin === false) {
            $redirectAfterLogin = home_url('/');
        }

        $this->addNotice('Bitte melde dich an, um die Konfiguration weiterzubearbeiten.', 'notice');

        wp_safe_redirect(wp_login_url($redirectAfterLogin));
        exit;
    }

    private function failAndRedirect(int $productId, string $message): void
    {
        $this->addNotice($message, 'error');

        $redirectUrl = $productId > 0 ? get_permalink($productId) : wc_get_page_permalink('shop');

        if ($redirectUrl === false || $redirectUrl === '') {
            $redirectUrl = home_url('/');
        }

        wp_safe_redirect($redirectUrl);
        exit;
    }

    private function addNotice(string $message, string $type = 'error'): void
    {
        if (function_exists('wc_add_notice')) {
            wc_add_notice($message, $type);
            return;
        }

        if (function_exists('wp_die')) {
            wp_die(esc_html($message));
        }
    }
}