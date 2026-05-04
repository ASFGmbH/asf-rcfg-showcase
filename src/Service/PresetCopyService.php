<?php

declare(strict_types=1);

namespace Asf\RcfgShowcase\Service;

use Asf\RcfgShowcase\Infrastructure\TableNames;
use Asf\RcfgShowcase\Repository\RcfgPresetRepository;
use RuntimeException;

if (!defined('ABSPATH')) {
    exit;
}

final class PresetCopyService
{
    public function __construct(
        private readonly RcfgPresetRepository $presetRepository = new RcfgPresetRepository()
    ) {
    }

    public function createWorkingCopy(string $templateId, int $productId = 0, int $userId = 0, ?string $sessionId = null): string
    {
        $templateId = $this->normalizePresetId($templateId);

        if (!$this->isValidPresetId($templateId)) {
            throw new RuntimeException('Die angegebene RCFG Template-ID hat ein ungültiges Format.');
        }

        $templatePreset = $this->presetRepository->findPresetById($templateId);

        if ($templatePreset === null) {
            throw new RuntimeException(sprintf(
                'Die RCFG Template-ID "%s" wurde nicht in der Preset-Tabelle gefunden.',
                $templateId
            ));
        }

        if (empty($templatePreset['preset_0']) || empty($templatePreset['preset_1'])) {
            throw new RuntimeException(sprintf(
                'Die RCFG Template-ID "%s" enthält kein vollständiges preset_0/preset_1.',
                $templateId
            ));
        }

        $copyId = $this->createUniqueRcfgId();

        $this->presetRepository->insertPresetCopy($copyId, $templatePreset, $userId);
        $this->insertCopyMapping($copyId, $templateId, $productId, $userId, $sessionId);

        return $copyId;
    }

    private function createUniqueRcfgId(): string
    {
        if (function_exists('one_rcfg_create_id')) {
            $copyId = (string) one_rcfg_create_id();

            if ($copyId !== '' && !$this->presetRepository->presetExists($copyId)) {
                return $copyId;
            }
        }

        for ($i = 0; $i < 20; $i++) {
            $copyId = $this->generateFallbackId();

            if (!$this->presetRepository->presetExists($copyId)) {
                return $copyId;
            }
        }

        throw new RuntimeException('Es konnte keine eindeutige RCFG-Arbeitskopie-ID erzeugt werden.');
    }

    private function generateFallbackId(): string
    {
        $characters = '23456789ABCDEFGHIJKLMNPQRSTUVWXYZ';

        $chunk = static function () use ($characters): string {
            $result = '';

            for ($i = 0; $i < 4; $i++) {
                $result .= $characters[random_int(0, strlen($characters) - 1)];
            }

            return $result;
        };

        return $chunk() . '-' . $chunk();
    }

    private function insertCopyMapping(string $copyId, string $templateId, int $productId, int $userId, ?string $sessionId): void
    {
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

    private function normalizePresetId(string $presetId): string
    {
        return strtoupper(trim($presetId));
    }

    private function isValidPresetId(string $presetId): bool
    {
        return (bool) preg_match('/^[A-Z0-9]{4}-[A-Z0-9]{4}(?:-\d+)?$/', $presetId);
    }
}