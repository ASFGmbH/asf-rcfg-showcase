<?php

declare(strict_types=1);

namespace Asf\RcfgShowcase\Infrastructure;

if (!defined('ABSPATH')) {
    exit;
}

final class TableNames
{
    public static function showcaseCopy(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'asf_rcfg_showcase_copy';
    }

    public static function rcfgPreset(): string
    {
        if (defined('ONE_RCFG_TABLE_PRESET')) {
            return ONE_RCFG_TABLE_PRESET;
        }

        global $wpdb;

        return $wpdb->prefix . 'rcfg_preset';
    }
}