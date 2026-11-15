<?php

namespace Tests\Unit;

use App\Enums\ExceptionStatus;
use App\Enums\Severity;
use App\Enums\ShipmentPriority;
use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Services\RiskService;
use Tests\TestCase;

class RiskServiceTest extends TestCase
{
    private function shipment(array $exceptions = [], bool $delayed = false, ?Severity $sev = null, ShipmentPriority $p = ShipmentPriority::Normal): Shipment
    {
        $s = new Shipment;
        $s->status = ShipmentStatus::InTransit;
        $s->is_delayed = $delayed;
        $s->delay_severity = $sev;
        $s->priority = $p;
        $s->setRelation('exceptions', collect(array_map(function ($row) {
            $e = new ShipmentException;
            $e->severity = $row[0];
            $e->status = $row[1];

            return $e;
        }, $exceptions)));

        return $s;
    }

    public function test_scoring_and_levels(): void
    {
        $svc = new RiskService;

        $this->assertSame(0, $svc->score($this->shipment()));
        $this->assertSame(3, $svc->score($this->shipment([], true, Severity::High)));
        $this->assertSame(5, $svc->score($this->shipment([[Severity::High, ExceptionStatus::Open]], true, Severity::Medium)));
        $this->assertSame(2, $svc->score($this->shipment([[Severity::High, ExceptionStatus::Resolved], [Severity::Medium, ExceptionStatus::Open]])), 'resolved exceptions do not count');
        $this->assertSame(1, $svc->score($this->shipment([], false, null, ShipmentPriority::Urgent)));

        $this->assertSame('NORMAL', $svc->level(0));
        $this->assertSame('WATCH', $svc->level(1));
        $this->assertSame('ELEVATED', $svc->level(2));
        $this->assertSame('HIGH', $svc->level(4));
        $this->assertSame('CRITICAL', $svc->level(6));
    }
}
