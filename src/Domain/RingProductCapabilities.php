<?php

declare(strict_types=1);

namespace Asf\RcfgShowcase\Domain;

if (!defined('ABSPATH')) {
    exit;
}

final class RingProductCapabilities
{
    /**
     * @return array<string, mixed>
     */
    public static function defaultsForType(string $type): array
    {
        $type = RingProductType::normalize($type);

        return match ($type) {
            RingProductType::TR => [
                'showcase' => true,
                'working_copy' => true,
                'ring_count' => 2,
                'stock_mode' => 'manufactory',
                'has_size_selection' => true,
                'size_mode' => 'pair',
                'personalizable' => true,
                'engraving_mode' => 'pair',
                'has_font_selection' => true,
                'has_stone_selection' => false,
                'has_surface_selection' => true,
                'surface_preview_enabled' => true,
            ],
            RingProductType::VR => [
                'showcase' => false,
                'working_copy' => false,
                'ring_count' => 1,
                'stock_mode' => 'manufactory',
                'has_size_selection' => true,
                'size_mode' => 'single',
                'personalizable' => true,
                'engraving_mode' => 'single',
                'has_font_selection' => true,
                'has_stone_selection' => true,
                'has_surface_selection' => false,
                'surface_preview_enabled' => false,
            ],
            RingProductType::MR => [
                'showcase' => false,
                'working_copy' => false,
                'ring_count' => 1,
                'stock_mode' => 'manufactory',
                'has_size_selection' => true,
                'size_mode' => 'single',
                'personalizable' => true,
                'engraving_mode' => 'single',
                'has_font_selection' => true,
                'has_stone_selection' => true,
                'has_surface_selection' => false,
                'surface_preview_enabled' => false,
            ],
            RingProductType::DR => [
                'showcase' => false,
                'working_copy' => false,
                'ring_count' => 1,
                'stock_mode' => 'stock',
                'has_size_selection' => true,
                'size_mode' => 'single',
                'personalizable' => false,
                'engraving_mode' => 'none',
                'has_font_selection' => false,
                'has_stone_selection' => false,
                'has_surface_selection' => false,
                'surface_preview_enabled' => false,
            ],
            RingProductType::PR => [
                'showcase' => false,
                'working_copy' => false,
                'ring_count' => 2,
                'stock_mode' => 'stock',
                'has_size_selection' => true,
                'size_mode' => 'pair',
                'personalizable' => true,
                'engraving_mode' => 'pair',
                'has_font_selection' => true,
                'has_stone_selection' => false,
                'has_surface_selection' => false,
                'surface_preview_enabled' => false,
            ],
            default => [
                'showcase' => false,
                'working_copy' => false,
                'ring_count' => RingProductType::defaultRingCount($type),
                'stock_mode' => RingProductType::defaultStockMode($type),
                'has_size_selection' => true,
                'size_mode' => RingProductType::defaultRingCount($type) === 2 ? 'pair' : 'single',
                'personalizable' => true,
                'engraving_mode' => RingProductType::defaultRingCount($type) === 2 ? 'pair' : 'single',
                'has_font_selection' => true,
                'has_stone_selection' => false,
                'has_surface_selection' => false,
                'surface_preview_enabled' => false,
            ],
        };
    }

    /**
     * @param array<string, mixed> $rawItem
     * @return array<string, mixed>
     */
    public static function resolve(string $type, array $rawItem): array
    {
        $capabilities = self::defaultsForType($type);

        $noEngraving = self::boolFromMixed($rawItem['customfield_asf_noEngraving'] ?? null);

        if ($noEngraving === true) {
            $capabilities['personalizable'] = false;
            $capabilities['engraving_mode'] = 'none';
            $capabilities['has_font_selection'] = false;
        }

        foreach ([
                     'showcase',
                     'working_copy',
                     'has_size_selection',
                     'personalizable',
                     'has_font_selection',
                     'has_stone_selection',
                     'has_surface_selection',
                     'surface_preview_enabled',
                 ] as $key) {
            if (array_key_exists($key, $rawItem)) {
                $value = self::boolFromMixed($rawItem[$key]);

                if ($value !== null) {
                    $capabilities[$key] = $value;
                }
            }
        }

        if (isset($rawItem['ring_count']) && is_numeric($rawItem['ring_count'])) {
            $ringCount = (int) $rawItem['ring_count'];

            if (in_array($ringCount, [1, 2], true)) {
                $capabilities['ring_count'] = $ringCount;
                $capabilities['size_mode'] = $ringCount === 2 ? 'pair' : 'single';

                if ($capabilities['personalizable'] === true && ($capabilities['engraving_mode'] ?? '') !== 'none') {
                    $capabilities['engraving_mode'] = $ringCount === 2 ? 'pair' : 'single';
                }
            }
        }

        if (isset($rawItem['stock_mode']) && is_scalar($rawItem['stock_mode'])) {
            $stockMode = trim((string) $rawItem['stock_mode']);

            if (in_array($stockMode, ['stock', 'manufactory'], true)) {
                $capabilities['stock_mode'] = $stockMode;
            }
        }

        if (isset($rawItem['engraving_mode']) && is_scalar($rawItem['engraving_mode'])) {
            $engravingMode = trim((string) $rawItem['engraving_mode']);

            if (in_array($engravingMode, ['none', 'single', 'pair'], true)) {
                $capabilities['engraving_mode'] = $engravingMode;
                $capabilities['personalizable'] = $engravingMode !== 'none';
            }
        }

        return $capabilities;
    }

    private static function boolFromMixed(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            $value = strtolower(trim($value));

            if (in_array($value, ['1', 'yes', 'true', 'ja'], true)) {
                return true;
            }

            if (in_array($value, ['0', 'no', 'false', 'nein'], true)) {
                return false;
            }
        }

        return null;
    }
}