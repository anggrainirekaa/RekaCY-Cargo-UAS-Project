<?php

namespace Tests\Feature;

use App\Enums\ShipmentStatus;
use App\Models\Carrier;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ShipmentAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function payload(User $customer): array
    {
        return [
            'customer_id' => $customer->id,
            'carrier_id' => Carrier::factory()->create()->id,
            'origin_id' => Location::factory()->create()->id,
            'destination_id' => Location::factory()->create()->id,
            'cargo_type' => 'Textiles',
            'weight_kg' => 1500,
            'expected_departure_at' => now()->addDay()->toDateTimeString(),
            'expected_arrival_at' => now()->addDays(20)->toDateTimeString(),
            'items' => [['description' => 'Rolls', 'quantity' => 10]],
        ];
    }

    public function test_customer_only_lists_own_shipments(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $mine = Shipment::factory()->create(['customer_id' => $a->id]);
        $theirs = Shipment::factory()->create(['customer_id' => $b->id]);

        $this->actingAs($a)->get('/shipments')->assertOk()->assertSee($mine->shipment_number)->assertDontSee($theirs->shipment_number);

        Sanctum::actingAs($a);
        $this->getJson('/api/shipments')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_customer_cannot_open_someone_elses_shipment(): void
    {
        $a = User::factory()->create();
        $other = Shipment::factory()->create();

        $this->actingAs($a)->get("/shipments/{$other->id}")->assertForbidden();
        Sanctum::actingAs($a);
        $this->getJson("/api/shipments/{$other->id}")->assertForbidden();
        $this->getJson("/api/shipments/{$other->id}/tracking")->assertForbidden();
    }

    public function test_customer_cannot_create_shipments(): void
    {
        $c = User::factory()->create();
        Sanctum::actingAs($c);

        $this->postJson('/api/shipments', $this->payload($c))->assertForbidden();
        $this->actingAs($c)->get('/shipments/create')->assertForbidden();
    }

    public function test_operator_creates_shipment_with_first_event_and_validation(): void
    {
        $customer = User::factory()->create();
        Sanctum::actingAs(User::factory()->operator()->create());

        $res = $this->postJson('/api/shipments', $this->payload($customer))->assertCreated();
        $this->assertSame('CREATED', $res->json('data.status'));
        $this->assertDatabaseCount('tracking_events', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'shipment.created']);

        $this->postJson('/api/shipments', [])->assertStatus(422)->assertJsonValidationErrors(['customer_id', 'items']);

        $bad = $this->payload($customer);
        $bad['destination_id'] = $bad['origin_id'];
        $this->postJson('/api/shipments', $bad)->assertStatus(422)->assertJsonValidationErrors('destination_id');
    }

    public function test_client_cannot_inject_status_or_owner_fields(): void
    {
        $customer = User::factory()->create();
        Sanctum::actingAs(User::factory()->operator()->create());

        $payload = $this->payload($customer) + ['status' => 'DELIVERED', 'is_delayed' => true, 'shipment_number' => 'HACK-1'];
        $res = $this->postJson('/api/shipments', $payload)->assertCreated();

        $this->assertSame('CREATED', $res->json('data.status'));
        $this->assertNotSame('HACK-1', $res->json('data.shipment_number'));
    }

    public function test_only_admin_can_delete_and_only_created_or_cancelled(): void
    {
        $s = Shipment::factory()->create();

        Sanctum::actingAs(User::factory()->operator()->create());
        $this->deleteJson("/api/shipments/{$s->id}")->assertForbidden();

        Sanctum::actingAs(User::factory()->admin()->create());
        $moving = Shipment::factory()->status(ShipmentStatus::InTransit)->create();
        $this->deleteJson("/api/shipments/{$moving->id}")->assertStatus(422);
        $this->deleteJson("/api/shipments/{$s->id}")->assertNoContent();
        $this->assertSoftDeleted('shipments', ['id' => $s->id]);
    }

    public function test_customer_cannot_reach_staff_pages(): void
    {
        $c = User::factory()->create();
        foreach (['/exceptions', '/carriers', '/locations', '/users', '/audit-log', '/operations', '/eta', '/analytics', '/settings'] as $url) {
            $this->actingAs($c)->get($url)->assertForbidden();
        }
    }

    public function test_all_roles_can_open_the_dashboard(): void
    {
        foreach ([User::factory()->admin(), User::factory()->operator(), User::factory()] as $factory) {
            $this->actingAs($factory->create())->get('/dashboard')->assertOk();
        }
    }
}
