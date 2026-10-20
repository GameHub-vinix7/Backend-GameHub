<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GogStoreService
{
    private const ENDPOINT = 'https://catalog.gog.com/v1/catalog';

    private const PRODUCT_ENDPOINT = 'https://api.gog.com/v2/games/';

    /** Fetch one exact title from GOG's public catalog, accepting IDR only. */
    public function fetchGame(string $title): ?array
    {
        try {
            $response = Http::acceptJson()
                ->withUserAgent('GameHubPriceTracker/1.0')
                ->connectTimeout(5)
                ->timeout(20)
                ->get(self::ENDPOINT, [
                    'countryCode' => 'ID',
                    'currencyCode' => 'IDR',
                    'locale' => 'en-US',
                    'limit' => 50,
                    'order' => 'desc:relevance',
                    'productType' => 'in:game',
                    'query' => 'like:'.$title,
                ]);

            if (! $response->successful()) {
                Log::warning('GOG catalog request failed.', ['title' => $title, 'status' => $response->status()]);

                return null;
            }

            $products = $response->json('products');
            if (! is_array($products)) {
                Log::warning('GOG catalog returned an unexpected payload.', ['title' => $title]);

                return null;
            }

            $wanted = $this->normalizeTitle($title);
            foreach ($products as $product) {
                if (! is_array($product) || ! is_string($product['title'] ?? null)
                    || $this->normalizeTitle($product['title']) !== $wanted) {
                    continue;
                }

                $normalized = $this->normalizeProduct($product, $title);
                if ($normalized === null) {
                    return null;
                }

                $productId = $product['id'] ?? null;
                $normalized['system_requirements'] = is_string($productId) && ctype_digit($productId)
                    ? $this->fetchSystemRequirements($productId, $title)
                    : [];

                return $normalized;
            }

            Log::info('GOG catalog returned no exact title match.', ['title' => $title]);

            return null;
        } catch (Throwable $exception) {
            Log::warning('GOG catalog request raised an exception.', [
                'title' => $title,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function fetchSystemRequirements(string $productId, string $title): array
    {
        try {
            $response = Http::acceptJson()
                ->withUserAgent('GameHubPriceTracker/1.0')
                ->connectTimeout(5)
                ->timeout(15)
                ->get(self::PRODUCT_ENDPOINT.$productId);

            if (! $response->successful()) {
                Log::info('GOG product details did not return system requirements.', [
                    'title' => $title,
                    'product_id' => $productId,
                    'status' => $response->status(),
                ]);

                return [];
            }

            return $this->normalizeSystemRequirements($response->json('_embedded.supportedOperatingSystems'));
        } catch (Throwable $exception) {
            Log::info('GOG system requirements could not be fetched; price sync will continue.', [
                'title' => $title,
                'product_id' => $productId,
                'message' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    private function normalizeSystemRequirements(mixed $operatingSystems): array
    {
        if (! is_array($operatingSystems)) {
            return [];
        }

        $fieldMap = [
            'system' => 'operating_system',
            'operating_system' => 'operating_system',
            'processor' => 'processor',
            'cpu' => 'processor',
            'memory' => 'memory',
            'graphics' => 'graphics',
            'video_card' => 'graphics',
            'network' => 'network',
            'storage' => 'storage',
            'sound_card' => 'sound_card',
            'other' => 'other',
        ];
        $osCount = count($operatingSystems);
        $normalized = [];

        foreach ($operatingSystems as $operatingSystem) {
            if (! is_array($operatingSystem)) {
                continue;
            }

            $rawName = $operatingSystem['operatingSystem']['name'] ?? $operatingSystem['name'] ?? 'System';
            $osName = match (strtolower((string) $rawName)) {
                'windows' => 'Windows',
                'osx', 'macos', 'mac os' => 'macOS',
                'linux' => 'Linux',
                default => ucfirst((string) $rawName),
            };

            foreach (($operatingSystem['systemRequirements'] ?? []) as $requirementSet) {
                if (! is_array($requirementSet)) {
                    continue;
                }

                $type = strtolower((string) ($requirementSet['type'] ?? ''));
                if (! in_array($type, ['minimum', 'recommended'], true)) {
                    continue;
                }

                foreach (($requirementSet['requirements'] ?? []) as $requirement) {
                    if (! is_array($requirement)) {
                        continue;
                    }

                    $field = $fieldMap[strtolower((string) ($requirement['id'] ?? ''))] ?? null;
                    $value = $this->cleanRequirementValue($requirement['description'] ?? null);
                    if ($field === null || $value === null) {
                        continue;
                    }

                    $labelPattern = match ($field) {
                        'processor' => 'process(?:or|sor)|cpu',
                        'operating_system' => 'system|operating system|os',
                        'memory' => 'memory',
                        'graphics' => 'graphics|video card',
                        'network' => 'network',
                        'storage' => 'storage|hard drive space|available space',
                        'sound_card' => 'sound card',
                        default => null,
                    };
                    if ($labelPattern !== null) {
                        $value = trim(preg_replace('/^(?:'.$labelPattern.')\s*:\s*/i', '', $value) ?? $value);
                    }

                    if ($field === 'operating_system') {
                        $value = preg_match('/^'.preg_quote($osName, '/').'/i', $value)
                            ? $value
                            : $osName.': '.$value;
                    } elseif ($osCount > 1) {
                        $value = $osName.': '.$value;
                    }

                    $normalized[$type][$field][] = $value;
                }
            }
        }

        foreach ($normalized as $type => $fields) {
            foreach ($fields as $field => $values) {
                $normalized[$type][$field] = implode('; ', array_values(array_unique($values)));
            }
        }

        return $normalized;
    }

    private function cleanRequirementValue(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $text = html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

        return $text === '' ? null : $text;
    }

    private function normalizeProduct(array $product, string $requestedTitle): ?array
    {
        $price = $product['price'] ?? null;
        $base = $price['baseMoney'] ?? null;
        $final = $price['finalMoney'] ?? null;
        $slug = $product['slug'] ?? null;

        if (! is_array($base) || ! is_array($final)
            || strtoupper((string) ($base['currency'] ?? '')) !== 'IDR'
            || strtoupper((string) ($final['currency'] ?? '')) !== 'IDR'
            || ! is_numeric($base['amount'] ?? null)
            || ! is_numeric($final['amount'] ?? null)
            || ! is_string($slug) || ! preg_match('/^[a-z0-9_]+$/i', $slug)) {
            Log::info('GOG product has no valid IDR price or product slug.', ['title' => $requestedTitle]);

            return null;
        }

        $original = round((float) $base['amount'], 2);
        $current = round((float) $final['amount'], 2);
        if ($original < 0 || $current < 0 || $current > $original) {
            return null;
        }

        $discount = $original > 0 ? (int) round((1 - $current / $original) * 100) : 0;

        return [
            'currency' => 'IDR',
            'original_price' => $original,
            'final_price' => $current,
            'discount_percentage' => max(0, min(100, $discount)),
            'store_url' => 'https://www.gog.com/en/game/'.$slug,
        ];
    }

    private function normalizeTitle(string $title): string
    {
        $title = str_replace(['™', '®', '©'], '', $title);
        $title = preg_replace('/[^a-z0-9]+/i', ' ', $title) ?? $title;

        return trim(strtolower(preg_replace('/\s+/', ' ', $title) ?? $title));
    }
}
