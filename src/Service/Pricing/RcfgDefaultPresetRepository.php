<?php

declare(strict_types=1);

namespace Asf\RcfgShowcase\Service\Pricing;

use Asf\RcfgShowcase\Infrastructure\TableNames;

if (!defined('ABSPATH')) {
    exit;
}

final class RcfgDefaultPresetRepository
{
    public function findDefaultPresetPair(): ?array
    {
        global $wpdb;

        $tableName = TableNames::rcfgPreset();

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT preset_0, preset_1 FROM {$tableName} WHERE id = %s LIMIT 1",
                '0000-0000'
            ),
            ARRAY_A
        );

        if (!is_array($row)) {
            return null;
        }

        $preset0 = $this->decodePreset((string) ($row['preset_0'] ?? ''));
        $preset1 = $this->decodePreset((string) ($row['preset_1'] ?? ''));

        if ($preset0 === null && $preset1 === null) {
            return null;
        }

        return [
            'preset_0' => $preset0,
            'preset_1' => $preset1,
        ];
    }

    private function decodePreset(string $json): ?array
    {
        if (trim($json) === '') {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }
}