<?php

declare(strict_types=1);

namespace Asf\RcfgShowcase\Repository;

if (!defined('ABSPATH')) {
    exit;
}

final class RcfgSettingsRepository
{
    public function getString(string $name, string $default = ''): string
    {
        global $wpdb;

        $tableName = $wpdb->prefix . 'rcfg_settings';

        $exists = $wpdb->get_var(
            $wpdb->prepare('SHOW TABLES LIKE %s', $tableName)
        );

        if ($exists !== $tableName) {
            return $default;
        }

        $value = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT value FROM {$tableName} WHERE name = %s LIMIT 1",
                $name
            )
        );

        if (!is_scalar($value)) {
            return $default;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : $default;
    }

    public function getFloat(string $name, float $default = 0.0): float
    {
        $value = str_replace(',', '.', $this->getString($name, (string) $default));

        return is_numeric($value) ? (float) $value : $default;
    }

    public function getInt(string $name, int $default = 0): int
    {
        return (int) round($this->getFloat($name, (float) $default));
    }

    public function isEnabled(string $name): bool
    {
        return $this->getString($name, '0') === '1';
    }
}