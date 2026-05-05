<?php

declare(strict_types=1);

namespace Asf\RcfgShowcase\Service\Import;

use Asf\RcfgShowcase\Domain\RingProductType;

if (!defined('ABSPATH')) {
    exit;
}

final class CdnImageUrlBuilder
{
    private const BASE_URL = 'https://zweipunkt-cdn.vonjacob.de/files/zweipunkt-cdn.vonjacob.de/asf_images';

    /**
     * @param array<string, mixed> $item
     */
    public function buildMainImageUrl(array $item): string
    {
        $defaultSurface = $this->stringValue($item, 'default_surface');

        if ($defaultSurface === '') {
            $defaultSurface = 'lm';
        }

        return $this->buildSurfaceImageUrl($item, $defaultSurface);
    }

    /**
     * @param array<string, mixed> $item
     * @param string[] $surfaceOptions
     * @return array<string, string>
     */
    public function buildSurfaceImageUrls(array $item, array $surfaceOptions): array
    {
        $urls = [];

        foreach ($surfaceOptions as $surface) {
            $surface = trim((string) $surface);

            if ($surface === '') {
                continue;
            }

            $urls[$surface] = $this->buildSurfaceImageUrl($item, $surface);
        }

        return $urls;
    }

    /**
     * @param array<string, mixed> $item
     */
    public function buildSurfaceImageUrl(array $item, string $surface): string
    {
        $type = $this->stringValue($item, 'product_type');
        $model = strtolower($this->stringValue($item, 'model'));
        $material = strtolower($this->stringValue($item, 'material'));
        $stones = $this->normalizeStoneSegment($this->stringValue($item, 'stones'));

        if ($model === '' || $material === '') {
            return '';
        }

        $folder = RingProductType::cdnFolder($type);

        /*
         * Aus existierender CDN-Struktur abgeleitet:
         * /asf_images/trauringe/tr001/tr001-ner-gelbgold-weissgold-1x003-lm.jpg
         *
         * Das Segment "ner" ist aktuell Teil der vorhandenen Bildkonvention.
         */
        $filename = sprintf(
            '%s-ner-%s-%s-%s.jpg',
            $model,
            $material,
            $stones !== '' ? $stones : 'ohne-stein',
            strtolower($surface)
        );

        return self::BASE_URL . '/' . rawurlencode($folder) . '/' . rawurlencode($model) . '/' . rawurlencode($filename);
    }

    private function normalizeStoneSegment(string $stones): string
    {
        $stones = strtolower(trim($stones));

        if ($stones === '') {
            return '';
        }

        $stones = str_replace(',', '.', $stones);
        $stones = preg_replace('/[^a-z0-9.]+/i', '', $stones) ?: $stones;

        return str_replace('.', '', $stones);
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