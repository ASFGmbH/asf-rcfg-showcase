<?php

declare(strict_types=1);

namespace Asf\RcfgShowcase\Service\Pricing;

use Asf\RcfgShowcase\Domain\RingProductType;
use Asf\RcfgShowcase\Repository\RcfgSettingsRepository;
use RuntimeException;

if (!defined('ABSPATH')) {
    exit;
}

final class PimItemPresetFactory
{
    public function __construct(
        private readonly RcfgDefaultPresetRepository $defaultPresetRepository = new RcfgDefaultPresetRepository(),
        private readonly RcfgSettingsRepository $settingsRepository = new RcfgSettingsRepository()
    ) {
    }

    /**
     * @param array<string, mixed> $item
     * @return array<int, array<string, mixed>>
     */
    public function buildPricePresets(array $item): array
    {
        $defaultPair = $this->defaultPresetRepository->findDefaultPresetPair();

        if ($defaultPair === null) {
            throw new RuntimeException('Default-Preset 0000-0000 konnte nicht geladen werden.');
        }

        $type = (string) ($item['product_type'] ?? '');
        $capabilities = is_array($item['capabilities'] ?? null) ? $item['capabilities'] : [];
        $ringCount = isset($capabilities['ring_count']) ? (int) $capabilities['ring_count'] : RingProductType::defaultRingCount($type);

        if ($ringCount === 2) {
            $womanPreset = $defaultPair['preset_0'] ?? $defaultPair['preset_1'] ?? null;
            $manPreset = $defaultPair['preset_1'] ?? $defaultPair['preset_0'] ?? null;

            if (!is_array($womanPreset) || !is_array($manPreset)) {
                throw new RuntimeException('Default-Preset 0000-0000 enthält kein vollständiges Preset-Paar.');
            }

            return [
                $this->patchPreset($womanPreset, $item, 'ms'),
                $this->patchPreset($manPreset, $item, 'mr'),
            ];
        }

        $singlePreset = $defaultPair['preset_0'] ?? $defaultPair['preset_1'] ?? null;

        if (!is_array($singlePreset)) {
            throw new RuntimeException('Default-Preset 0000-0000 enthält kein verwendbares Einzel-Preset.');
        }

        return [
            $this->patchPreset($singlePreset, $item, 'single'),
        ];
    }

    /**
     * @param array<string, mixed> $preset
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function patchPreset(array $preset, array $item, string $side): array
    {
        $isWoman = $side === 'ms';
        $isMan = $side === 'mr';

        $preset['_cartActive'] = true;
        $preset['name'] = $isWoman ? 'Damenring' : 'Herrenring';

        if ($side === 'single') {
            $preset['name'] = 'Ring';
        }

        $profile = $this->stringValue($item, 'profile');

        if ($profile !== '') {
            $preset['_profileName'] = 'Profil ' . preg_replace('/\D+/', '', $profile);
        }

        $width = $this->resolveDimension($item, $side, 'width_default', '5');
        $strength = $this->resolveDimension($item, $side, 'strength_default', '1.4');
        $size = $this->resolveRingSize($side);

        if ($width > 0) {
            $preset['_ringWidth'] = (string) (int) round($width * 1000);
        }

        if ($strength > 0) {
            $preset['_ringHeight'] = (string) (int) round($strength * 1000);
        }

        if ($size > 0) {
            $preset['_ringSize'] = (string) (int) round($size * 1000);
        }

        $this->patchMaterialDataBestEffort($preset, $item);
        $this->patchSurfaceDataBestEffort($preset, $item);
        $this->patchAlloyDataBestEffort($preset, $item);

        return $preset;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function resolveDimension(array $item, string $side, string $key, string $default): float
    {
        $groupKey = $side === 'mr' ? 'man' : 'woman';

        if ($side === 'single') {
            $groupKey = 'woman';
        }

        $group = is_array($item[$groupKey] ?? null) ? $item[$groupKey] : [];
        $value = isset($group[$key]) && is_scalar($group[$key]) ? trim((string) $group[$key]) : '';

        if ($value === '') {
            $value = $default;
        }

        $value = str_replace(',', '.', $value);

        return is_numeric($value) ? (float) $value : (float) $default;
    }

    private function resolveRingSize(string $side): float
    {
        if ($side === 'mr') {
            if ($this->settingsRepository->isEnabled('mr_fixsize_active')) {
                return (float) $this->settingsRepository->getString('mr_fixsize', '58');
            }

            return 58.0;
        }

        if ($side === 'ms') {
            if ($this->settingsRepository->isEnabled('ms_fixsize_active')) {
                return (float) $this->settingsRepository->getString('ms_fixsize', '58');
            }

            return 58.0;
        }

        if ($this->settingsRepository->isEnabled('ms_fixsize_active')) {
            return (float) $this->settingsRepository->getString('ms_fixsize', '58');
        }

        return 58.0;
    }

    /**
     * Material-Mapping ist bewusst best-effort.
     *
     * Wenn das Default-Preset bereits korrekte Materialarrays besitzt, werden sie erhalten.
     * Ohne vollständiges appData-ID-Mapping erzwingen wir hier noch keine harten IDs.
     *
     * @param array<string, mixed> $preset
     * @param array<string, mixed> $item
     */
    private function patchMaterialDataBestEffort(array &$preset, array $item): void
    {
        $material = strtolower($this->stringValue($item, 'material'));

        if ($material === '') {
            return;
        }

        $parts = array_values(array_filter(array_map('trim', preg_split('/[-_]+/', $material) ?: [])));

        if ($parts === []) {
            return;
        }

        if (!isset($preset['_materialDiv']) || !is_array($preset['_materialDiv']) || count($preset['_materialDiv']) !== count($parts)) {
            $share = (int) floor(10000 / count($parts));
            $divisions = array_fill(0, count($parts), $share);
            $divisions[count($divisions) - 1] += 10000 - array_sum($divisions);

            $preset['_materialDiv'] = array_map('strval', $divisions);
        }

        /*
         * _material selbst bleibt bewusst unangetastet, solange keine sichere appData-ID-Auflösung
         * für Tokens wie gelbgold/weissgold vorhanden ist.
         */
    }

    /**
     * @param array<string, mixed> $preset
     * @param array<string, mixed> $item
     */
    private function patchSurfaceDataBestEffort(array &$preset, array $item): void
    {
        /*
         * Auch Oberflächen wie "lm", "po", "sam" sind appData-IDs bzw. Codes des 3D-Systems.
         * Ohne belastbare Code→ID-Tabelle verändern wir _surface noch nicht hart.
         */
        if (!isset($preset['_surface']) || !is_array($preset['_surface'])) {
            return;
        }
    }

    /**
     * @param array<string, mixed> $preset
     * @param array<string, mixed> $item
     */
    private function patchAlloyDataBestEffort(array &$preset, array $item): void
    {
        $alloy = $this->stringValue($item, 'alloy');

        if ($alloy === '') {
            return;
        }

        $alloyNumber = preg_replace('/\D+/', '', $alloy);

        if ($alloyNumber === '') {
            return;
        }

        if (!isset($preset['_fineness']) || !is_array($preset['_fineness'])) {
            return;
        }

        foreach ($preset['_fineness'] as $index => $_value) {
            $preset['_fineness'][$index] = $alloyNumber;
        }
    }

    /**
     * @param array<string, mixed> $source
     */
    private function stringValue(array $source, string $key): string
    {
        if (!array_key_exists($key, $source) || !is_scalar($source[$key])) {
            return '';
        }

        return trim((string) $source[$key]);
    }
}