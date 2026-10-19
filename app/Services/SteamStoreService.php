<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SteamStoreService
{
    public function fetchGame(int $appId): ?array
    {
        try {
            $response = Http::acceptJson()
                ->withUserAgent('GameHubPriceTracker/1.0')
                ->connectTimeout(5)
                ->timeout(15)
                ->get('https://store.steampowered.com/api/appdetails', [
                    'appids' => $appId,
                    'cc' => 'id',
                    'l' => 'english',
                ]);

            if (! $response->successful()) {
                Log::warning('Steam Store API request failed.', [
                    'app_id' => $appId,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $wrapper = $response->json((string) $appId);
            $data = is_array($wrapper) ? ($wrapper['data'] ?? null) : null;

            if (! is_array($data) || ! ($wrapper['success'] ?? false) || (int) ($data['steam_appid'] ?? 0) !== $appId) {
                Log::warning('Steam Store API returned no matching game.', ['app_id' => $appId]);

                return null;
            }

            $price = $this->normalizePrice($data['price_overview'] ?? null);
            $releaseDate = $this->normalizeReleaseDate($data['release_date']['date'] ?? null);

            return [
                'steam_app_id' => $appId,
                'title' => $data['name'] ?? null,
                'description' => $data['short_description'] ?? null,
                'genre' => $data['genres'][0]['description'] ?? null,
                'release_date' => $releaseDate,
                'cover_image' => $data['header_image'] ?? null,
                'popularity' => isset($data['recommendations']['total']) ? (int) $data['recommendations']['total'] : null,
                'system_requirements' => $this->normalizeRequirements($data['pc_requirements'] ?? null),
                'price' => $price,
            ];
        } catch (Throwable $exception) {
            Log::warning('Steam Store API request raised an exception.', [
                'app_id' => $appId,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function normalizePrice(mixed $overview): ?array
    {
        if (! is_array($overview)
            || ! isset($overview['currency'], $overview['initial'], $overview['final'])
            || ! preg_match('/^[A-Z]{3}$/', (string) $overview['currency'])
            || ! is_numeric($overview['initial'])
            || ! is_numeric($overview['final'])) {
            return null;
        }

        $original = round((float) $overview['initial'] / 100, 2);
        $final = round((float) $overview['final'] / 100, 2);
        $discount = isset($overview['discount_percent'])
            ? (int) $overview['discount_percent']
            : ($original > 0 ? (int) round((1 - $final / $original) * 100) : 0);

        return [
            'original_price' => $original,
            'final_price' => $final,
            'discount_percentage' => max(0, min(100, $discount)),
            'currency' => (string) $overview['currency'],
        ];
    }

    private function normalizeReleaseDate(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        foreach (['j M, Y', 'M j, Y', 'F j, Y', 'j F, Y', 'Y-m-d'] as $format) {
            $date = \DateTimeImmutable::createFromFormat('!'.$format, trim($value));
            $errors = \DateTimeImmutable::getLastErrors();

            if ($date && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    private function normalizeRequirements(mixed $requirements): array
    {
        if (! is_array($requirements)) {
            return [];
        }

        $labels = [
            'operating_system' => '(?:OS|Operating System)\\*?',
            'processor' => '(?:Processor|CPU)',
            'memory' => 'Memory',
            'graphics' => '(?:Graphics|Video Card)',
            'network' => 'Network',
            'storage' => '(?:Storage|Hard Disk Space|Available Space)',
            'sound_card' => 'Sound Card',
        ];
        $normalized = [];

        foreach (['minimum', 'recommended'] as $type) {
            $content = $requirements[$type] ?? null;
            if (is_array($content)) {
                $content = implode("\n", array_map('strval', $content));
            }
            if (! is_string($content) || trim($content) === '') {
                continue;
            }

            $content = preg_replace('/<(?:br\\s*\\/?|\\/li|\\/p|\\/div)\\s*>/i', "\n", $content) ?? $content;
            $text = html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $fields = [];

            foreach (preg_split('/[\\r\\n]+/', $text) ?: [] as $line) {
                $line = trim($line);
                $line = preg_replace('/^[\s•-]+/u', '', $line) ?? $line;
                if ($line === '') {
                    continue;
                }

                foreach ($labels as $field => $labelPattern) {
                    if (preg_match('/^'.$labelPattern.'\\s*:\\s*(.+)$/iu', $line, $matches)) {
                        $fields[$field] = trim($matches[1]);
                        break;
                    }
                }
            }

            if ($fields !== []) {
                $normalized[$type] = $fields;
            }
        }

        return $normalized;
    }
}
