<?php

declare(strict_types=1);

namespace Asf\RcfgShowcase\Admin;

if (!defined('ABSPATH')) {
    exit;
}

final class ProductFields
{
    public const META_SHOWCASE_ENABLED = '_asf_rcfg_showcase_enabled';
    public const META_TEMPLATE_ID = '_asf_rcfg_template_id';

    public function register(): void
    {
        add_action('woocommerce_product_options_general_product_data', [$this, 'renderFields']);
        add_action('woocommerce_admin_process_product_object', [$this, 'saveFields']);
    }

    public function renderFields(): void
    {
        if (!function_exists('woocommerce_wp_checkbox') || !function_exists('woocommerce_wp_text_input')) {
            return;
        }

        echo '<div class="options_group">';

        woocommerce_wp_checkbox([
            'id' => self::META_SHOWCASE_ENABLED,
            'label' => 'RCFG Showcase aktiv',
            'description' => 'Dieses Produkt als sichtbaren Showcase-/Bestandsartikel für den 3D-Trauringkonfigurator verwenden.',
            'desc_tip' => false,
            'cbvalue' => 'yes',
        ]);

        woocommerce_wp_text_input([
            'id' => self::META_TEMPLATE_ID,
            'label' => 'RCFG Template-ID',
            'placeholder' => 'z. B. 0000-0000 oder C006-DEMO',
            'description' => 'Preset-ID aus wp_rcfg_preset. Diese ID dient als Blaupause und wird später beim Klick auf „Weiter konfigurieren“ in eine neue Arbeitskopie kopiert.',
            'desc_tip' => true,
            'type' => 'text',
            'custom_attributes' => [
                'maxlength' => '32',
                'autocomplete' => 'off',
            ],
        ]);

        echo '</div>';
    }

    public function saveFields(\WC_Product $product): void
    {
        $showcaseEnabled = isset($_POST[self::META_SHOWCASE_ENABLED]) ? 'yes' : 'no';
        $product->update_meta_data(self::META_SHOWCASE_ENABLED, $showcaseEnabled);

        $templateId = isset($_POST[self::META_TEMPLATE_ID])
            ? sanitize_text_field(wp_unslash((string) $_POST[self::META_TEMPLATE_ID]))
            : '';

        $templateId = strtoupper(trim($templateId));

        if ($templateId === '') {
            $product->delete_meta_data(self::META_TEMPLATE_ID);
            return;
        }

        if (!$this->isValidTemplateId($templateId)) {
            if (class_exists('\WC_Admin_Meta_Boxes')) {
                \WC_Admin_Meta_Boxes::add_error(
                    'Die RCFG Template-ID wurde nicht gespeichert. Erlaubt sind z. B. 0000-0000, C006-DEMO oder ABCD-EFGH-1.'
                );
            }

            return;
        }

        $product->update_meta_data(self::META_TEMPLATE_ID, $templateId);
    }

    private function isValidTemplateId(string $templateId): bool
    {
        return (bool) preg_match('/^[A-Z0-9]{4}-[A-Z0-9]{4}(?:-\d+)?$/', $templateId);
    }
}