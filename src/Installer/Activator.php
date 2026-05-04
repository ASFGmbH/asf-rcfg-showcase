<?php

declare(strict_types=1);

namespace Asf\RcfgShowcase\Installer;

use Asf\RcfgShowcase\Infrastructure\TableNames;

if (!defined('ABSPATH')) {
    exit;
}

final class Activator
{
    public static function activate(): void
    {
        self::createCopyTable();
        self::createRingImageNoCacheHtaccess();

        update_option('asf_rcfg_showcase_db_version', ASF_RCFG_SHOWCASE_DB_VERSION, false);
    }

    private static function createCopyTable(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $tableName = TableNames::showcaseCopy();
        $charsetCollate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$tableName} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            copy_id varchar(32) NOT NULL,
            template_id varchar(32) NOT NULL,
            product_id bigint(20) unsigned NOT NULL DEFAULT 0,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            session_id varchar(128) NULL DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY copy_id (copy_id),
            KEY template_id (template_id),
            KEY product_id (product_id),
            KEY user_id (user_id),
            KEY session_id (session_id)
        ) {$charsetCollate};";

        dbDelta($sql);
    }

    private static function createRingImageNoCacheHtaccess(): void
    {
        $uploadDir = wp_upload_dir();

        if (!empty($uploadDir['error'])) {
            return;
        }

        $targetDir = trailingslashit((string) $uploadDir['basedir']) . 'ringkonfiguration/';

        if (!wp_mkdir_p($targetDir)) {
            return;
        }

        $htaccessPath = $targetDir . '.htaccess';

        $content = <<<HTACCESS
# ASF RCFG Showcase
# Generated configurator preview images are overwritten under the same filename.
# Force browsers/proxies to revalidate them.

<IfModule mod_headers.c>
    <FilesMatch "\\.(png|jpg|jpeg|webp)$">
        Header set Cache-Control "no-store, no-cache, must-revalidate, max-age=0"
        Header set Pragma "no-cache"
        Header set Expires "0"
    </FilesMatch>
</IfModule>

<IfModule mod_expires.c>
    ExpiresActive Off
</IfModule>

HTACCESS;

        if (!file_exists($htaccessPath)) {
            file_put_contents($htaccessPath, $content);
            return;
        }

        $existing = (string) file_get_contents($htaccessPath);

        if (!str_contains($existing, 'ASF RCFG Showcase')) {
            file_put_contents($htaccessPath, "\n" . $content, FILE_APPEND);
        }
    }
}