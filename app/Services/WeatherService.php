<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Open-Meteo current conditions at the shipment's last known position.
 * Context information only — it is NOT used to predict or justify delays.
 */
class WeatherService
{
    /** @return array{ok: bool, data: ?array<string, mixed>, error: ?string} */
    public function current(float $lat, float $lng): array
    {
        $key = sprintf('weather:%.2f:%.2f', $lat, $lng);
        if ($cached = Cache::get($key)) {
            return $cached;
        }

        try {
            $json = Http::timeout(config('rekacy.weather.timeout'))
                ->retry(1, 200, throw: false)
                ->get(config('rekacy.weather.url'), [
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'current' => 'temperature_2m,wind_speed_10m,weather_code',
                ])->throw()->json();

            $c = $json['current'] ?? null;

            if (! is_array($c) || ! isset($c['temperature_2m'])) {
                return ['ok' => false, 'data' => null, 'error' => 'Weather provider returned no data for this position.'];
            }

            $payload = ['ok' => true, 'error' => null, 'data' => [
                'temperature_c' => $c['temperature_2m'],
                'wind_kmh' => $c['wind_speed_10m'] ?? null,
                'summary' => $this->describe((int) ($c['weather_code'] ?? -1)),
                'observed_at' => $c['time'] ?? null,
                'source' => 'Open-Meteo',
            ]];

            Cache::put($key, $payload, now()->addMinutes(20));

            return $payload;
        } catch (ConnectionException $e) {
            Log::warning('Weather timeout/connection failure: '.$e->getMessage());

            return ['ok' => false, 'data' => null, 'error' => 'Weather service is unreachable right now.'];
        } catch (RequestException $e) {
            Log::warning('Weather error response: '.$e->getMessage());

            return ['ok' => false, 'data' => null, 'error' => 'Weather service returned an error.'];
        }
    }

    private function describe(int $code): string
    {
        return match (true) {
            $code === 0 => 'Clear sky',
            in_array($code, [1, 2, 3], true) => 'Partly cloudy',
            in_array($code, [45, 48], true) => 'Fog',
            $code >= 51 && $code <= 67 => 'Rain / drizzle',
            $code >= 71 && $code <= 77 => 'Snow',
            $code >= 80 && $code <= 82 => 'Rain showers',
            $code >= 95 => 'Thunderstorm',
            default => 'Unknown conditions',
        };
    }
}
