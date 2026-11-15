<?php

namespace Tests\Feature;

use App\Broadcasting\ChannelAuth;
use App\Enums\ShipmentStatus;
use App\Events\ShipmentUpdated;
use App\Events\UserNotified;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RealtimeBroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_change_broadcasts_to_staff_and_the_shipment_channel(): void
    {
        Event::fake([ShipmentUpdated::class, UserNotified::class]);
        $s = Shipment::factory()->create();
        Sanctum::actingAs(User::factory()->operator()->create());

        $this->postJson("/api/shipments/{$s->id}/tracking", ['status' => 'BOOKED'])->assertCreated();

        Event::assertDispatched(ShipmentUpdated::class, function ($e) use ($s) {
            $channels = collect($e->broadcastOn())->map(fn ($c) => $c->name)->all();

            return $e->shipment->id === $s->id && in_array('private-staff.live', $channels) && in_array("private-shipment.{$s->id}", $channels);
        });
        Event::assertDispatched(UserNotified::class, fn ($e) => $e->userId === $s->customer_id);
    }

    public function test_a_failing_websocket_never_breaks_the_request(): void
    {
        Event::listen(ShipmentUpdated::class, fn () => throw new \RuntimeException('Reverb is down'));
        $s = Shipment::factory()->create();
        Sanctum::actingAs(User::factory()->operator()->create());

        $this->postJson("/api/shipments/{$s->id}/tracking", ['status' => 'BOOKED'])->assertCreated();
        $this->assertSame(ShipmentStatus::Booked, $s->fresh()->status);
    }

    public function test_event_payload_carries_database_metrics(): void
    {
        Shipment::factory()->count(2)->status(ShipmentStatus::InTransit)->create();
        Shipment::factory()->status(ShipmentStatus::Booked)->create();
        $s = Shipment::factory()->status(ShipmentStatus::Delivered)->create();

        $payload = (new ShipmentUpdated($s, 'status', 'x'))->broadcastWith();

        $this->assertSame(4, $payload['metrics']['total']);
        $this->assertSame(2, $payload['metrics']['in_transit']);
        $this->assertSame(1, $payload['metrics']['waiting']);
        $this->assertSame(1, $payload['metrics']['delivered']);
        $this->assertSame('x', $payload['message']);
        $this->assertArrayNotHasKey('customer', $payload['shipment']); // no personal data in broadcasts
    }

    public function test_channel_authorisation_rules(): void
    {
        $customer = User::factory()->create();
        $other = User::factory()->create();
        $s = Shipment::factory()->create(['customer_id' => $customer->id]);

        $this->assertTrue(ChannelAuth::staff(User::factory()->admin()->create()));
        $this->assertTrue(ChannelAuth::staff(User::factory()->operator()->create()));
        $this->assertFalse(ChannelAuth::staff($customer));
        $this->assertFalse(ChannelAuth::staff(User::factory()->courier()->create()));
        $this->assertFalse(ChannelAuth::staff(User::factory()->operator()->inactive()->create()));

        $this->assertTrue(ChannelAuth::shipment($customer, $s->id));
        $this->assertFalse(ChannelAuth::shipment($other, $s->id));
        $this->assertFalse(ChannelAuth::shipment(User::factory()->courier()->create(), $s->id));
        $this->assertTrue(ChannelAuth::shipment(User::factory()->operator()->create(), $s->id));
        $this->assertFalse(ChannelAuth::shipment($customer, 999999));

        $this->assertTrue(ChannelAuth::user($customer, $customer->id));
        $this->assertFalse(ChannelAuth::user($customer, $other->id));
    }

    public function test_live_metrics_endpoint_is_staff_only_and_matches_the_database(): void
    {
        Shipment::factory()->count(3)->status(ShipmentStatus::InTransit)->create();

        Sanctum::actingAs(User::factory()->operator()->create());
        $this->getJson('/api/live/metrics')->assertOk()->assertJsonPath('in_transit', 3)->assertJsonPath('total', 3);

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/live/metrics')->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/live/metrics')->assertForbidden();
    }

    public function test_realtime_ui_degrades_to_polling_when_broadcasting_is_off(): void
    {
        $html = $this->actingAs(User::factory()->admin()->create())->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('window.RK_REALTIME', $html);
        $this->assertMatchesRegularExpression('/enabled\\W+false/', $html);
        $this->assertStringContainsString('rtPill', $html);
        $this->assertStringContainsString('Live operations', $html);
    }
}
