<?php

declare(strict_types=1);

namespace Asf\RcfgShowcase\Domain;

if (!defined('ABSPATH')) {
    exit;
}

final class RingProductType
{
    public const TR = 'TR'; // Trauringe
    public const VR = 'VR'; // Verlobungsringe
    public const MR = 'MR'; // Memoireringe
    public const SR = 'SR'; // Sternzeichenringe
    public const BR = 'BR'; // Brailleringe
    public const PR = 'PR'; // Partnerringe
    public const DR = 'DR'; // Damenringe

    /**
     * @return string[]
     */
    public static function all(): array
    {
        return [
            self::TR,
            self::VR,
            self::MR,
            self::SR,
            self::BR,
            self::PR,
            self::DR,
        ];
    }

    public static function normalize(string $type): string
    {
        $type = strtoupper(trim($type));

        return in_array($type, self::all(), true) ? $type : '';
    }

    /**
     * @param array<string, mixed> $item
     */
    public static function detectFromItem(array $item): string
    {
        $explicit = self::stringValue($item, 'product_type');

        if ($explicit !== '') {
            return self::normalize($explicit);
        }

        $model = strtoupper(self::stringValue($item, 'customfield_asf_model'));

        if ($model === '') {
            $model = strtoupper(self::stringValue($item, 'model'));
        }

        foreach (self::all() as $type) {
            if (str_starts_with($model, $type)) {
                return $type;
            }
        }

        $asfType = strtolower(self::stringValue($item, 'customfield_asf_type'));
        $category = strtolower(self::stringValue($item, 'category'));

        $haystack = $asfType . ' ' . $category;

        if (str_contains($haystack, 'trau') || str_contains($haystack, 'ehering')) {
            return self::TR;
        }

        if (str_contains($haystack, 'verlob')) {
            return self::VR;
        }

        if (str_contains($haystack, 'memoire')) {
            return self::MR;
        }

        if (str_contains($haystack, 'sternzeichen')) {
            return self::SR;
        }

        if (str_contains($haystack, 'braille')) {
            return self::BR;
        }

        if (str_contains($haystack, 'partner')) {
            return self::PR;
        }

        if (str_contains($haystack, 'damen')) {
            return self::DR;
        }

        return '';
    }

    public static function label(string $type): string
    {
        return match (self::normalize($type)) {
            self::TR => 'Trauringe',
            self::VR => 'Verlobungsringe',
            self::MR => 'Memoireringe',
            self::SR => 'Sternzeichenringe',
            self::BR => 'Brailleringe',
            self::PR => 'Partnerringe',
            self::DR => 'Damenringe',
            default => 'Ring',
        };
    }

    public static function cdnFolder(string $type): string
    {
        return match (self::normalize($type)) {
            self::TR => 'trauringe',
            self::VR => 'verlobungsringe',
            self::MR => 'memoireringe',
            self::SR => 'sternzeichenringe',
            self::BR => 'brailleringe',
            self::PR => 'partnerringe',
            self::DR => 'damenringe',
            default => 'ringe',
        };
    }

    public static function defaultRingCount(string $type): int
    {
        return match (self::normalize($type)) {
            self::TR, self::PR => 2,
            default => 1,
        };
    }

    public static function defaultStockMode(string $type): string
    {
        return match (self::normalize($type)) {
            self::PR, self::DR => 'stock',
            default => 'manufactory',
        };
    }

    /**
     * @param array<string, mixed> $source
     */
    private static function stringValue(array $source, string $key): string
    {
        if (!array_key_exists($key, $source) || !is_scalar($source[$key])) {
            return '';
        }

        return trim((string) $source[$key]);
    }
}