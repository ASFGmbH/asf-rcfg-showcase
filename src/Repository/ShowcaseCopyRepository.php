<?php

declare(strict_types=1);

namespace Asf\RcfgShowcase\Repository;

use Asf\RcfgShowcase\Infrastructure\TableNames;
use RuntimeException;

if (!defined('ABSPATH')) {
    exit;
}

final class ShowcaseCopyRepository
{
    public function insertCopyMapping(
        string $copyId,
        string $templateId,
        int $productId,
        int $userId,
        ?string $sessionId
    ): void {
        global $wpdb;

        $tableName = TableNames::showcaseCopy();
        $now = current_time('mysql');

        $inserted = $wpdb->insert(
            $tableName,
            [
                'copy_id' => $copyId,
                'template_id' => $templateId,
                'product_id' => $productId,
                'user_id' => $userId,
                'session_id' => $sessionId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                '%s',
                '%s',
                '%d',
                '%d',
                '%s',
                '%s',
                '%s',
            ]
        );

        if ($inserted === false) {
            throw new RuntimeException('Die RCFG-Arbeitskopie konnte nicht in der Showcase-Mapping-Tabelle vermerkt werden.');
        }
    }

    public function findByCopyId(string $copyId): ?array
    {
        global $wpdb;

        $copyId = strtoupper(trim($copyId));

        if ($copyId === '') {
            return null;
        }

        $tableName = TableNames::showcaseCopy();

        $sql = $wpdb->prepare(
            "SELECT id, copy_id, template_id, product_id, user_id, session_id, created_at, updated_at
             FROM {$tableName}
             WHERE copy_id = %s
             LIMIT 1",
            $copyId
        );

        $row = $wpdb->get_row($sql, ARRAY_A);

        return is_array($row) ? $row : null;
    }

    public function existsByCopyId(string $copyId): bool
    {
        return $this->findByCopyId($copyId) !== null;
    }

    public function touchUpdatedAt(string $copyId): void
    {
        global $wpdb;

        $copyId = strtoupper(trim($copyId));

        if ($copyId === '') {
            return;
        }

        $wpdb->update(
            TableNames::showcaseCopy(),
            [
                'updated_at' => current_time('mysql'),
            ],
            [
                'copy_id' => $copyId,
            ],
            [
                '%s',
            ],
            [
                '%s',
            ]
        );
    }
}