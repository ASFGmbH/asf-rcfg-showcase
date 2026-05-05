<?php
/**
 * Plugin Name: ASF RCFG Showcase
 * Description: Verknüpft WooCommerce-Showcase-Produkte mit 3D-Trauringkonfigurator-Presets und erzeugt Arbeitskopien zur Weiterkonfiguration.
 * Version: 0.2.0
 * Author: ASF
 * Text Domain: asf-rcfg-showcase
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('ASF_RCFG_SHOWCASE_VERSION', '0.2.0');
define('ASF_RCFG_SHOWCASE_DB_VERSION', '1');
define('ASF_RCFG_SHOWCASE_FILE', __FILE__);
define('ASF_RCFG_SHOWCASE_DIR', plugin_dir_path(__FILE__));
define('ASF_RCFG_SHOWCASE_URL', plugin_dir_url(__FILE__));

require_once ASF_RCFG_SHOWCASE_DIR . 'src/Installer/Activator.php';
require_once ASF_RCFG_SHOWCASE_DIR . 'src/Infrastructure/TableNames.php';

require_once ASF_RCFG_SHOWCASE_DIR . 'src/Domain/RingProductType.php';
require_once ASF_RCFG_SHOWCASE_DIR . 'src/Domain/RingProductCapabilities.php';

require_once ASF_RCFG_SHOWCASE_DIR . 'src/Repository/RcfgPresetRepository.php';
require_once ASF_RCFG_SHOWCASE_DIR . 'src/Repository/RcfgSettingsRepository.php';
require_once ASF_RCFG_SHOWCASE_DIR . 'src/Repository/ShowcaseCopyRepository.php';

require_once ASF_RCFG_SHOWCASE_DIR . 'src/Service/Pricing/RcfgDefaultPresetRepository.php';
require_once ASF_RCFG_SHOWCASE_DIR . 'src/Service/Pricing/PimItemPresetFactory.php';
require_once ASF_RCFG_SHOWCASE_DIR . 'src/Service/Pricing/RingPriceCalculatorAdapter.php';

require_once ASF_RCFG_SHOWCASE_DIR . 'src/Service/Import/CdnImageUrlBuilder.php';
require_once ASF_RCFG_SHOWCASE_DIR . 'src/Service/Import/PimRingProductNormalizer.php';
require_once ASF_RCFG_SHOWCASE_DIR . 'src/Service/Import/ImportClient.php';

require_once ASF_RCFG_SHOWCASE_DIR . 'src/Admin/ProductFields.php';
require_once ASF_RCFG_SHOWCASE_DIR . 'src/Admin/ImportPage.php';

require_once ASF_RCFG_SHOWCASE_DIR . 'src/Service/PresetCopyService.php';

require_once ASF_RCFG_SHOWCASE_DIR . 'src/Frontend/ContinueConfigurationHandler.php';
require_once ASF_RCFG_SHOWCASE_DIR . 'src/Frontend/ConfiguratorAssets.php';

require_once ASF_RCFG_SHOWCASE_DIR . 'src/Integration/RcfgRestOverwriteInterceptor.php';

register_activation_hook(ASF_RCFG_SHOWCASE_FILE, static function (): void {
    \Asf\RcfgShowcase\Installer\Activator::activate();
});

add_action('plugins_loaded', static function (): void {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', static function (): void {
            echo '<div class="notice notice-error"><p><strong>ASF RCFG Showcase:</strong> WooCommerce ist nicht aktiv.</p></div>';
        });

        return;
    }

    if (!defined('ONE_RCFG_REST_NAMESPACE')) {
        add_action('admin_notices', static function (): void {
            echo '<div class="notice notice-warning"><p><strong>ASF RCFG Showcase:</strong> Der 3D-Trauringkonfigurator wurde nicht erkannt. Die Showcase-Felder sind sichtbar, die Konfigurator-Funktionen stehen aber erst nach Aktivierung des 3D-Plugins zur Verfügung.</p></div>';
        });
    }

    if (is_admin()) {
        (new \Asf\RcfgShowcase\Admin\ProductFields())->register();
        (new \Asf\RcfgShowcase\Admin\ImportPage())->register();
    }

    (new \Asf\RcfgShowcase\Frontend\ContinueConfigurationHandler())->register();
    (new \Asf\RcfgShowcase\Frontend\ConfiguratorAssets())->register();
    (new \Asf\RcfgShowcase\Integration\RcfgRestOverwriteInterceptor())->register();
}, 20);