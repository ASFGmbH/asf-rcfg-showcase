<?php
/**
 * Plugin Name: ASF RCFG Showcase
 * Description: Verknüpft WooCommerce-Showcase-Produkte mit 3D-Trauringkonfigurator-Presets und erzeugt Arbeitskopien zur Weiterkonfiguration.
 * Version: 0.1.0
 * Author: ASF
 * Text Domain: asf-rcfg-showcase
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('ASF_RCFG_SHOWCASE_VERSION', '0.1.0');
define('ASF_RCFG_SHOWCASE_FILE', __FILE__);
define('ASF_RCFG_SHOWCASE_DIR', plugin_dir_path(__FILE__));
define('ASF_RCFG_SHOWCASE_URL', plugin_dir_url(__FILE__));

add_action('plugins_loaded', static function (): void {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', static function (): void {
            echo '<div class="notice notice-error"><p><strong>ASF RCFG Showcase:</strong> WooCommerce ist nicht aktiv.</p></div>';
        });
        return;
    }

    if (!defined('ONE_RCFG_REST_NAMESPACE')) {
        add_action('admin_notices', static function (): void {
            echo '<div class="notice notice-warning"><p><strong>ASF RCFG Showcase:</strong> Der 3D-Trauringkonfigurator wurde nicht erkannt.</p></div>';
        });
        return;
    }
}, 20);