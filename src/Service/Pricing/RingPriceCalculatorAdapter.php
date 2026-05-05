<?php

declare(strict_types=1);

namespace Asf\RcfgShowcase\Service\Pricing;

use Throwable;

if (!defined('ABSPATH')) {
    exit;
}

final class RingPriceCalculatorAdapter
{
    public function __construct(
        private readonly PimItemPresetFactory $presetFactory = new PimItemPresetFactory()
    ) {
    }

    /**
     * @param array<string, mixed> $item
     * @return array{
     *     ok: bool,
     *     price: float,
     *     formatted_price: string,
     *     ring_prices: array<int, float>,
     *     warnings: array<int, string>
     * }
     */
    public function calculateImportPrice(array $item): array
    {
        $warnings = [];

        if (!has_filter('rcfg_calc_price')) {
            return [
                'ok' => false,
                'price' => 0.0,
                'formatted_price' => '0.00',
                'ring_prices' => [],
                'warnings' => [
                    'RingPreisrechner v2 ist nicht aktiv oder der Filter rcfg_calc_price ist nicht registriert.',
                ],
            ];
        }

        try {
            $presets = $this->presetFactory->buildPricePresets($item);
        } catch (Throwable $exception) {
            return [
                'ok' => false,
                'price' => 0.0,
                'formatted_price' => '0.00',
                'ring_prices' => [],
                'warnings' => [
                    'Preis-Presets konnten nicht erzeugt werden: ' . $exception->getMessage(),
                ],
            ];
        }

        $ringPrices = [];

        foreach ($presets as $index => $preset) {
            try {
                $presetObject = json_decode(
                    wp_json_encode($preset, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    false
                );

                if (!$presetObject instanceof \stdClass) {
                    $warnings[] = sprintf('Preis-Preset #%d konnte nicht in ein Objekt umgewandelt werden.', $index);
                    continue;
                }

                $price = (float) apply_filters('rcfg_calc_price', $presetObject);

                if ($price <= 0.0) {
                    $warnings[] = sprintf('RingPreisrechner lieferte für Preset #%d keinen positiven Preis.', $index);
                }

                if (in_array(round($price, 2), [1.0, 2.0], true)) {
                    $warnings[] = sprintf(
                        'RingPreisrechner lieferte für Preset #%d den verdächtigen Fallbackpreis %.2f. Bitte Mapping prüfen.',
                        $index,
                        $price
                    );
                }

                $ringPrices[] = round($price, 2);
            } catch (Throwable $exception) {
                $warnings[] = sprintf(
                    'Preisberechnung für Preset #%d fehlgeschlagen: %s',
                    $index,
                    $exception->getMessage()
                );
            }
        }

        $total = round(array_sum($ringPrices), 2);

        return [
            'ok' => $total > 0.0 && $warnings === [],
            'price' => $total,
            'formatted_price' => number_format($total, 2, '.', ''),
            'ring_prices' => $ringPrices,
            'warnings' => $warnings,
        ];
    }
}