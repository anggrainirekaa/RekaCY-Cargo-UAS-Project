<?php

namespace Tests\Unit;

use App\Enums\Severity;
use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Services\EtaService;
use Carbon\Carbon;
use Tests\TestCase;

class EtaServiceTest extends TestCase
{
    private Carbon $expected;

    protected function setUp(): void
    {
        parent::setUp();
        $this->expected = Carbon::parse('2026-11-10 12:00:00');
    }

    private function shipment(?Carbon $eta = null, ?Carbon $delivered = null, ShipmentStatus $status = ShipmentStatus::InTransit): Shipment
    {
        $s = new Shipment(['expected_arrival_at' => $this->expected, 'estimated_arrival_at' => $eta]);
        $s->status = $status;
        $s->delivered_at = $delivered;

        return $s;
    }

    private function eval(Shipment $s, string $now = '2026-11-03 00:00:00')
    {
        return (new EtaService)->evaluate($s, Carbon::parse($now));
    }

    public function test_no_eta_and_before_expected_is_on_time(): void
    {
        $r = $this->eval($this->shipment());
        $this->assertFalse($r->isDelayed);
        $this->assertNull($r->severity);
    }

    public function test_eta_earlier_or_equal_is_on_time(): void
    {
        $this->assertFalse($this->eval($this->shipment($this->expected->copy()))->isDelayed);
        $this->assertFalse($this->eval($this->shipment($this->expected->copy()->subHours(5)))->isDelayed);
    }

    public function test_severity_boundaries(): void
    {
        $cases = [[3, Severity::Low], [6, Severity::Low], [6.5, Severity::Medium], [24, Severity::Medium], [24.5, Severity::High], [72, Severity::High]];

        foreach ($cases as [$hours, $severity]) {
            $r = $this->eval($this->shipment($this->expected->copy()->addSeconds((int) ($hours * 3600))));
            $this->assertTrue($r->isDelayed, "{$hours}h should be delayed");
            $this->assertSame($severity, $r->severity, "{$hours}h");
            $this->assertEquals($hours, $r->hours);
        }
    }

    public function test_thresholds_are_configurable(): void
    {
        config(['rekacy.delay.low_max_hours' => 2, 'rekacy.delay.medium_max_hours' => 4]);

        $this->assertSame(Severity::Medium, $this->eval($this->shipment($this->expected->copy()->addHours(3)))->severity);
        $this->assertSame(Severity::High, $this->eval($this->shipment($this->expected->copy()->addHours(5)))->severity);
    }

    public function test_escalation_uses_group_threshold(): void
    {
        config(['rekacy.delay.escalate_hours' => 12]);

        $this->assertFalse($this->eval($this->shipment($this->expected->copy()->addHours(11)))->escalate);
        $this->assertTrue($this->eval($this->shipment($this->expected->copy()->addHours(12)))->escalate);
    }

    public function test_undelivered_shipment_past_expected_arrival_is_delayed_from_now(): void
    {
        $r = $this->eval($this->shipment(), '2026-11-10 22:00:00'); // 10h after expected
        $this->assertTrue($r->isDelayed);
        $this->assertSame(Severity::Medium, $r->severity);
        $this->assertEquals(10.0, $r->hours);
    }

    public function test_delivered_shipments_use_actual_delivery_time(): void
    {
        $late = $this->shipment(null, $this->expected->copy()->addHours(30), ShipmentStatus::Delivered);
        $this->assertSame(Severity::High, $this->eval($late, '2027-01-01 00:00:00')->severity);

        $early = $this->shipment($this->expected->copy()->addHours(30), $this->expected->copy()->subHours(2), ShipmentStatus::Delivered);
        $this->assertFalse($this->eval($early, '2027-01-01 00:00:00')->isDelayed);
    }

    public function test_cancelled_shipments_are_never_delayed(): void
    {
        $r = $this->eval($this->shipment($this->expected->copy()->addDays(5), null, ShipmentStatus::Cancelled));
        $this->assertFalse($r->isDelayed);
    }

    public function test_reason_explains_the_values_used(): void
    {
        $r = $this->eval($this->shipment($this->expected->copy()->addHours(7)));
        $this->assertStringContainsString('7 h', $r->reason);
        $this->assertStringContainsString('MEDIUM', $r->reason);
    }
}
