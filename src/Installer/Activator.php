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
}