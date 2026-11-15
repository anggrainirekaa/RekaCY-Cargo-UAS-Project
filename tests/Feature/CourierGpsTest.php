<?php

namespace Tests\Feature;

use App\Enums\AssignmentStatus;
use App\Enums\ShipmentStatus;
use App\Events\CourierLocationUpdated;
use App\Models\DeliveryAssignment;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CourierGpsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-09 10:00:00');
    }

    /** @return array{0: User, 1: DeliveryAssignment} */
    private function active(AssignmentStatus $st = AssignmentStatus::InTransit, bool $tracking = true): array
    {
        $u = User::factory()->courier()->create();
        $s = Shipment::factory()->status(ShipmentStatus::InTransit)->create([
            'destination_id' => Location::factory()->create(['latitude' => 3.60, 'longitude' => 98.70])->id,
        ]);
        $a = new DeliveryAssignment;
        $a->shipment_id = $s->id;
        $a->courier_id = $u->courier->id;
        $a->status = $st;
        $a->assigned_at = now();
        $a->save();
        $u->courier->forceFill(['is_tracking' => $tracking])->save();

        return [$u, $a];
    }

    private function point(DeliveryAssignment $a, array $o = []): array
    {
        return $o + ['assignment_id' => $a->id, 'latitude' => 3.5800, 'longitude' => 98.6700, 'accuracy' => 12.5, 'speed_kmh' => 28, 'recorded_at' => now()->toIso8601String()];
    }

    public function test_valid_point_is_stored_broadcast_and_returns_an_eta(): void
    {
        Event::fake([CourierLocationUpdated::class]);
        [$u, $a] = $this->active();
        Sanctum::actingAs($u);

        $this->postJson('/api/courier/location', $this->point($a))->assertCreated()->assertJsonPath('stored', true)->assertJsonStructure(['eta' => ['distance_km', 'minutes', 'eta_at', 'basis']]);

        $this->assertDatabaseHas('courier_locations', ['courier_id' => $u->courier->id, 'delivery_assignment_id' => $a->id, 'shipment_id' => $a->shipment_id, 'source' => 'device']);
        $this->assertNotNull($u->courier->fresh()->last_seen_at);
        Event::assertDispatched(CourierLocationUpdated::class, fn ($e) => $e->shipment->id === $a->shipment_id);
    }

    public function test_history_is_kept_for_every_point(): void
    {
        [$u, $a] = $this->active();
        Sanctum::actingAs($u);

        foreach ([0, 1, 2] as $i) {
            Carbon::setTestNow(now()->addSeconds(30));
            $this->postJson('/api/courier/location', $this->point($a, ['latitude' => 3.58 + $i * 0.002, 'recorded_at' => now()->toIso8601String()]))->assertCreated();
        }
        $this->assertSame(3, $a->locations()->count());
    }

    public function test_rejected_when_not_tracking_not_in_progress_or_not_owned(): void
    {
        [$u, $a] = $this->active(tracking: false);
        Sanctum::actingAs($u);
        $this->postJson('/api/courier/location', $this->point($a))->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'Tracking is not started'));

        [$u2, $assigned] = $this->active(AssignmentStatus::Assigned);
        Sanctum::actingAs($u2);
        $this->postJson('/api/courier/location', $this->point($assigned))->assertStatus(422);

        [$u3, $a3] = $this->active();
        Sanctum::actingAs($u3);
        $this->postJson('/api/courier/location', $this->point($a))->assertStatus(422); // someone else's task
        $this->assertSame(0, \App\Models\CourierLocation::count());
    }

    public function test_finished_tasks_do_not_accept_gps(): void
    {
        [$u, $a] = $this->active(AssignmentStatus::Completed);
        Sanctum::actingAs($u);

        $this->postJson('/api/courier/location', $this->point($a))->assertStatus(422);
    }

    public function test_payload_validation(): void
    {
        [$u, $a] = $this->active();
        Sanctum::actingAs($u);

        $this->postJson('/api/courier/location', $this->point($a, ['latitude' => 95]))->assertStatus(422)->assertJsonValidationErrors('latitude');
        $this->postJson('/api/courier/location', $this->point($a, ['longitude' => 181]))->assertStatus(422)->assertJsonValidationErrors('longitude');
        $this->postJson('/api/courier/location', $this->point($a, ['accuracy' => -1]))->assertStatus(422)->assertJsonValidationErrors('accuracy');
        $this->postJson('/api/courier/location', $this->point($a, ['source' => 'hacked']))->assertStatus(422)->assertJsonValidationErrors('source');
        $this->postJson('/api/courier/location', ['latitude' => 1, 'longitude' => 1])->assertStatus(422)->assertJsonValidationErrors('assignment_id');
        $this->postJson('/api/courier/location', $this->point($a, ['recorded_at' => now()->addMinutes(10)->toIso8601String()]))->assertStatus(422);
        $this->postJson('/api/courier/location', $this->point($a, ['recorded_at' => now()->subMinutes(30)->toIso8601String()]))->assertStatus(422);
    }

    public function test_only_couriers_can_post_locations(): void
    {
        [, $a] = $this->active();
        $this->postJson('/api/courier/location', $this->point($a))->assertStatus(401);

        foreach ([User::factory()->create(), User::factory()->operator()->create(), User::factory()->admin()->create()] as $other) {
            Sanctum::actingAs($other);
            $this->postJson('/api/courier/location', $this->point($a))->assertForbidden();
        }
    }

    public function test_duplicate_points_are_skipped(): void
    {
        [$u, $a] = $this->active();
        Sanctum::actingAs($u);

        $this->postJson('/api/courier/location', $this->point($a))->assertCreated();
        $this->postJson('/api/courier/location', $this->point($a))->assertOk()->assertJsonPath('stored', false);
        $this->assertSame(1, $a->locations()->count());
    }

    public function test_gps_endpoint_is_rate_limited(): void
    {
        [$u, $a] = $this->active();
        Sanctum::actingAs($u);

        $last = 200;
        for ($i = 0; $i < 41; $i++) {
            $last = $this->postJson('/api/courier/location', $this->point($a, ['latitude' => 3.5 + $i * 0.001]))->status();
        }
        $this->assertSame(429, $last);
    }

    public function test_tracking_start_requires_a_delivery_in_progress_and_stop_works(): void
    {
        $u = User::factory()->courier()->create();
        Sanctum::actingAs($u);
        $this->postJson('/api/courier/tracking/start')->assertStatus(422);
        $this->assertFalse($u->courier->fresh()->is_tracking);

        [$u2, $a] = $this->active(AssignmentStatus::PickedUp, tracking: false);
        Sanctum::actingAs($u2);
        $this->postJson('/api/courier/tracking/start')->assertOk()->assertJsonPath('tracking', true);
        $this->assertTrue($u2->courier->fresh()->is_tracking);
        $this->postJson('/api/courier/tracking/stop')->assertOk()->assertJsonPath('tracking', false);
        $this->assertFalse($u2->courier->fresh()->is_tracking);
        $this->assertDatabaseHas('audit_logs', ['action' => 'courier.tracking_started']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'courier.tracking_stopped']);
    }

    public function test_demo_points_are_labelled(): void
    {
        [$u, $a] = $this->active();
        Sanctum::actingAs($u);
        $this->postJson('/api/courier/location', $this->point($a, ['source' => 'demo']))->assertCreated();

        $this->assertDatabaseHas('courier_locations', ['source' => 'demo']);
        $s = $a->shipment;
        Sanctum::actingAs($s->customer);
        $this->getJson("/api/shipments/{$s->id}/live-location")->assertOk()->assertJsonPath('location.source', 'demo');
    }

    public function test_live_state_active_stale_and_privacy_rules(): void
    {
        [$u, $a] = $this->active();
        $s = $a->shipment;
        Sanctum::actingAs($u);
        $this->postJson('/api/courier/location', $this->point($a))->assertCreated();

        // owner customer sees it
        Sanctum::actingAs($s->customer);
        $this->getJson("/api/shipments/{$s->id}/live-location")->assertOk()->assertJsonPath('state', 'ACTIVE')->assertJsonPath('location.latitude', 3.58);

        // another customer cannot
        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/shipments/{$s->id}/live-location")->assertForbidden();

        // staff can; a courier cannot use this endpoint for shipments
        Sanctum::actingAs(User::factory()->operator()->create());
        $this->getJson("/api/shipments/{$s->id}/live-location")->assertOk()->assertJsonPath('state', 'ACTIVE');
        Sanctum::actingAs($u);
        $this->getJson("/api/shipments/{$s->id}/live-location")->assertForbidden();

        // signal lost after 90 s of silence
        Carbon::setTestNow(now()->addMinutes(3));
        Sanctum::actingAs($s->customer);
        $this->getJson("/api/shipments/{$s->id}/live-location")->assertJsonPath('state', 'STALE')->assertJsonPath('message', fn ($m) => str_contains($m, 'Signal lost'));

        // once the delivery is completed nothing about the courier's position is exposed
        $a->forceFill(['status' => AssignmentStatus::Completed])->save();
        $this->getJson("/api/shipments/{$s->id}/live-location")->assertOk()->assertJsonPath('state', 'NONE')->assertJsonPath('location', null)->assertJsonPath('courier', null);
    }

    public function test_eta_uses_measured_speed_with_clamping(): void
    {
        [$u, $a] = $this->active();
        Sanctum::actingAs($u);
        // ~2.2 km in 5 minutes ≈ 26 km/h
        $this->postJson('/api/courier/location', $this->point($a, ['latitude' => 3.5800, 'recorded_at' => now()->subMinutes(5)->toIso8601String()]))->assertCreated();
        $res = $this->postJson('/api/courier/location', $this->point($a, ['latitude' => 3.5995, 'recorded_at' => now()->toIso8601String()]))->assertCreated();

        $eta = $res->json('eta');
        $this->assertSame('measured speed', $eta['basis']);
        $this->assertGreaterThanOrEqual(15, $eta['speed_kmh']);
        $this->assertLessThanOrEqual(60, $eta['speed_kmh']);
        $this->assertGreaterThanOrEqual(0, $eta['minutes']);
    }
}
