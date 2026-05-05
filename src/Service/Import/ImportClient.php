<?php

declare(strict_types=1);

namespace Asf\RcfgShowcase\Service\Import;

use Asf\RcfgShowcase\Service\Pricing\RingPriceCalculatorAdapter;
use RuntimeException;
use Throwable;

if (!defined('ABSPATH')) {
    exit;
}

final class ImportClient
{
    private const MAX_RESPONSE_BYTES = 5242880; // 5 MB

    private PimRingProductNormalizer $normalizer;
    private RingPriceCalculatorAdapter $priceCalculator;

    public function __construct(
        private readonly string $importUrl,
        ?PimRingProductNormalizer $normalizer = null,
        ?RingPriceCalculatorAdapter $priceCalculator = null
    ) {
        $this->normalizer = $normalizer ?? new PimRingProductNormalizer();
        $this->priceCalculator = $priceCalculator ?? new RingPriceCalculatorAdapter();
    }

    /**
     * @return array{
     *     success: bool,
     *     generated_at: string|null,
     *     count: int,
     *     items: array<int, array<string, mixed>>,
     *     warnings: array<int, string>
     * }
     */
    public function fetchAndValidate(): array
    {
        $response = wp_remote_get($this->importUrl, [
            'timeout' => 30,
            'redirection' => 3,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'ASF-RCFG-Showcase/' . ASF_RCFG_SHOWCASE_VERSION,
            ],
        ]);

        if (is_wp_error($response)) {
            throw new RuntimeException(
                'Import-API konnte nicht erreicht werden: ' . $response->get_error_message()
            );
        }

        $statusCode = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException(sprintf(
                'Import-API hat HTTP-Status %d zurückgegeben.',
                $statusCode
            ));
        }

        if (trim($body) === '') {
            throw new RuntimeException('Import-API hat eine leere Antwort zurückgegeben.');
        }

        if (strlen($body) > self::MAX_RESPONSE_BYTES) {
            throw new RuntimeException('Import-API Antwort ist größer als 5 MB und wurde aus Sicherheitsgründen abgelehnt.');
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new RuntimeException('Import-API hat kein gültiges JSON zurückgegeben: ' . $exception->getMessage());
        }

        return $this->validatePayload($decoded);
    }

    /**
     * @param mixed $payload
     * @return array{
     *     success: bool,
     *     generated_at: string|null,
     *     count: int,
     *     items: array<int, array<string, mixed>>,
     *     warnings: array<int, string>
     * }
     */
    private function validatePayload(mixed $payload): array
    {
        if (!is_array($payload)) {
            throw new RuntimeException('Import-JSON muss ein Objekt sein.');
        }

        $warnings = [];

        if (array_key_exists('success', $payload) && $payload['success'] !== true) {
            throw new RuntimeException('Import-JSON meldet success=false.');
        }

        $generatedAt = isset($payload['generated_at']) && is_scalar($payload['generated_at'])
            ? (string) $payload['generated_at']
            : null;

        if (!isset($payload['items']) || !is_array($payload['items'])) {
            throw new RuntimeException('Import-JSON muss ein Array "items" enthalten.');
        }

        $items = [];

        foreach ($payload['items'] as $index => $rawItem) {
            if (!is_array($rawItem)) {
                $warnings[] = sprintf('Eintrag #%d wurde übersprungen: Eintrag ist kein Objekt.', (int) $index);
                continue;
            }

            try {
                $normalized = $this->normalizer->normalize($rawItem, (int) $index);
                $this->validateNormalizedItem($normalized, (int) $index, $warnings);

                $priceResult = $this->priceCalculator->calculateImportPrice($normalized);
                $normalized['price_result'] = $priceResult;
                $normalized['regular_price'] = $priceResult['formatted_price'];

                foreach ($priceResult['warnings'] as $priceWarning) {
                    $warnings[] = sprintf(
                        'Eintrag #%d "%s": %s',
                        (int) $index,
                        (string) ($normalized['sku'] ?? ''),
                        $priceWarning
                    );
                }

                $items[] = $normalized;
            } catch (RuntimeException $exception) {
                $warnings[] = $exception->getMessage();
            }
        }

        if ($items === []) {
            throw new RuntimeException('Import-JSON enthält keine gültigen Artikel. ' . implode(' ', $warnings));
        }

        return [
            'success' => true,
            'generated_at' => $generatedAt,
            'count' => count($items),
            'items' => $items,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @param string[] $warnings
     */
    private function validateNormalizedItem(array $item, int $index, array &$warnings): void
    {
        foreach (['external_id', 'sku', 'product_type', 'model', 'title'] as $requiredKey) {
            if (empty($item[$requiredKey]) || !is_scalar($item[$requiredKey])) {
                throw new RuntimeException(sprintf(
                    'Eintrag #%d wurde übersprungen: "%s" fehlt.',
                    $index,
                    $requiredKey
                ));
            }
        }

        $status = isset($item['status']) && is_scalar($item['status']) ? (string) $item['status'] : 'publish';

        if (!in_array($status, ['publish', 'draft', 'private'], true)) {
            throw new RuntimeException(sprintf(
                'Eintrag #%d "%s" wurde übersprungen: status "%s" ist nicht erlaubt.',
                $index,
                (string) $item['sku'],
                $status
            ));
        }

        $templateId = isset($item['template_id']) && is_scalar($item['template_id'])
            ? trim((string) $item['template_id'])
            : '';

        if ($templateId !== '' && !$this->isValidTemplateId($templateId)) {
            throw new RuntimeException(sprintf(
                'Eintrag #%d "%s" wurde übersprungen: template_id "%s" hat ein ungültiges Format.',
                $index,
                (string) $item['sku'],
                $templateId
            ));
        }

        $capabilities = is_array($item['capabilities'] ?? null) ? $item['capabilities'] : [];

        if (($capabilities['showcase'] ?? false) === true && $templateId === '') {
            $warnings[] = sprintf(
                'Eintrag #%d "%s": Showcase ist aktiv, aber es fehlt noch eine template_id. Das Produkt kann importiert werden, aber der 3D-Weiterkonfigurieren-Flow ist erst nach Mapping einer Template-ID nutzbar.',
                $index,
                (string) $item['sku']
            );
        }

        if (empty($item['main_image_url'])) {
            $warnings[] = sprintf(
                'Eintrag #%d "%s": Es konnte keine Hauptbild-URL aus den Importdaten abgeleitet werden.',
                $index,
                (string) $item['sku']
            );
        }
    }

    private function isValidTemplateId(string $templateId): bool
    {
        return (bool) preg_match('/^[A-Z0-9]{4}-[A-Z0-9]{4}(?:-\d+)?$/', strtoupper(trim($templateId)));
    }
}