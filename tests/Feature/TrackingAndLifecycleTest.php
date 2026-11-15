<?php

namespace Tests\Feature;

use App\Enums\ShipmentStatus;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TrackingAndLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-03 10:00:00');
    }

    private function operatorAndShipment(): array
    {
        $op = User::factory()->operator()->create();
        Sanctum::actingAs($op);

        return [$op, Shipment::factory()->create(['expected_arrival_at' => now()->addDays(20)])];
    }

    public function test_full_valid_lifecycle_creates_history_without_overwriting(): void
    {
        [, $s] = $this->operatorAndShipment();

        foreach (['BOOKED', 'PICKED_UP', 'IN_TRANSIT', 'DELIVERED'] as $i => $status) {
            Carbon::setTestNow(now()->addHours(5));
            $this->postJson("/api/shipments/{$s->id}/tracking", ['status' => $status, 'description' => "step {$i}"])->assertCreated();
        }

        $this->assertSame(ShipmentStatus::Delivered, $s->fresh()->status);
        $this->assertNotNull($s->fresh()->delivered_at);
        $this->assertSame(4, $s->trackingEvents()->count());
        $this->getJson("/api/shipments/{$s->id}/tracking")->assertOk()->assertJsonCount(4, 'data');
    }

    public function test_skipping_a_status_is_rejected(): void
    {
        [, $s] = $this->operatorAndShipment();

        $this->postJson("/api/shipments/{$s->id}/tracking", ['status' => 'IN_TRANSIT'])
            ->assertStatus(422)->assertJsonPath('errors.business_rule.0', fn ($m) => str_contains($m, 'Invalid status transition'));
        $this->assertSame(ShipmentStatus::Created, $s->fresh()->status);
        $this->assertSame(0, $s->trackingEvents()->count());
    }

    public function test_terminal_status_cannot_return_or_receive_events(): void
    {
        [, $s] = $this->operatorAndShipment();
        $s->forceFill(['status' => ShipmentStatus::Delivered, 'delivered_at' => now()])->save();

        $this->postJson("/api/shipments/{$s->id}/tracking", ['status' => 'CREATED'])->assertStatus(422);
        $this->postJson("/api/shipments/{$s->id}/tracking", ['description' => 'late note'])->assertStatus(422);
    }

    public function test_invalid_status_value_fails_validation(): void
    {
        [, $s] = $this->operatorAndShipment();

        $this->postJson("/api/shipments/{$s->id}/tracking", ['status' => 'TELEPORTED'])->assertStatus(422)->assertJsonValidationErrors('status');
        $this->postJson("/api/shipments/{$s->id}/tracking", [])->assertStatus(422)->assertJsonValidationErrors('description');
        $this->postJson("/api/shipments/{$s->id}/tracking", ['latitude' => 95, 'longitude' => 10])->assertStatus(422)->assertJsonValidationErrors('latitude');
    }

    public function test_event_cannot_be_older_than_latest_or_in_the_future(): void
    {
        [, $s] = $this->operatorAndShipment();

        $this->postJson("/api/shipments/{$s->id}/tracking", ['status' => 'BOOKED', 'occurred_at' => now()->toDateTimeString()])->assertCreated();
        $this->postJson("/api/shipments/{$s->id}/tracking", ['description' => 'old', 'occurred_at' => now()->subHour()->toDateTimeString()])->assertStatus(422);
        $this->postJson("/api/shipments/{$s->id}/tracking", ['description' => 'future', 'occurred_at' => now()->addHours(3)->toDateTimeString()])->assertStatus(422);
    }

    public function test_current_location_is_latest_event_with_coordinates(): void
    {
        [, $s] = $this->operatorAndShipment();
        $port = Location::factory()->create(['latitude' => 1.5, 'longitude' => 103.5]);

        $this->postJson("/api/shipments/{$s->id}/tracking", ['status' => 'BOOKED', 'latitude' => 10, 'longitude' => 20, 'occurred_at' => now()->toDateTimeString()])->assertCreated();
        Carbon::setTestNow(now()->addHour());
        $this->postJson("/api/shipments/{$s->id}/tracking", ['location_id' => $port->id, 'description' => 'At port'])->assertCreated();
        Carbon::setTestNow(now()->addHour());
        $this->postJson("/api/shipments/{$s->id}/tracking", ['description' => 'No coordinates here'])->assertCreated();

        $this->getJson("/api/shipments/{$s->id}")->assertOk()
            ->assertJsonPath('data.current_location.latitude', 1.5)
            ->assertJsonPath('data.current_location.longitude', 103.5);
    }

    public function test_customers_cannot_add_tracking_events(): void
    {
        $c = User::factory()->create();
        $s = Shipment::factory()->create(['customer_id' => $c->id]);
        Sanctum::actingAs($c);

        $this->postJson("/api/shipments/{$s->id}/tracking", ['status' => 'BOOKED'])->assertForbidden();
    }

    public function test_status_change_notifies_customer_and_writes_audit_log(): void
    {
        $c = User::factory()->create();
        $s = Shipment::factory()->create(['customer_id' => $c->id]);
        Sanctum::actingAs(User::factory()->operator()->create());

        $this->postJson("/api/shipments/{$s->id}/tracking", ['status' => 'BOOKED'])->assertCreated();

        $this->assertSame(1, $c->notifications()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'shipment.status_changed', 'entity_id' => $s->id]);
    }
}
