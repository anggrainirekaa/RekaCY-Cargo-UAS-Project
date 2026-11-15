<?php

namespace App\Services;

use App\Enums\ExceptionStatus;
use App\Enums\Severity;
use App\Enums\ShipmentPriority;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Deterministic shipment risk score (no ML):
 *   + delay severity            LOW 1 · MEDIUM 2 · HIGH 3   (while the shipment is active)
 *   + each open exception       LOW 1 · MEDIUM 2 · HIGH 3
 *   + URGENT priority           +1
 * Level: ≥6 CRITICAL · ≥4 HIGH · ≥2 ELEVATED · ≥1 WATCH · 0 NORMAL
 */
class RiskService
{
    private const POINTS = ['LOW' => 1, 'MEDIUM' => 2, 'HIGH' => 3];

    public function score(Shipment $shipment): int
    {
        $score = 0;

        if ($shipment->is_delayed && $shipment->delay_severity && ! $shipment->status->isTerminal()) {
            $score += self::POINTS[$shipment->delay_severity->value];
        }

        foreach ($shipment->exceptions as $e) {
            if ($e->status !== ExceptionStatus::Resolved) {
                $score += self::POINTS[$e->severity->value];
            }
        }

        if ($shipment->priority === ShipmentPriority::Urgent) {
            $score++;
        }

        return $score;
    }

    public function level(int $score): string
    {
        return match (true) {
            $score >= 6 => 'CRITICAL', $score >= 4 => 'HIGH', $score >= 2 => 'ELEVATED', $score >= 1 => 'WATCH', default => 'NORMAL',
        };
    }

    /** @return array{top: Collection, distribution: array<string,int>} */
    public function overview(User $user, int $top = 6): array
    {
        $rows = Shipment::query()->visibleTo($user)->active()
            ->with(['exceptions' => fn ($q) => $q->where('status', '!=', ExceptionStatus::Resolved->value), 'origin:id,city', 'destination:id,city'])
            ->get()
            ->map(fn (Shipment $s) => ['shipment' => $s, 'score' => $score = $this->score($s), 'level' => $this->level($score)]);

        $dist = array_fill_keys(['CRITICAL', 'HIGH', 'ELEVATED', 'WATCH', 'NORMAL'], 0);
        foreach ($rows as $r) {
            $dist[$r['level']]++;
        }

        return ['top' => $rows->where('score', '>', 0)->sortByDesc('score')->take($top)->values(), 'distribution' => $dist];
    }

    public static function levelClasses(string $level): string
    {
        return match ($level) {
            'CRITICAL' => 'bg-red-500/10 text-red-300 ring-red-400/30',
            'HIGH' => 'bg-orange-500/10 text-orange-300 ring-orange-400/30',
            'ELEVATED' => 'bg-amber-500/10 text-amber-300 ring-amber-400/30',
            'WATCH' => 'bg-cyan-500/10 text-cyan-300 ring-cyan-400/30',
            default => 'bg-slate-100 text-slate-700 ring-slate-200',
        };
    }
}
