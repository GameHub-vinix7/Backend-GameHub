<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class CheapSharkService
{
    public function fetchCheapestDeal(int $steamAppId): ?array
    {
        try {
            $response = Http::acceptJson()
                ->withUserAgent($this->userAgent())
                ->connectTimeout(5)
                ->timeout(15)
                ->get('https://www.cheapshark.com/api/1.0/deals', [
                    'steamAppID' => $steamAppId,
                    'pageNumber' => 0,
                    'pageSize' => 1,
                    'sortBy' => 'Price',
                    'desc' => 0,
                ]);

            if ($response->status() === 429) {
                Log::warning('CheapShark rate limit reached; respecting Retry-After.', [
                    'retry_after' => $response->header('Retry-After'),
                    'steam_app_id' => $steamAppId,
                ]);

                return null;
            }

            if (! $response->successful()) {
                Log::warning('CheapShark deals request failed.', [
                    'steam_app_id' => $steamAppId,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $deal = $response->json()[0] ?? null;

            if (! is_array($deal)
                || (int) ($deal['steamAppID'] ?? 0) !== $steamAppId
                || ! isset($deal['dealID'], $deal['salePrice'], $deal['normalPrice'])
                || ! is_numeric($deal['salePrice'])
                || ! is_numeric($deal['normalPrice'])) {
                return null;
            }

            $original = round((float) $deal['normalPrice'], 2);
            $final = round((float) $deal['salePrice'], 2);

            return [
                'platform' => 'CheapShark',
                'source' => 'cheapshark',
                'currency' => 'USD',
                'original_price' => $original,
                'final_price' => $final,
                'discount_percentage' => $original > 0
                    ? max(0, min(100, (int) round((1 - $final / $original) * 100)))
                    : 0,
                'store_url' => 'https://www.cheapshark.com/redirect?dealID='.$deal['dealID'],
                'title' => $deal['title'] ?? null,
                'cover_image' => $deal['thumb'] ?? null,
            ];
        } catch (Throwable $exception) {
            Log::warning('CheapShark deals request raised an exception.', [
                'steam_app_id' => $steamAppId,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function userAgent(): string
    {
        $email = config('services.cheapshark.contact_email');
        $contact = is_string($email) && $email !== '' ? '; '.$email : '';

        return 'GameHubPriceTracker/1.0 (https://github.com/davidshdy17/GameHub'.$contact.')';
    }
}
