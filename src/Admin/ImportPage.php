<?php

declare(strict_types=1);

namespace Asf\RcfgShowcase\Admin;

use Asf\RcfgShowcase\Service\Import\ImportClient;
use Throwable;

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
     * Festverdrahtete Import-URL.
     *
     * Diese URL muss später auf den API-Endpunkt im RingPreisrechner-V2-Dashboard zeigen.
     * Das Showcase-Plugin stellt diesen Endpoint nicht selbst bereit, sondern konsumiert ihn nur.
     */
    private const IMPORT_URL = 'https://dashboard.asf.gmbh/api/rcfg-showcase-products';

    public function __construct(
        private readonly ?ImportClient $importClient = null
    ) {
    }

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

        echo '<p>Diese Seite importiert Showcase-Artikel aus dem RingPreisrechner-V2-Dashboard.</p>';

        if ($result !== null) {
            $noticeClass = $result['type'] === 'error' ? 'notice notice-error' : 'notice notice-success';

            echo '<div class="' . esc_attr($noticeClass) . ' is-dismissible">';
            echo '<p>' . wp_kses_post($result['message']) . '</p>';
            echo '</div>';
        }

        echo '<div class="card" style="max-width: 900px;">';
        echo '<h2>Importquelle</h2>';
        echo '<p>Die Import-URL ist aktuell fest im Plugin hinterlegt:</p>';
        echo '<code>' . esc_html(self::IMPORT_URL) . '</code>';
        echo '<p style="margin-top: 12px;">In diesem Schritt wird die JSON-Antwort nur abgerufen und validiert. Das WooCommerce-Mapping folgt im nächsten Commit.</p>';
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

        echo '<h2>Erwartetes PIM-/Import-JSON-Format</h2>';
        echo '<pre style="background: #fff; padding: 12px; border: 1px solid #ccd0d4; overflow:auto; max-width: 900px;">';
        echo esc_html($this->getExampleJson());
        echo '</pre>';

        echo '</div>';
    }

    public function handleImportRequest(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Du hast keine Berechtigung für diesen Import.', 'asf-rcfg-showcase'));
        }

        check_admin_referer(self::NONCE_ACTION);

        try {
            $result = $this->getImportClient()->fetchAndValidate();

            $this->storeResultMessage(
                'success',
                $this->formatSuccessMessage($result)
            );
        } catch (Throwable $exception) {
            $this->storeResultMessage(
                'error',
                'Import fehlgeschlagen: ' . esc_html($exception->getMessage())
            );
        }

        wp_safe_redirect($this->getPageUrl());
        exit;
    }

    private function getImportClient(): ImportClient
    {
        return $this->importClient ?? new ImportClient(self::IMPORT_URL);
    }

    /**
     * @param array{
     *     success: bool,
     *     generated_at: string|null,
     *     count: int,
     *     items: array<int, array<string, mixed>>,
     *     warnings: array<int, string>
     * } $result
     */
    private function formatSuccessMessage(array $result): string
    {
        $message = sprintf(
            'Import-JSON erfolgreich geladen und validiert. Gültige Artikel: <strong>%d</strong>.',
            (int) $result['count']
        );

        if (!empty($result['generated_at'])) {
            $message .= '<br>Generiert am: <code>' . esc_html((string) $result['generated_at']) . '</code>';
        }

        if (!empty($result['items'][0]['sku'])) {
            $message .= '<br>Erster gültiger Artikel: <code>' . esc_html((string) $result['items'][0]['sku']) . '</code>';
        }

        if (!empty($result['warnings'])) {
            $message .= '<br><br><strong>Warnungen:</strong><ul>';

            foreach (array_slice($result['warnings'], 0, 10) as $warning) {
                $message .= '<li>' . esc_html((string) $warning) . '</li>';
            }

            if (count($result['warnings']) > 10) {
                $message .= '<li>Weitere Warnungen wurden aus Platzgründen ausgeblendet.</li>';
            }

            $message .= '</ul>';
        }

        return $message;
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

    private function getExampleJson(): string
    {
        return <<<'JSON'
{
  "success": true,
  "generated_at": "2026-05-05T12:00:00+02:00",
  "items": [
    {
      "oo_id": 23292,
      "customfield_asf_model": "TR001",
      "customfield_asf_type": "Trauring",
      "category": "Eheringe",
      "manufacturer": "ASF-Trauringe",
      "customfield_asf_material": "gelbgold-weissgold",
      "customfield_asf_alloy": "333er",
      "customfield_asf_surface": "po;lm;sam;qm;scm;em;xm;hpo;hma;",
      "customfield_asf_default_surface": "lm",
      "customfield_asf_stones": "1x0.03",
      "customfield_asf_default_stone": "Diamant",
      "customfield_asf_stone_colors": "weiß;apple-green;baby-pink;canary-yellow;cognac-brown;orange;schwarz;sky-blue;",
      "customfield_asf_mount": ",Einreiber,",
      "customfield_asf_gap": "Konvex Fuge",
      "customfield_asf_gap_width": 1,
      "profil": 3,
      "customfield_asf_minManWidth": "3",
      "customfield_asf_maxManWidth": "15",
      "customfield_asf_minWomanWidth": "3",
      "customfield_asf_maxWomanWidth": "15",
      "customfield_asf_default_width": "5",
      "customfield_asf_minWomanStrength": "1.2",
      "customfield_asf_maxWomanStrength": "4",
      "customfield_asf_minManStrength": "1.4",
      "customfield_asf_maxManStrength": "4",
      "customfield_asf_default_strength": "1.4",
      "customfield_asf_noEngraving": 0,
      "template_id": "YITG-6KPT",
      "desc": "Diese schicken Eheringe aus {customfield_asf_material} wirken an jeder Hand schön. Das Modell {customfield_asf_model} wird aus einer {customfield_asf_alloy} Legierung gefertigt."
    }
  ]
}
JSON;
    }
}