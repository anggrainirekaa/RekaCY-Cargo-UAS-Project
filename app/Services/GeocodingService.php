<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Nominatim (OpenStreetMap) geocoding with timeout, error and empty-result handling. */
class GeocodingService
{
    /** @return array{ok: bool, results: list<array{name: string, lat: float, lng: float}>, error: ?string} */
    public function search(string $query): array
    {
        $query = trim($query);

        if (mb_strlen($query) < 3) {
            return ['ok' => false, 'results' => [], 'error' => 'Enter at least 3 characters.'];
        }

        $key = 'geocode:'.md5(mb_strtolower($query));
        if ($cached = Cache::get($key)) {
            return $cached;
        }

        try {
            $response = Http::withHeaders(['User-Agent' => config('rekacy.geocoding.user_agent')])
                ->timeout(config('rekacy.geocoding.timeout'))
                ->retry(1, 200, throw: false)
                ->get(config('rekacy.geocoding.url'), ['q' => $query, 'format' => 'jsonv2', 'limit' => 5])
                ->throw();

            $results = collect($response->json() ?? [])->map(fn ($r) => [
                'name' => (string) ($r['display_name'] ?? ''),
                'lat' => (float) ($r['lat'] ?? 0),
                'lng' => (float) ($r['lon'] ?? 0),
            ])->values()->all();

            $payload = [
                'ok' => true,
                'results' => $results,
                'error' => $results ? null : 'No places found for this search.',
            ];

            if ($results) {
                Cache::put($key, $payload, now()->addDay());
            }

            return $payload;
        } catch (ConnectionException $e) {
            Log::warning('Geocoding timeout/connection failure: '.$e->getMessage());

            return ['ok' => false, 'results' => [], 'error' => 'Geocoding service is unreachable. Enter coordinates manually.'];
        } catch (RequestException $e) {
            Log::warning('Geocoding error response: '.$e->getMessage());

            return ['ok' => false, 'results' => [], 'error' => 'Geocoding service returned an error. Try again later.'];
        }
    }
}
