<?php

namespace Tests\Feature;

use App\Models\Shipment;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class SupportSearchAndAccountTest extends TestCase
{
    use RefreshDatabase;

    private function ticket(User $owner, array $o = []): SupportTicket
    {
        $t = new SupportTicket(['subject' => 'Help', 'category' => 'GENERAL', 'priority' => 'LOW'] + $o);
        $t->user_id = $owner->id;
        $t->status = 'OPEN';
        $t->reference = 'TKT-'.fake()->unique()->numerify('####');
        $t->save();
        $t->replies()->create(['user_id' => $owner->id, 'body' => 'Hi']);

        return $t;
    }

    public function test_customer_creates_ticket_and_cannot_see_others(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $mine = Shipment::factory()->create(['customer_id' => $a->id]);
        $foreign = Shipment::factory()->create(['customer_id' => $b->id]);

        $this->actingAs($a)->post('/support', ['subject' => 'Delay?', 'category' => 'DELAY', 'priority' => 'HIGH', 'shipment_id' => $mine->id, 'body' => 'Where is it?'])->assertRedirect();
        $this->assertDatabaseHas('support_tickets', ['subject' => 'Delay?', 'user_id' => $a->id]);
        $this->actingAs($a)->post('/support', ['subject' => 'x', 'category' => 'GENERAL', 'priority' => 'LOW', 'shipment_id' => $foreign->id, 'body' => 'y'])->assertSessionHasErrors('shipment_id');

        $theirs = $this->ticket($b);
        $this->actingAs($a)->get("/support/{$theirs->id}")->assertForbidden();
        $this->actingAs($a)->post("/support/{$theirs->id}/replies", ['body' => 'hack'])->assertForbidden();
        $this->actingAs($a)->patch("/support/{$theirs->id}", ['status' => 'CLOSED', 'priority' => 'LOW'])->assertForbidden();
        $this->actingAs($a)->get('/support')->assertOk()->assertDontSee($theirs->reference);
    }

    public function test_internal_notes_are_hidden_from_customers(): void
    {
        $c = User::factory()->create();
        $op = User::factory()->operator()->create();
        $t = $this->ticket($c);

        $this->actingAs($op)->post("/support/{$t->id}/replies", ['body' => 'SECRET-INTERNAL', 'is_internal' => 1])->assertRedirect();
        $this->actingAs($op)->post("/support/{$t->id}/replies", ['body' => 'Public answer'])->assertRedirect();

        $this->actingAs($c)->get("/support/{$t->id}")->assertOk()->assertSee('Public answer')->assertDontSee('SECRET-INTERNAL');
        $this->actingAs($op)->get("/support/{$t->id}")->assertSee('SECRET-INTERNAL');
        $this->assertSame('IN_PROGRESS', $t->fresh()->status->value);
    }

    public function test_search_is_scoped_by_role(): void
    {
        $a = User::factory()->create(['name' => 'Zeta Customer']);
        $mine = Shipment::factory()->create(['customer_id' => $a->id, 'cargo_type' => 'Quartz']);
        $other = Shipment::factory()->create(['cargo_type' => 'Quartz']);

        $res = $this->actingAs($a)->getJson('/search.json?q=Quartz')->assertOk();
        $this->assertCount(1, $res->json('shipments'));
        $this->assertSame([], $res->json('customers'));

        $staff = $this->actingAs(User::factory()->operator()->create())->getJson('/search.json?q=Quartz');
        $this->assertCount(2, $staff->json('shipments'));
        $this->actingAs(User::factory()->operator()->create())->getJson('/search.json?q=Zeta')->assertJsonPath('customers.0.title', 'Zeta Customer');
        $this->actingAs($a)->get('/search?q=Quartz')->assertOk()->assertSee($mine->shipment_number)->assertDontSee($other->shipment_number);
    }

    public function test_password_reset_flow(): void
    {
        Notification::fake();
        $u = User::factory()->create(['email' => 'r@x.test']);

        $this->post('/forgot-password', ['email' => 'r@x.test'])->assertSessionHas('status');
        $this->post('/forgot-password', ['email' => 'nobody@x.test'])->assertSessionHas('status'); // no enumeration
        Notification::assertSentTo($u, ResetPassword::class);

        $token = Password::createToken($u);
        $this->post('/reset-password', ['token' => $token, 'email' => 'r@x.test', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])->assertRedirect('/login');
        $this->assertTrue(Hash::check('newpass123', $u->fresh()->password));
        $this->post('/reset-password', ['token' => 'bad', 'email' => 'r@x.test', 'password' => 'another123', 'password_confirmation' => 'another123'])->assertSessionHasErrors('email');
    }

    public function test_profile_update_cannot_change_role(): void
    {
        $u = User::factory()->create();

        $this->actingAs($u)->patch('/profile', ['name' => 'New Name', 'email' => $u->email, 'role' => 'admin', 'is_active' => 0])->assertRedirect();
        $u->refresh();
        $this->assertSame('New Name', $u->name);
        $this->assertTrue($u->isCustomer());
        $this->assertTrue($u->is_active);

        $this->actingAs($u)->put('/profile/password', ['current_password' => 'wrong', 'password' => 'abcd1234', 'password_confirmation' => 'abcd1234'])->assertSessionHasErrors('current_password');
        $this->actingAs($u)->put('/profile/password', ['current_password' => 'password', 'password' => 'abcd1234', 'password_confirmation' => 'abcd1234'])->assertSessionHasNoErrors();
    }

    public function test_public_pages_and_error_pages(): void
    {
        $this->get('/')->assertOk()->assertSee('COMPLETE CONTROL', false)->assertSee('RekaCy');
        $this->get('/about')->assertOk();
        $this->get('/login')->assertOk()->assertSee('RekaCy');
        $this->get('/this-does-not-exist')->assertNotFound()->assertSee('Page not found');
        $this->actingAs(User::factory()->create())->get('/users')->assertForbidden()->assertSee('Access denied');
    }

    public function test_reports_are_staff_only_and_csv_is_safe(): void
    {
        Shipment::factory()->create(['cargo_type' => '=HYPERLINK("x")']);

        $this->actingAs(User::factory()->create())->get('/reports')->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/reports/shipments.csv')->assertForbidden();

        $op = User::factory()->operator()->create();
        $this->actingAs($op)->get('/reports')->assertOk();
        $this->actingAs($op)->get('/reports/print')->assertOk();
        $res = $this->actingAs($op)->get('/reports/shipments.csv')->assertOk();
        $this->assertStringContainsString('text/csv', $res->headers->get('Content-Type'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'report.exported']);
        $this->assertSame("'=1+1", \App\Services\ReportService::cell('=1+1'));
    }
}
