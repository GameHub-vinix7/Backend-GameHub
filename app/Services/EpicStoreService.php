<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class EpicStoreService
{
    private const ENDPOINT = 'https://api.egdata.app/search/v2/search';

    /**
     * Looks up one game on the Epic Games Store catalog and returns a normalized IDR offer.
     *
     * Epic's own GraphQL endpoint blocks non-browser TLS fingerprints, so this
     * adapter reads the public egdata catalog mirror. Only an exact title match
     * is accepted so a search for "Hades" never stores "Hades II".
     */
    public function fetchGame(string $title, ?string $slug = null): ?array
    {
        try {
            $response = Http::acceptJson()
                ->withUserAgent('GameHubPriceTracker/1.0')
                ->connectTimeout(5)
                ->timeout(20)
                ->post(self::ENDPOINT.'?country=ID&locale=en-US', [
                    'title' => $title,
                    'page' => 1,
                    'limit' => 20,
                ]);

            if (! $response->successful()) {
                Log::warning('Epic catalog API request failed.', [
                    'title' => $title,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $offers = $response->json('offers');

            if (! is_array($offers)) {
                Log::warning('Epic catalog API returned an unexpected payload.', ['title' => $title]);

                return null;
            }

            $element = $this->matchOffer($title, $slug, $offers);

            if ($element === null) {
                Log::info('Epic catalog API returned no exact title match.', ['title' => $title]);

                return null;
            }

            return $this->normalizeOffer($element, $title);
        } catch (Throwable $exception) {
            Log::warning('Epic catalog API request raised an exception.', [
                'title' => $title,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function matchOffer(string $title, ?string $slug, array $offers): ?array
    {
        $wanted = $this->normalizeTitle($title);
        $slug = $slug !== null ? strtolower(trim($slug)) : null;

        $matches = array_values(array_filter($offers, function ($offer) use ($wanted, $slug): bool {
            if (! is_array($offer) || ! isset($offer['title']) || ! is_string($offer['title'])) {
                return false;
            }

            if ($this->normalizeTitle($offer['title']) === $wanted) {
                return true;
            }

            // The same Epic product can gain a suffix such as "— Remastered"
            // while keeping the canonical product slug.
            return $slug !== null && (
                strtolower((string) ($offer['urlSlug'] ?? '')) === $slug
                || strtolower((string) ($offer['productSlug'] ?? '')) === $slug
            );
        }));

        if ($matches === []) {
            return null;
        }

        usort($matches, function (array $a, array $b) use ($slug, $wanted): int {
            $score = fn (array $offer): array => [
                $this->hasIdrPrice($offer) ? 0 : 1,
                in_array(strtoupper((string) ($offer['offerType'] ?? '')), ['BASE_GAME', 'EDITION'], true) ? 0 : 1,
                $this->isPrePurchase($offer) ? 1 : 0,
                $slug !== null && (
                    strtolower((string) ($offer['urlSlug'] ?? '')) === $slug
                    || strtolower((string) ($offer['productSlug'] ?? '')) === $slug
                ) ? 0 : 1,
                $this->normalizeTitle((string) $offer['title']) === $wanted ? 0 : 1,
                str_contains((string) ($offer['urlSlug'] ?? ''), '--') ? 1 : 0,
                strlen((string) ($offer['urlSlug'] ?? '')),
            ];

            return $score($a) <=> $score($b);
        });

        return $matches[0];
    }

    private function isPrePurchase(array $offer): bool
    {
        $slug = strtolower((string) ($offer['urlSlug'] ?? ''));

        return str_contains($slug, 'prepurchase') || str_contains($slug, 'pre-purchase');
    }

    private function hasIdrPrice(array $offer): bool
    {
        return strtoupper((string) ($offer['price']['price']['currencyCode'] ?? '')) === 'IDR';
    }

    private function normalizeOffer(array $offer, string $requestedTitle): ?array
    {
        $total = $offer['price']['price'] ?? null;

        if (! is_array($total)
            || ! isset($total['currencyCode'], $total['originalPrice'], $total['discountPrice'])
            || ! is_numeric($total['originalPrice'])
            || ! is_numeric($total['discountPrice'])) {
            Log::info('Epic catalog API returned no usable price.', ['title' => $requestedTitle]);

            return null;
        }

        $currency = strtoupper((string) $total['currencyCode']);

        // The catalog only presents verified Indonesian rupiah prices.
        if ($currency !== 'IDR') {
            Log::info('Epic catalog API returned a non-IDR price; it will not be stored.', [
                'title' => $requestedTitle,
                'currency' => $currency,
            ]);

            return null;
        }

        $original = round((float) $total['originalPrice'] / 100, 2);
        $final = round((float) $total['discountPrice'] / 100, 2);

        if ($original < 0 || $final < 0 || $final > $original) {
            return null;
        }

        $urlSlug = $offer['urlSlug'] ?? $offer['productSlug'] ?? null;

        if (! is_string($urlSlug) || trim($urlSlug) === '') {
            return null;
        }

        $discount = isset($total['discount']) && is_numeric($total['discount'])
            ? (int) $total['discount']
            : ($original > 0 ? (int) round((1 - $final / $original) * 100) : 0);

        return [
            'epic_id' => $offer['id'] ?? null,
            'title' => $offer['title'] ?? $requestedTitle,
            'currency' => 'IDR',
            'original_price' => $original,
            'final_price' => $final,
            'discount_percentage' => max(0, min(100, $discount)),
            'store_url' => 'https://store.epicgames.com/en-US/p/'.rawurlencode(trim($urlSlug)),
        ];
    }

    private function normalizeTitle(string $title): string
    {
        $title = str_replace(['™', '®', '©'], '', $title);
        $title = preg_replace('/[^a-z0-9]+/i', ' ', $title) ?? $title;

        return trim(strtolower(preg_replace('/\s+/', ' ', $title) ?? $title));
    }
}
