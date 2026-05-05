<?php

declare(strict_types=1);

namespace Asf\RcfgShowcase\Service\Import;

use Asf\RcfgShowcase\Domain\RingProductCapabilities;
use Asf\RcfgShowcase\Domain\RingProductType;
use RuntimeException;

if (!defined('ABSPATH')) {
    exit;
}

final class PimRingProductNormalizer
{
    private CdnImageUrlBuilder $imageUrlBuilder;

    public function __construct(?CdnImageUrlBuilder $imageUrlBuilder = null)
    {
        $this->imageUrlBuilder = $imageUrlBuilder ?? new CdnImageUrlBuilder();
    }

    /**
     * @param array<string, mixed> $rawItem
     * @return array<string, mixed>
     */
    public function normalize(array $rawItem, int $index): array
    {
        $type = RingProductType::detectFromItem($rawItem);

        if ($type === '') {
            throw new RuntimeException(sprintf(
                'Eintrag #%d wurde übersprungen: Produkttyp konnte nicht erkannt werden.',
                $index
            ));
        }

        $model = $this->firstString($rawItem, ['model', 'customfield_asf_model']);

        if ($model === '') {
            throw new RuntimeException(sprintf(
                'Eintrag #%d wurde übersprungen: Modell fehlt.',
                $index
            ));
        }

        $model = strtoupper($model);
        $capabilities = RingProductCapabilities::resolve($type, $rawItem);

        $externalId = $this->firstString($rawItem, ['external_id', 'oo_id']);

        if ($externalId === '') {
            $externalId = $model;
        }

        $sku = $this->firstString($rawItem, ['sku']);

        if ($sku === '') {
            $sku = $model;
        }

        $category = $this->firstString($rawItem, ['category']);
        $manufacturer = $this->firstString($rawItem, ['manufacturer']);
        $material = $this->firstString($rawItem, ['material', 'customfield_asf_material']);
        $alloy = $this->firstString($rawItem, ['alloy', 'customfield_asf_alloy']);
        $profile = $this->firstString($rawItem, ['profile', 'profil']);
        $stones = $this->firstString($rawItem, ['stones', 'customfield_asf_stones']);
        $defaultStone = $this->firstString($rawItem, ['default_stone', 'customfield_asf_default_stone']);
        $defaultSurface = $this->firstString($rawItem, ['default_surface', 'customfield_asf_default_surface']);

        $surfaceOptions = $this->normalizeList(
            $rawItem['surface_options'] ?? ($rawItem['customfield_asf_surface'] ?? '')
        );

        if ($defaultSurface === '' && $surfaceOptions !== []) {
            $defaultSurface = (string) $surfaceOptions[0];
        }

        $normalized = [
            'external_id' => (string) $externalId,
            'sku' => strtoupper((string) $sku),
            'product_type' => $type,
            'model' => $model,
            'title' => $this->resolveTitle($rawItem, $type, $model),
            'slug' => $this->resolveSlug($rawItem, $type, $model),
            'status' => $this->resolveStatus($rawItem),
            'template_id' => strtoupper($this->firstString($rawItem, ['template_id'])),
            'regular_price' => $this->firstString($rawItem, ['regular_price']),
            'category' => $category,
            'manufacturer' => $manufacturer,
            'material' => $material,
            'alloy' => $alloy,
            'profile' => $profile,
            'surface_options' => $surfaceOptions,
            'default_surface' => $defaultSurface,
            'stones' => $stones,
            'default_stone' => $defaultStone,
            'stone_colors' => $this->normalizeList(
                $rawItem['stone_colors'] ?? ($rawItem['customfield_asf_stone_colors'] ?? '')
            ),
            'mount' => $this->normalizeList(
                $rawItem['mount'] ?? ($rawItem['customfield_asf_mount'] ?? '')
            ),
            'gap' => $this->firstString($rawItem, ['gap', 'customfield_asf_gap']),
            'gap_width' => $this->firstString($rawItem, ['gap_width', 'customfield_asf_gap_width']),
            'style_tags' => $this->normalizeList(
                $rawItem['style_tags'] ?? ($rawItem['customfield_asf_stil'] ?? '')
            ),
            'profile_list' => $this->normalizeList(
                $rawItem['profile_list'] ?? ($rawItem['customfield_asf_profileList'] ?? '')
            ),
            'woman' => [
                'width_min' => $this->firstString($rawItem, ['woman_width_min', 'customfield_asf_minWomanWidth']),
                'width_max' => $this->firstString($rawItem, ['woman_width_max', 'customfield_asf_maxWomanWidth']),
                'width_default' => $this->firstString($rawItem, ['woman_width_default', 'customfield_asf_default_width']),
                'strength_min' => $this->firstString($rawItem, ['woman_strength_min', 'customfield_asf_minWomanStrength']),
                'strength_max' => $this->firstString($rawItem, ['woman_strength_max', 'customfield_asf_maxWomanStrength']),
                'strength_default' => $this->firstString($rawItem, ['woman_strength_default', 'customfield_asf_default_strength']),
            ],
            'man' => [
                'width_min' => $this->firstString($rawItem, ['man_width_min', 'customfield_asf_minManWidth']),
                'width_max' => $this->firstString($rawItem, ['man_width_max', 'customfield_asf_maxManWidth']),
                'width_default' => $this->firstString($rawItem, ['man_width_default', 'customfield_asf_default_width']),
                'strength_min' => $this->firstString($rawItem, ['man_strength_min', 'customfield_asf_minManStrength']),
                'strength_max' => $this->firstString($rawItem, ['man_strength_max', 'customfield_asf_maxManStrength']),
                'strength_default' => $this->firstString($rawItem, ['man_strength_default', 'customfield_asf_default_strength']),
            ],
            'capabilities' => $capabilities,
            'description' => $this->resolveDescription($rawItem),
            'short_description' => $this->resolveShortDescription($rawItem, $type, $model, $material, $alloy, $stones),
            'attributes' => $this->resolveAttributes($rawItem, $material, $alloy, $profile, $stones, $defaultStone, $defaultSurface),
            'source' => [
                'system' => $this->firstString($rawItem, ['source_system']) ?: 'pimcore',
                'oo_id' => $this->firstString($rawItem, ['oo_id']),
                'image_hash' => $this->firstString($rawItem, ['imageHash']),
                'price_group' => $this->firstString($rawItem, ['priceGroup']),
                'delivery' => $this->firstString($rawItem, ['delivery']),
            ],
            'raw' => $rawItem,
        ];

        $normalized['description'] = $this->replacePlaceholders((string) $normalized['description'], $normalized);

        $normalized['main_image_url'] = $this->imageUrlBuilder->buildMainImageUrl($normalized);
        $normalized['surface_image_urls'] = $this->imageUrlBuilder->buildSurfaceImageUrls($normalized, $surfaceOptions);

        return $normalized;
    }

    /**
     * @param array<string, mixed> $rawItem
     */
    private function resolveTitle(array $rawItem, string $type, string $model): string
    {
        $title = $this->firstString($rawItem, ['title', 'name']);

        if ($title !== '') {
            return $title;
        }

        return RingProductType::label($type) . ' ' . $model;
    }

    /**
     * @param array<string, mixed> $rawItem
     */
    private function resolveSlug(array $rawItem, string $type, string $model): string
    {
        $slug = $this->firstString($rawItem, ['slug']);

        if ($slug !== '') {
            return sanitize_title($slug);
        }

        return sanitize_title(RingProductType::label($type) . '-' . $model);
    }

    /**
     * @param array<string, mixed> $rawItem
     */
    private function resolveStatus(array $rawItem): string
    {
        $status = $this->firstString($rawItem, ['status']);

        if ($status !== '') {
            return in_array($status, ['publish', 'draft', 'private'], true) ? $status : 'draft';
        }

        $active = strtolower($this->firstString($rawItem, ['active']));

        if (in_array($active, ['0', 'false', 'no', 'nein'], true)) {
            return 'draft';
        }

        return 'publish';
    }

    /**
     * @param array<string, mixed> $rawItem
     */
    private function resolveDescription(array $rawItem): string
    {
        return $this->firstString($rawItem, ['description', 'desc']);
    }

    /**
     * @param array<string, mixed> $rawItem
     */
    private function resolveShortDescription(
        array $rawItem,
        string $type,
        string $model,
        string $material,
        string $alloy,
        string $stones
    ): string {
        $shortDescription = $this->firstString($rawItem, ['short_description']);

        if ($shortDescription !== '') {
            return $shortDescription;
        }

        $parts = [
            RingProductType::label($type) . ' ' . $model,
        ];

        if ($alloy !== '' || $material !== '') {
            $parts[] = 'aus ' . trim($alloy . ' ' . $material);
        }

        if ($stones !== '') {
            $parts[] = 'mit Steinbesatz ' . $stones;
        }

        return implode(' ', $parts) . '.';
    }

    /**
     * @param array<string, mixed> $rawItem
     * @return array<int, array{name:string,value:string,visible:bool}>
     */
    private function resolveAttributes(
        array $rawItem,
        string $material,
        string $alloy,
        string $profile,
        string $stones,
        string $defaultStone,
        string $defaultSurface
    ): array {
        if (isset($rawItem['attributes']) && is_array($rawItem['attributes'])) {
            return $this->normalizeAttributes($rawItem['attributes']);
        }

        $attributes = [];

        foreach ([
                     'Material' => $material,
                     'Legierung' => $alloy,
                     'Profil' => $profile,
                     'Steinbesatz' => $stones,
                     'Steinart' => $defaultStone,
                     'Standardoberfläche' => $defaultSurface,
                 ] as $name => $value) {
            if (trim((string) $value) === '') {
                continue;
            }

            $attributes[] = [
                'name' => $name,
                'value' => trim((string) $value),
                'visible' => true,
            ];
        }

        return $attributes;
    }

    /**
     * @param array<int|string, mixed> $attributes
     * @return array<int, array{name:string,value:string,visible:bool}>
     */
    private function normalizeAttributes(array $attributes): array
    {
        $normalized = [];

        foreach ($attributes as $key => $attribute) {
            if (is_array($attribute)) {
                $name = isset($attribute['name']) && is_scalar($attribute['name'])
                    ? trim((string) $attribute['name'])
                    : '';

                $value = isset($attribute['value']) && is_scalar($attribute['value'])
                    ? trim((string) $attribute['value'])
                    : '';

                $visible = !array_key_exists('visible', $attribute) || (bool) $attribute['visible'];
            } else {
                $name = is_string($key) ? trim($key) : '';
                $value = is_scalar($attribute) ? trim((string) $attribute) : '';
                $visible = true;
            }

            if ($name === '' || $value === '') {
                continue;
            }

            $normalized[] = [
                'name' => $name,
                'value' => $value,
                'visible' => $visible,
            ];
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $rawItem
     * @param string[] $keys
     */
    private function firstString(array $rawItem, array $keys): string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $rawItem)) {
                continue;
            }

            if (!is_scalar($rawItem[$key])) {
                continue;
            }

            $value = trim((string) $rawItem[$key]);

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @return string[]
     */
    private function normalizeList(mixed $value): array
    {
        if (is_array($value)) {
            $items = $value;
        } else {
            $value = trim((string) $value);

            if ($value === '') {
                return [];
            }

            $items = preg_split('/[;,]+/', $value) ?: [];
        }

        $normalized = [];

        foreach ($items as $item) {
            if (!is_scalar($item)) {
                continue;
            }

            $item = trim((string) $item, " \t\n\r\0\x0B,");

            if ($item === '') {
                continue;
            }

            $normalized[] = $item;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param array<string, mixed> $normalized
     */
    private function replacePlaceholders(string $text, array $normalized): string
    {
        if ($text === '') {
            return '';
        }

        $raw = is_array($normalized['raw'] ?? null) ? $normalized['raw'] : [];

        $replacements = [
            '{customfield_asf_material}' => (string) ($normalized['material'] ?? ''),
            '{customfield_asf_alloy}' => (string) ($normalized['alloy'] ?? ''),
            '{customfield_asf_stones}' => (string) ($normalized['stones'] ?? ''),
            '{customfield_asf_model}' => (string) ($normalized['model'] ?? ''),
            '{customfield_asf_default_stone}' => (string) ($normalized['default_stone'] ?? ''),
            '{customfield_asf_surface}' => implode(', ', (array) ($normalized['surface_options'] ?? [])),
        ];

        foreach ($raw as $key => $value) {
            if (!is_scalar($value)) {
                continue;
            }

            $replacements['{' . $key . '}'] = (string) $value;
        }

        return strtr($text, $replacements);
    }
}