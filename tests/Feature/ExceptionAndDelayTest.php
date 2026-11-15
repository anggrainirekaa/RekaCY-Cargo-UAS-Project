<?php

namespace Tests\Feature;

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExceptionAndDelayTest extends TestCase
{
    use RefreshDatabase;

    public function test_exception_lifecycle_and_timestamps(): void
    {
        $s = Shipment::factory()->status(ShipmentStatus::InTransit)->create();
        Sanctum::actingAs(User::factory()->operator()->create());

        $id = $this->postJson("/api/shipments/{$s->id}/exceptions", ['type' => 'CUSTOMS_HOLD', 'description' => 'Documents under review'])
            ->assertCreated()->assertJsonPath('data.status', 'OPEN')->assertJsonPath('data.severity', 'MEDIUM')->json('data.id');

        $this->patchJson("/api/exceptions/{$id}", ['status' => 'INVESTIGATING'])->assertOk()->assertJsonPath('data.status', 'INVESTIGATING');
        $this->patchJson("/api/exceptions/{$id}", ['status' => 'RESOLVED'])->assertStatus(422)->assertJsonValidationErrors('resolution_note');
        $this->patchJson("/api/exceptions/{$id}", ['status' => 'RESOLVED', 'resolution_note' => 'Cleared'])->assertOk();

        $this->assertNotNull(\App\Models\ShipmentException::find($id)->resolved_at);
        $this->patchJson("/api/exceptions/{$id}", ['status' => 'OPEN'])->assertStatus(422);
        $this->assertDatabaseHas('tracking_events', ['shipment_id' => $s->id, 'event_type' => 'EXCEPTION', 'is_exception' => true]);
    }

    public function test_invalid_exception_type_is_rejected_and_customers_cannot_open(): void
    {
        $c = User::factory()->create();
        $s = Shipment::factory()->create(['customer_id' => $c->id]);

        Sanctum::actingAs(User::factory()->operator()->create());
        $this->postJson("/api/shipments/{$s->id}/exceptions", ['type' => 'ALIENS', 'description' => 'x'])->assertStatus(422);

        Sanctum::actingAs($c);
        $this->postJson("/api/shipments/{$s->id}/exceptions", ['type' => 'DAMAGED', 'description' => 'x'])->assertForbidden();
        $this->getJson("/api/shipments/{$s->id}/exceptions")->assertOk();
    }

    public function test_customer_cannot_read_exceptions_of_other_customers(): void
    {
        $other = Shipment::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/shipments/{$other->id}/exceptions")->assertForbidden();
    }

    public function test_delay_detection_command_flags_overdue_shipments_and_notifies(): void
    {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $customer = User::factory()->create();
        User::factory()->operator()->create();
        $late = Shipment::factory()->status(ShipmentStatus::InTransit)->create([
            'customer_id' => $customer->id, 'expected_arrival_at' => now()->subHours(30),
        ]);
        $ok = Shipment::factory()->status(ShipmentStatus::InTransit)->create(['expected_arrival_at' => now()->addDays(3)]);

        $this->artisan('rekacy:detect-delays')->assertSuccessful();

        $this->assertTrue($late->fresh()->is_delayed);
        $this->assertSame('HIGH', $late->fresh()->delay_severity->value);
        $this->assertFalse($ok->fresh()->is_delayed);
        $this->assertDatabaseHas('tracking_events', ['shipment_id' => $late->id, 'event_type' => 'DELAY_DETECTED']);
        $this->assertGreaterThanOrEqual(1, $customer->notifications()->count());

        $this->artisan('rekacy:detect-delays')->assertSuccessful();
        $this->assertSame(1, $late->trackingEvents()->where('event_type', 'DELAY_DETECTED')->count(), 'no duplicate delay events');
    }

    public function test_dashboard_numbers_come_from_the_database_and_are_scoped(): void
    {
        $c = User::factory()->create();
        Shipment::factory()->count(2)->status(ShipmentStatus::InTransit)->create(['customer_id' => $c->id]);
        Shipment::factory()->status(ShipmentStatus::Delivered)->create(['customer_id' => $c->id, 'delivered_at' => now(), 'expected_arrival_at' => now()->addDay()]);
        Shipment::factory()->count(5)->create();

        Sanctum::actingAs($c);
        $this->getJson('/api/dashboard')->assertOk()
            ->assertJsonPath('totals.total', 3)->assertJsonPath('totals.in_transit', 2)->assertJsonPath('totals.delivered', 1)
            ->assertJsonPath('kpis.on_time_rate', 100);

        Sanctum::actingAs(User::factory()->admin()->create());
        $this->getJson('/api/dashboard')->assertJsonPath('totals.total', 8);
    }

    public function test_public_tracking_hides_customer_data_and_coordinates(): void
    {
        $s = Shipment::factory()->create();

        $this->get('/track?number='.$s->shipment_number)->assertOk()->assertSee($s->shipment_number)
            ->assertDontSee($s->customer->email)->assertDontSee($s->customer->name);
        $this->get('/track?number=NOPE')->assertOk()->assertSee('No shipment found');
    }

    public function test_pagination_filter_and_sorting_work_server_side(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        Shipment::factory()->count(20)->create();
        Shipment::factory()->status(ShipmentStatus::Booked)->create(['is_delayed' => true, 'delay_hours' => 5]);

        $this->getJson('/api/shipments?per_page=10')->assertOk()->assertJsonCount(10, 'data')->assertJsonPath('meta.total', 21);
        $this->getJson('/api/shipments?status=BOOKED')->assertJsonCount(1, 'data');
        $this->getJson('/api/shipments?delay=delayed')->assertJsonCount(1, 'data');
        $this->getJson('/api/shipments?sort=bogus')->assertStatus(422);
    }
}
