<?php

declare(strict_types=1);

namespace Asf\RcfgShowcase\Repository;

use Asf\RcfgShowcase\Infrastructure\TableNames;
use RuntimeException;

if (!defined('ABSPATH')) {
    exit;
}

final class RcfgPresetRepository
{
    public function findPresetById(string $presetId): ?array
    {
        global $wpdb;

        $tableName = TableNames::rcfgPreset();

        $sql = $wpdb->prepare(
            "SELECT id, preset_0, preset_1, img, info, price, order_id, user_id
             FROM {$tableName}
             WHERE id = %s
             LIMIT 1",
            $presetId
        );

        $row = $wpdb->get_row($sql, ARRAY_A);

        return is_array($row) ? $row : null;
    }

    public function insertPresetCopy(string $copyId, array $templatePreset, int $userId): void
    {
        global $wpdb;

        $tableName = TableNames::rcfgPreset();

        $inserted = $wpdb->insert(
            $tableName,
            [
                'id' => $copyId,
                'preset_0' => $templatePreset['preset_0'] ?? null,
                'preset_1' => $templatePreset['preset_1'] ?? null,
                'img' => $templatePreset['img'] ?? null,
                'info' => $templatePreset['info'] ?? null,
                'price' => $templatePreset['price'] ?? 0,
                'order_id' => 0,
                'user_id' => $userId,
            ],
            [
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%f',
                '%d',
                '%d',
            ]
        );

        if ($inserted === false) {
            throw new RuntimeException('Die RCFG-Arbeitskopie konnte nicht in wp_rcfg_preset angelegt werden.');
        }
    }

    public function presetExists(string $presetId): bool
    {
        global $wpdb;

        $tableName = TableNames::rcfgPreset();

        $sql = $wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$tableName}
             WHERE id = %s",
            $presetId
        );

        return (int) $wpdb->get_var($sql) > 0;
    }
}