<?php

namespace Tests\Feature;

use App\Enums\ExceptionStatus;
use App\Enums\ExceptionType;
use App\Enums\Severity;
use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExceptionCenterTest extends TestCase
{
    use RefreshDatabase;

    private function exception(array $o = []): ShipmentException
    {
        $s = Shipment::factory()->status(ShipmentStatus::InTransit)->create();

        return $s->exceptions()->create($o + [
            'type' => ExceptionType::CustomsHold, 'severity' => Severity::High, 'status' => ExceptionStatus::Open,
            'description' => 'Held', 'detected_at' => now()->subHour(),
        ]);
    }

    public function test_staff_can_view_center_filters_and_detail(): void
    {
        $this->exception();
        $this->exception(['severity' => Severity::Low, 'type' => ExceptionType::WeatherDelay]);
        $op = User::factory()->operator()->create();

        $this->actingAs($op)->get('/exceptions')->assertOk()->assertSee('Exception Management')->assertSee('SIMULATED TRACKING DATA');
        $this->actingAs($op)->get('/exceptions?severity=HIGH&status=OPEN&type=CUSTOMS_HOLD')->assertOk();
        $this->actingAs($op)->get('/exceptions?severity=BOGUS')->assertSessionHasErrors('severity');
        $this->actingAs($op)->get('/exceptions/'.$this->exception()->id)->assertOk()->assertSee('Investigation notes');
    }

    public function test_assign_update_severity_and_add_notes(): void
    {
        $ex = $this->exception();
        $op = User::factory()->operator()->create();
        $other = User::factory()->operator()->create();

        $this->actingAs($op)->patch("/exceptions/{$ex->id}", ['assigned_to' => $other->id, 'severity' => 'LOW', 'status' => 'INVESTIGATING'])->assertRedirect();
        $ex->refresh();
        $this->assertSame($other->id, $ex->assigned_to);
        $this->assertSame(Severity::Low, $ex->severity);
        $this->assertSame(ExceptionStatus::Investigating, $ex->status);

        $this->actingAs($op)->post("/exceptions/{$ex->id}/notes", ['body' => 'Called the broker'])->assertRedirect();
        $this->assertDatabaseHas('exception_notes', ['shipment_exception_id' => $ex->id, 'body' => 'Called the broker']);
        $this->actingAs($op)->post("/exceptions/{$ex->id}/notes", ['body' => ''])->assertSessionHasErrors('body');
    }

    public function test_cannot_assign_a_customer_or_inactive_user(): void
    {
        $ex = $this->exception();
        Sanctum::actingAs(User::factory()->operator()->create());

        $this->patchJson("/api/exceptions/{$ex->id}", ['assigned_to' => User::factory()->create()->id])->assertStatus(422);
        $this->patchJson("/api/exceptions/{$ex->id}", ['assigned_to' => User::factory()->operator()->inactive()->create()->id])->assertStatus(422);
    }

    public function test_customers_cannot_reach_exception_center_or_notes(): void
    {
        $ex = $this->exception();
        $c = User::factory()->create();

        $this->actingAs($c)->get('/exceptions')->assertForbidden();
        $this->actingAs($c)->get("/exceptions/{$ex->id}")->assertForbidden();
        $this->actingAs($c)->post("/exceptions/{$ex->id}/notes", ['body' => 'x'])->assertForbidden();
        Sanctum::actingAs($c);
        $this->postJson("/api/exceptions/{$ex->id}/notes", ['body' => 'x'])->assertForbidden();
    }

    public function test_api_exception_list_is_scoped_to_the_caller(): void
    {
        $c = User::factory()->create();
        $mine = Shipment::factory()->create(['customer_id' => $c->id]);
        $mine->exceptions()->create(['type' => 'DAMAGED', 'severity' => 'HIGH', 'status' => 'OPEN', 'description' => 'x', 'detected_at' => now()]);
        $this->exception();

        Sanctum::actingAs($c);
        $this->getJson('/api/exceptions')->assertOk()->assertJsonCount(1, 'data');
        Sanctum::actingAs(User::factory()->admin()->create());
        $this->getJson('/api/exceptions')->assertJsonCount(2, 'data');
    }

    public function test_command_center_renders_for_staff_with_database_values(): void
    {
        $this->exception();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/dashboard')->assertOk()->assertSee('Global shipment map')->assertSee('Critical alerts')->assertSee('Route deviations');
        $this->actingAs($admin)->get('/operations')->assertOk();
        $this->actingAs($admin)->get('/eta')->assertOk();
        $this->actingAs($admin)->get('/tracking')->assertOk();
        $this->actingAs($admin)->get('/alerts')->assertOk();
        $this->actingAs($admin)->get('/analytics')->assertOk();
    }
}
