<?php

declare(strict_types=1);

namespace Asf\RcfgShowcase\Admin;

if (!defined('ABSPATH')) {
    exit;
}

final class ImportPage
{
    private const MENU_SLUG = 'asf-rcfg-showcase-import';
    private const ACTION = 'asf_rcfg_import_showcase_products';
    private const NONCE_ACTION = 'asf_rcfg_import_showcase_products';
    private const RESULT_TRANSIENT_PREFIX = 'asf_rcfg_showcase_import_result_';

    /**
     * Platzhalter für die festverdrahtete Import-URL.
     *
     * Diese URL wird im nächsten Commit vom ImportClient verwendet.
     * Sobald der API-Endpunkt im RingPreisrechner-V2-Dashboard final steht,
     * wird nur dieser Wert angepasst.
     */
    private const IMPORT_URL = 'https://dashboard.asf.gmbh/api/rcfg-showcase-products';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_post_' . self::ACTION, [$this, 'handleImportRequest']);
    }

    public function registerMenu(): void
    {
        add_submenu_page(
            'woocommerce',
            'ASF RCFG Import',
            'ASF RCFG Import',
            'manage_woocommerce',
            self::MENU_SLUG,
            [$this, 'render']
        );
    }

    public function render(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Du hast keine Berechtigung für diese Seite.', 'asf-rcfg-showcase'));
        }

        $result = $this->consumeResultMessage();

        echo '<div class="wrap">';
        echo '<h1>ASF RCFG Showcase Import</h1>';

        echo '<p>Diese Seite bereitet den Import von Showcase-Artikeln aus dem RingPreisrechner-V2-Dashboard vor.</p>';

        if ($result !== null) {
            $noticeClass = $result['type'] === 'error' ? 'notice notice-error' : 'notice notice-success';

            echo '<div class="' . esc_attr($noticeClass) . ' is-dismissible">';
            echo '<p>' . esc_html($result['message']) . '</p>';
            echo '</div>';
        }

        echo '<div class="card" style="max-width: 900px;">';
        echo '<h2>Importquelle</h2>';
        echo '<p>Die Import-URL ist aktuell fest im Plugin hinterlegt:</p>';
        echo '<code>' . esc_html(self::IMPORT_URL) . '</code>';
        echo '<p style="margin-top: 12px;">Der eigentliche API-Client und das WooCommerce-Mapping werden in den nächsten Commits ergänzt.</p>';
        echo '</div>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top: 20px;">';
        echo '<input type="hidden" name="action" value="' . esc_attr(self::ACTION) . '">';
        wp_nonce_field(self::NONCE_ACTION);

        submit_button(
            'Artikel importieren',
            'primary',
            'submit',
            false
        );

        echo '</form>';

        echo '<hr>';

        echo '<h2>Geplanter Importablauf</h2>';
        echo '<ol>';
        echo '<li>API-URL im RingPreisrechner-V2-Dashboard aufrufen.</li>';
        echo '<li>JSON-Antwort validieren.</li>';
        echo '<li>Artikel anhand SKU oder externer ID in WooCommerce suchen.</li>';
        echo '<li>Nicht vorhandene Artikel erstellen.</li>';
        echo '<li>Vorhandene Artikel aktualisieren.</li>';
        echo '<li>Showcase-Meta setzen: aktiv + RCFG Template-ID.</li>';
        echo '<li>Artikel veröffentlichen.</li>';
        echo '<li>Importbericht ausgeben.</li>';
        echo '</ol>';

        echo '</div>';
    }

    public function handleImportRequest(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Du hast keine Berechtigung für diesen Import.', 'asf-rcfg-showcase'));
        }

        check_admin_referer(self::NONCE_ACTION);

        $this->storeResultMessage(
            'success',
            'Import-Button wurde erfolgreich ausgelöst. Der externe API-Client wird im nächsten Commit angebunden.'
        );

        wp_safe_redirect($this->getPageUrl());
        exit;
    }

    private function getPageUrl(): string
    {
        return add_query_arg(
            [
                'page' => self::MENU_SLUG,
            ],
            admin_url('admin.php')
        );
    }

    private function getResultTransientKey(): string
    {
        return self::RESULT_TRANSIENT_PREFIX . get_current_user_id();
    }

    private function storeResultMessage(string $type, string $message): void
    {
        set_transient(
            $this->getResultTransientKey(),
            [
                'type' => $type,
                'message' => $message,
            ],
            60
        );
    }

    /**
     * @return array{type:string,message:string}|null
     */
    private function consumeResultMessage(): ?array
    {
        $key = $this->getResultTransientKey();
        $result = get_transient($key);

        delete_transient($key);

        if (!is_array($result)) {
            return null;
        }

        $type = isset($result['type']) && $result['type'] === 'error' ? 'error' : 'success';
        $message = isset($result['message']) ? (string) $result['message'] : '';

        if ($message === '') {
            return null;
        }

        return [
            'type' => $type,
            'message' => $message,
        ];
    }
}