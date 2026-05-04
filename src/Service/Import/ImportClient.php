<?php

declare(strict_types=1);

namespace Asf\RcfgShowcase\Service\Import;

use RuntimeException;
use Throwable;

if (!defined('ABSPATH')) {
    exit;
}

final class ImportClient
{
    private const MAX_RESPONSE_BYTES = 5242880; // 5 MB

    public function __construct(
        private readonly string $importUrl
    ) {
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
                $items[] = $this->validateItem($rawItem, (int) $index);
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
     * @return array<string, mixed>
     */
    private function validateItem(array $item, int $index): array
    {
        $sku = $this->stringValue($item, 'sku');
        $externalId = $this->stringValue($item, 'external_id');
        $title = $this->stringValue($item, 'title');
        $templateId = strtoupper($this->stringValue($item, 'template_id'));

        if ($sku === '' && $externalId === '') {
            throw new RuntimeException(sprintf(
                'Eintrag #%d wurde übersprungen: Es fehlt "sku" oder "external_id".',
                $index
            ));
        }

        if ($sku === '') {
            $sku = 'SHOWCASE-' . sanitize_key($externalId);
            $sku = strtoupper(str_replace('_', '-', $sku));
        }

        if ($externalId === '') {
            $externalId = $sku;
        }

        if ($title === '') {
            throw new RuntimeException(sprintf(
                'Eintrag #%d "%s" wurde übersprungen: Es fehlt "title".',
                $index,
                $sku
            ));
        }

        if ($templateId === '') {
            throw new RuntimeException(sprintf(
                'Eintrag #%d "%s" wurde übersprungen: Es fehlt "template_id".',
                $index,
                $sku
            ));
        }

        if (!$this->isValidTemplateId($templateId)) {
            throw new RuntimeException(sprintf(
                'Eintrag #%d "%s" wurde übersprungen: template_id "%s" hat ein ungültiges Format.',
                $index,
                $sku,
                $templateId
            ));
        }

        $status = $this->stringValue($item, 'status');

        if ($status === '') {
            $status = 'publish';
        }

        if (!in_array($status, ['publish', 'draft', 'private'], true)) {
            throw new RuntimeException(sprintf(
                'Eintrag #%d "%s" wurde übersprungen: status "%s" ist nicht erlaubt.',
                $index,
                $sku,
                $status
            ));
        }

        $regularPrice = $this->stringValue($item, 'regular_price');

        if ($regularPrice !== '') {
            $normalizedPrice = str_replace(',', '.', $regularPrice);

            if (!is_numeric($normalizedPrice) || (float) $normalizedPrice < 0) {
                throw new RuntimeException(sprintf(
                    'Eintrag #%d "%s" wurde übersprungen: regular_price "%s" ist ungültig.',
                    $index,
                    $sku,
                    $regularPrice
                ));
            }

            $regularPrice = number_format((float) $normalizedPrice, 2, '.', '');
        }

        $attributes = $this->normalizeAttributes($item['attributes'] ?? []);
        $meta = is_array($item['meta'] ?? null) ? $item['meta'] : [];

        return [
            'external_id' => $externalId,
            'sku' => $sku,
            'title' => $title,
            'slug' => sanitize_title($this->stringValue($item, 'slug')),
            'status' => $status,
            'template_id' => $templateId,
            'regular_price' => $regularPrice,
            'short_description' => $this->stringValue($item, 'short_description'),
            'description' => $this->stringValue($item, 'description'),
            'attributes' => $attributes,
            'meta' => $meta,
            'raw' => $item,
        ];
    }

    /**
     * @param mixed $attributes
     * @return array<int, array{name:string,value:string,visible:bool}>
     */
    private function normalizeAttributes(mixed $attributes): array
    {
        if (!is_array($attributes)) {
            return [];
        }

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
     * @param array<string, mixed> $source
     */
    private function stringValue(array $source, string $key): string
    {
        if (!array_key_exists($key, $source) || !is_scalar($source[$key])) {
            return '';
        }

        return trim((string) $source[$key]);
    }

    private function isValidTemplateId(string $templateId): bool
    {
        return (bool) preg_match('/^[A-Z0-9]{4}-[A-Z0-9]{4}(?:-\d+)?$/', $templateId);
    }
}