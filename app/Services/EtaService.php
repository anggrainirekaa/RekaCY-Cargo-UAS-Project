<?php

namespace App\Services;

use App\Enums\Severity;
use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use Carbon\CarbonInterface;

/**
 * Deterministic ETA / delay rule (no ML). Thresholds come from config('rekacy.delay').
 *
 *   reference = delivered_at                         (if delivered)
 *             = estimated_arrival_at ?? expected     (otherwise)
 *             = max(reference, now)                  (if still undelivered after expected arrival)
 *   delay     = reference - expected_arrival_at
 */
class EtaService
{
    public function evaluate(Shipment $shipment, ?CarbonInterface $now = null): DelayResult
    {
        $now ??= now();
        $expected = $shipment->expected_arrival_at;

        if (! $expected || $shipment->status === ShipmentStatus::Cancelled) {
            return new DelayResult(false, 0.0, null, false, 'No delay rule applies.');
        }

        if ($shipment->delivered_at) {
            $reference = $shipment->delivered_at;
            $basis = 'Actual delivery';
        } else {
            $reference = $shipment->estimated_arrival_at ?? $expected;
            $basis = 'Current ETA';

            if ($now->greaterThan($expected) && $now->greaterThan($reference)) {
                $reference = $now;
                $basis = 'Current time (expected arrival has passed)';
            }
        }

        $hours = ($reference->getTimestamp() - $expected->getTimestamp()) / 3600;

        if ($hours <= 0) {
            return new DelayResult(false, 0.0, null, false, "{$basis} is on or before the expected arrival — on time.");
        }

        $lowMax = (float) config('rekacy.delay.low_max_hours');
        $mediumMax = (float) config('rekacy.delay.medium_max_hours');
        $escalate = (float) config('rekacy.delay.escalate_hours');

        $severity = match (true) {
            $hours <= $lowMax => Severity::Low,
            $hours <= $mediumMax => Severity::Medium,
            default => Severity::High,
        };

        $rounded = round($hours, 2);
        $reason = sprintf(
            '%s is %s h after the expected arrival → %s (rule: LOW ≤ %s h, MEDIUM ≤ %s h, HIGH above).',
            $basis, rtrim(rtrim(number_format($rounded, 2, '.', ''), '0'), '.'), $severity->value,
            $this->fmt($lowMax), $this->fmt($mediumMax)
        );

        return new DelayResult(true, $rounded, $severity, $hours >= $escalate, $reason);
    }

    public function apply(Shipment $shipment, DelayResult $result): void
    {
        $shipment->is_delayed = $result->isDelayed;
        $shipment->delay_hours = $result->hours;
        $shipment->delay_severity = $result->severity;
    }

    private function fmt(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
    }
}
