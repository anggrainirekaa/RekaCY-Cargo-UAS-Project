<?php

namespace Tests\Feature;

use App\Enums\AssignmentStatus;
use App\Enums\ShipmentStatus;
use App\Models\DeliveryAssignment;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CourierWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function booked(array $o = []): Shipment
    {
        return Shipment::factory()->status(ShipmentStatus::Booked)->create($o);
    }

    private function task(User $courierUser, ShipmentStatus $shipment = ShipmentStatus::Booked, AssignmentStatus $status = AssignmentStatus::Assigned): DeliveryAssignment
    {
        $s = Shipment::factory()->status($shipment)->create();
        $a = new DeliveryAssignment;
        $a->shipment_id = $s->id;
        $a->courier_id = $courierUser->courier->id;
        $a->status = $status;
        $a->assigned_at = now();
        $a->save();

        return $a;
    }

    public function test_staff_assigns_courier_and_everyone_is_notified(): void
    {
        $courier = User::factory()->courier()->create();
        $s = $this->booked();
        $op = User::factory()->operator()->create();

        $this->actingAs($op)->post("/shipments/{$s->id}/assign", ['courier_id' => $courier->courier->id, 'notes' => 'Fragile'])->assertRedirect();

        $this->assertDatabaseHas('delivery_assignments', ['shipment_id' => $s->id, 'courier_id' => $courier->courier->id, 'status' => 'ASSIGNED', 'assigned_by' => $op->id]);
        $this->assertDatabaseHas('tracking_events', ['shipment_id' => $s->id, 'event_type' => 'COURIER_ASSIGNED']);
        $this->assertSame(1, $courier->notifications()->count());
        $this->assertSame(1, $s->customer->notifications()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'assignment.created']);
    }

    public function test_assignment_rules(): void
    {
        $courier = User::factory()->courier()->create();
        Sanctum::actingAs(User::factory()->operator()->create());

        $created = Shipment::factory()->create(); // status CREATED
        $this->postJson("/api/shipments/{$created->id}/assign", ['courier_id' => $courier->courier->id])->assertStatus(422);

        $s = $this->booked();
        $this->postJson("/api/shipments/{$s->id}/assign", ['courier_id' => 9999])->assertStatus(422)->assertJsonValidationErrors('courier_id');
        $this->postJson("/api/shipments/{$s->id}/assign", ['courier_id' => $courier->courier->id])->assertCreated();
        $this->postJson("/api/shipments/{$s->id}/assign", ['courier_id' => $courier->courier->id])->assertStatus(422); // already holds it

        $other = User::factory()->courier()->create();
        $this->postJson("/api/shipments/{$s->id}/assign", ['courier_id' => $other->courier->id])->assertCreated();
        $this->assertSame('CANCELLED', $s->assignments()->where('courier_id', $courier->courier->id)->first()->status->value);
        $this->assertSame(1, $s->assignments()->active()->count());
    }

    public function test_only_staff_can_assign(): void
    {
        $courier = User::factory()->courier()->create();
        $s = $this->booked();

        $this->actingAs($courier)->post("/shipments/{$s->id}/assign", ['courier_id' => $courier->courier->id])->assertForbidden();
        $this->actingAs(User::factory()->create())->post("/shipments/{$s->id}/assign", ['courier_id' => $courier->courier->id])->assertForbidden();
        $this->assertSame(0, DeliveryAssignment::count());
    }

    public function test_courier_only_sees_and_acts_on_own_tasks(): void
    {
        $a = User::factory()->courier()->create();
        $b = User::factory()->courier()->create();
        $mine = $this->task($a);
        $theirs = $this->task($b);

        $this->actingAs($a)->get('/courier')->assertOk()->assertSee($mine->shipment->shipment_number)->assertDontSee($theirs->shipment->shipment_number);
        $this->actingAs($a)->get("/courier/tasks/{$theirs->id}")->assertForbidden();
        $this->actingAs($a)->post("/courier/tasks/{$theirs->id}/accept")->assertForbidden();
        $this->assertSame(AssignmentStatus::Assigned, $theirs->fresh()->status);
    }

    public function test_courier_is_kept_out_of_staff_and_customer_areas(): void
    {
        $c = User::factory()->courier()->create();
        $this->actingAs($c)->get('/dashboard')->assertRedirect('/courier');
        foreach (['/exceptions', '/operations', '/carriers', '/users', '/reports', '/couriers'] as $url) {
            $this->actingAs($c)->get($url)->assertForbidden();
        }
        $other = Shipment::factory()->create();
        $this->actingAs($c)->get("/shipments/{$other->id}")->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/courier')->assertForbidden();
    }

    public function test_full_lifecycle_moves_the_shipment_and_stores_proof(): void
    {
        Storage::fake('local');
        $u = User::factory()->courier()->create();
        $a = $this->task($u);
        $s = $a->shipment;

        $this->actingAs($u)->post("/courier/tasks/{$a->id}/accept")->assertRedirect();
        $this->assertSame('ON_DELIVERY', $u->courier->fresh()->availability);

        $this->actingAs($u)->post("/courier/tasks/{$a->id}/pickup")->assertRedirect();
        $this->assertSame(ShipmentStatus::PickedUp, $s->fresh()->status);

        $this->actingAs($u)->post("/courier/tasks/{$a->id}/start")->assertRedirect();
        $this->assertSame(ShipmentStatus::InTransit, $s->fresh()->status);

        $this->actingAs($u)->post("/courier/tasks/{$a->id}/arrive")->assertRedirect();
        $this->assertSame(AssignmentStatus::Arrived, $a->fresh()->status);

        $this->actingAs($u)->post("/courier/tasks/{$a->id}/complete", [
            'recipient_name' => 'Budi', 'photo' => UploadedFile::fake()->image('proof.jpg'),
        ])->assertSessionHasNoErrors();

        $this->assertSame(ShipmentStatus::Delivered, $s->fresh()->status);
        $this->assertNotNull($s->fresh()->delivered_at);
        $this->assertSame(AssignmentStatus::Completed, $a->fresh()->status);
        $this->assertDatabaseHas('shipment_documents', ['shipment_id' => $s->id, 'type' => 'POD', 'recipient_name' => 'Budi']);
        $this->assertSame('AVAILABLE', $u->courier->fresh()->availability);
        $this->assertFalse($u->courier->fresh()->is_tracking);
        $this->assertDatabaseHas('tracking_events', ['shipment_id' => $s->id, 'event_type' => 'DELIVERED']);
        $this->assertGreaterThanOrEqual(3, $s->customer->notifications()->count()); // picked up, in transit, delivered
    }

    public function test_invalid_steps_and_incomplete_proof_are_rejected(): void
    {
        Storage::fake('local');
        $u = User::factory()->courier()->create();
        $a = $this->task($u);

        $this->actingAs($u)->post("/courier/tasks/{$a->id}/pickup")->assertSessionHasErrors('business_rule'); // not accepted yet
        $this->actingAs($u)->post("/courier/tasks/{$a->id}/arrive")->assertSessionHasErrors('business_rule');
        $this->assertSame(AssignmentStatus::Assigned, $a->fresh()->status);

        $b = $this->task($u, ShipmentStatus::InTransit, AssignmentStatus::Arrived);
        $this->actingAs($u)->post("/courier/tasks/{$b->id}/complete", ['recipient_name' => 'Budi'])->assertSessionHasErrors('photo');
        $this->actingAs($u)->post("/courier/tasks/{$b->id}/complete", ['photo' => UploadedFile::fake()->image('p.jpg')])->assertSessionHasErrors('recipient_name');
        $this->actingAs($u)->post("/courier/tasks/{$b->id}/complete", ['recipient_name' => 'X', 'photo' => UploadedFile::fake()->create('p.pdf', 10, 'application/pdf')])->assertSessionHasErrors('photo');
        $this->assertSame(ShipmentStatus::InTransit, $b->shipment->fresh()->status);
    }

    public function test_only_one_delivery_in_progress_per_courier(): void
    {
        $u = User::factory()->courier()->create();
        $this->task($u, ShipmentStatus::InTransit, AssignmentStatus::InTransit);
        $next = $this->task($u, ShipmentStatus::Booked, AssignmentStatus::Accepted);

        $this->actingAs($u)->post("/courier/tasks/{$next->id}/pickup")->assertSessionHasErrors('business_rule');
        $this->assertSame(AssignmentStatus::Accepted, $next->fresh()->status);
    }

    public function test_failed_delivery_opens_a_high_exception_and_frees_the_courier(): void
    {
        $u = User::factory()->courier()->create();
        $a = $this->task($u, ShipmentStatus::InTransit, AssignmentStatus::Arrived);
        $u->courier->forceFill(['is_tracking' => true, 'availability' => 'ON_DELIVERY'])->save();

        $this->actingAs($u)->post("/courier/tasks/{$a->id}/fail", ['reason' => 'x'])->assertSessionHasErrors('reason');
        $this->actingAs($u)->post("/courier/tasks/{$a->id}/fail", ['reason' => 'Recipient not at home'])->assertRedirect();

        $this->assertSame(AssignmentStatus::Failed, $a->fresh()->status);
        $this->assertSame('Recipient not at home', $a->fresh()->failure_reason);
        $this->assertDatabaseHas('shipment_exceptions', ['shipment_id' => $a->shipment_id, 'type' => 'DELIVERY_FAILED', 'severity' => 'HIGH', 'status' => 'OPEN']);
        $this->assertNotSame(ShipmentStatus::Delivered, $a->shipment->fresh()->status);
        $this->assertFalse($u->courier->fresh()->is_tracking);
        $this->assertSame('AVAILABLE', $u->courier->fresh()->availability);
    }

    public function test_staff_can_cancel_an_assignment(): void
    {
        $u = User::factory()->courier()->create();
        $a = $this->task($u);

        $this->actingAs(User::factory()->operator()->create())->delete("/assignments/{$a->id}")->assertRedirect();
        $this->assertSame(AssignmentStatus::Cancelled, $a->fresh()->status);
        $this->actingAs($u)->delete("/assignments/{$a->id}")->assertForbidden();
    }

    public function test_courier_api_tasks_and_auth(): void
    {
        $u = User::factory()->courier()->create();
        $this->task($u);
        $this->getJson('/api/courier/tasks')->assertStatus(401);

        Sanctum::actingAs($u);
        $this->getJson('/api/courier/tasks')->assertOk()->assertJsonCount(1, 'data');
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/courier/tasks')->assertForbidden();
    }
}
