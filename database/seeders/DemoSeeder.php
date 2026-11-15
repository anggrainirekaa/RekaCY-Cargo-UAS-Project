<?php

namespace Database\Seeders;

use App\Enums\ExceptionStatus;
use App\Enums\ExceptionType;
use App\Enums\Role;
use App\Enums\Severity;
use App\Enums\ShipmentStatus;
use App\Models\Carrier;
use App\Models\Courier;
use App\Models\DeliveryAssignment;
use App\Enums\AssignmentStatus;
use App\Models\SupportTicket;
use App\Enums\TicketStatus;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\User;
use App\Services\EtaService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * SYNTHETIC demo data (documented in README): carriers, routes, shipments and events are
 * invented but internally consistent. Scenario = "Group A": Southeast Asia → Europe.
 * Deterministic: mt_srand() makes every run produce the same dataset.
 */
class DemoSeeder extends Seeder
{
    public function run(EtaService $eta): void
    {
        mt_srand(20261103);
        $now = Carbon::now();

        $this->user('RekaCy Admin', 'admin@rekacy.test', Role::Admin);
        $ops = [
            $this->user('Rina Operator', 'operator@rekacy.test', Role::Operator),
            $this->user('Bima Operator', 'operator2@rekacy.test', Role::Operator),
        ];
        $customers = [
            $this->user('Customer Demo', 'customer@rekacy.test', Role::Customer, 'Nusantara Coffee Exporters'),
            $this->user('Samudra Textile', 'customer2@rekacy.test', Role::Customer, 'Samudra Textile Ltd'),
            $this->user('Aceh Spice Trading', 'customer3@rekacy.test', Role::Customer, 'Aceh Spice Trading Co'),
        ];

        $carriers = collect([
            ['Pacific Gateway Lines', 'PGL', 'sea'],
            ['Nusantara Ocean Freight', 'NOF', 'sea'],
            ['EuroAsia Cargo', 'EAC', 'sea'],
            ['Meridian Air Express', 'MAX', 'air'],
        ])->map(fn ($c) => Carrier::create(['name' => $c[0], 'code' => $c[1], 'mode' => $c[2], 'contact_email' => strtolower($c[1]).'@carrier.example', 'is_active' => true]));

        $locs = [];
        foreach ([
            ['Belawan Port', 'Medan', 'Indonesia', 3.7830, 98.6830],
            ['Tanjung Priok Port', 'Jakarta', 'Indonesia', -6.1045, 106.8805],
            ['Tanjung Perak Port', 'Surabaya', 'Indonesia', -7.2063, 112.7326],
            ['Port Klang', 'Klang', 'Malaysia', 3.0000, 101.3600],
            ['Port of Singapore', 'Singapore', 'Singapore', 1.2644, 103.8200],
            ['Port of Colombo', 'Colombo', 'Sri Lanka', 6.9400, 79.8500],
            ['Port Said', 'Port Said', 'Egypt', 31.2600, 32.3000],
            ['Port of Rotterdam', 'Rotterdam', 'Netherlands', 51.9500, 4.1400],
            ['Port of Hamburg', 'Hamburg', 'Germany', 53.5400, 9.9700],
            ['Port of Antwerp-Bruges', 'Antwerp', 'Belgium', 51.2700, 4.3600],
            ['Port of Felixstowe', 'Felixstowe', 'United Kingdom', 51.9500, 1.3500],
        ] as [$n, $city, $country, $lat, $lng]) {
            $locs[$n] = Location::create(['name' => $n, 'city' => $city, 'country' => $country, 'type' => 'port', 'latitude' => $lat, 'longitude' => $lng]);
        }

        $routes = [
            ['Belawan Port', 'Port Klang', 'Port of Colombo', 'Port Said', 'Port of Rotterdam'],
            ['Tanjung Priok Port', 'Port of Singapore', 'Port of Colombo', 'Port Said', 'Port of Hamburg'],
            ['Tanjung Perak Port', 'Port of Singapore', 'Port of Colombo', 'Port Said', 'Port of Antwerp-Bruges'],
            ['Belawan Port', 'Port of Singapore', 'Port of Colombo', 'Port Said', 'Port of Felixstowe'],
        ];
        $cargo = ['Coffee beans', 'Textiles', 'Spices', 'Palm-oil derivatives', 'Electronics', 'Furniture', 'Rubber products', 'Seafood (reefer)'];
        $offsets = [0, 0, 0, -3, 2, 4.5, 9, 15, 30, 52];
        $excTypes = [ExceptionType::CustomsHold, ExceptionType::CustomsHold, ExceptionType::CustomsHold, ExceptionType::WeatherDelay, ExceptionType::PortCongestion, ExceptionType::DocumentationIssue, ExceptionType::Damaged, ExceptionType::Misrouted];

        for ($i = 1; $i <= 48; $i++) {
            $route = $routes[mt_rand(0, 3)];
            $n = count($route);
            $total = ($n - 1) * mt_rand(100, 150); // hours
            $roll = mt_rand(1, 100);
            $stage = match (true) {
                $roll <= 6 => 'CREATED', $roll <= 12 => 'BOOKED', $roll <= 18 => 'PICKED_UP',
                $roll <= 68 => 'IN_TRANSIT', $roll <= 96 => 'DELIVERED', default => 'CANCELLED',
            };
            $offset = $offsets[mt_rand(0, count($offsets) - 1)];
            $op = $ops[mt_rand(0, 1)];

            $delivered = null;
            $eta_at = null;
            $progress = 0.0;

            if (in_array($stage, ['CREATED', 'BOOKED', 'CANCELLED'], true)) {
                $departure = $now->copy()->addHours(mt_rand(30, 200));
            } elseif ($stage === 'PICKED_UP') {
                $departure = $now->copy()->subHours(mt_rand(2, 20));
            } elseif ($stage === 'IN_TRANSIT') {
                $progress = mt_rand(10, 112) / 100;
                $departure = $now->copy()->subSeconds((int) ($progress * $total * 3600));
            } else {
                $departure = $now->copy()->subHours((int) ($total + $offset + mt_rand(12, 240)));
                $delivered = $departure->copy()->addSeconds((int) (($total + $offset) * 3600));
            }

            $expected = $departure->copy()->addHours($total);
            if ($stage === 'IN_TRANSIT' || $stage === 'PICKED_UP') {
                $eta_at = $offset !== 0 ? $expected->copy()->addSeconds((int) ($offset * 3600)) : null;
            }

            $s = new Shipment([
                'customer_id' => $customers[mt_rand(0, 2)]->id,
                'carrier_id' => $carriers[mt_rand(0, 2)]->id,
                'origin_id' => $locs[$route[0]]->id,
                'destination_id' => $locs[$route[$n - 1]]->id,
                'cargo_type' => $cargo[mt_rand(0, count($cargo) - 1)],
                'weight_kg' => mt_rand(800, 24000),
                'volume_cbm' => mt_rand(40, 650) / 10,
                'expected_departure_at' => $departure,
                'expected_arrival_at' => $expected,
                'estimated_arrival_at' => $eta_at,
                'notes' => $i % 7 === 0 ? 'Handle with care — synthetic demo note.' : null,
                'priority' => ['NORMAL', 'NORMAL', 'NORMAL', 'HIGH', 'URGENT'][mt_rand(0, 4)],
            ]);
            $s->shipment_number = sprintf('RCY-%s-%06d', $now->format('Y'), $i);
            $s->status = ShipmentStatus::from($stage);
            $s->delivered_at = $delivered;
            $s->created_by = $op->id;
            $createdAt = $stage === 'CREATED' ? $now->copy()->subHours(mt_rand(1, 20)) : $departure->copy()->subDays(2);
            $s->created_at = $createdAt;
            $s->updated_at = $createdAt;
            $s->save();

            $s->items()->create(['description' => $s->cargo_type.' — lot A', 'quantity' => mt_rand(1, 40), 'weight_kg' => $s->weight_kg]);
            if ($i % 3 === 0) {
                $s->items()->create(['description' => 'Packaging & pallets', 'quantity' => mt_rand(2, 20), 'weight_kg' => null]);
            }

            $ev = function (string $type, ?ShipmentStatus $status, ?string $locName, Carbon $at, ?string $desc, bool $exc = false) use ($s, $locs, $op) {
                $loc = $locName ? $locs[$locName] : null;
                $s->trackingEvents()->create([
                    'event_type' => $type, 'status' => $status, 'location_id' => $loc?->id, 'location_name' => $loc?->name,
                    'latitude' => $loc?->latitude, 'longitude' => $loc?->longitude, 'occurred_at' => $at,
                    'description' => $desc, 'is_exception' => $exc, 'created_by' => $op->id,
                ]);
            };

            $ev('CREATED', ShipmentStatus::Created, $route[0], $createdAt, 'Shipment record created.');
            if ($stage !== 'CREATED') {
                if ($stage === 'CANCELLED') {
                    $ev('CANCELLED', ShipmentStatus::Cancelled, $route[0], $createdAt->copy()->addHours(3), 'Cancelled at customer request.');
                } else {
                    $ev('BOOKED', ShipmentStatus::Booked, $route[0], $createdAt->copy()->addHours(6), 'Space confirmed with carrier.');
                }
            }
            if (in_array($stage, ['PICKED_UP', 'IN_TRANSIT', 'DELIVERED'], true)) {
                $ev('PICKED_UP', ShipmentStatus::PickedUp, $route[0], $departure->copy(), 'Cargo picked up and gated in.');
            }
            if (in_array($stage, ['IN_TRANSIT', 'DELIVERED'], true)) {
                $span = $stage === 'DELIVERED' ? $total + $offset : $total;
                for ($w = 1; $w <= $n - 2; $w++) {
                    $at = $departure->copy()->addSeconds((int) ($w * ($span / ($n - 1)) * 3600));
                    if ($at->lte($now)) {
                        $ev($w === 1 ? 'IN_TRANSIT' : 'CHECKPOINT', $w === 1 ? ShipmentStatus::InTransit : null, $route[$w], $at, 'Vessel departed '.$locs[$route[$w]]->city.'.');
                    }
                }
                if ($stage === 'IN_TRANSIT' && $n > 2 && $departure->copy()->addSeconds((int) ($total / ($n - 1) * 3600))->gt($now)) {
                    $ev('IN_TRANSIT', ShipmentStatus::InTransit, $route[0], $departure->copy()->addMinutes(30), 'Vessel sailed from origin.');
                }
            }
            if ($stage === 'DELIVERED') {
                $ev('DELIVERED', ShipmentStatus::Delivered, $route[$n - 1], $delivered, 'Delivered to consignee.');
            }

            $r = $eta->evaluate($s, $now);
            $eta->apply($s, $r);
            $s->save();

            if (in_array($stage, ['IN_TRANSIT', 'DELIVERED'], true) && mt_rand(1, 100) <= 38) {
                $type = $excTypes[mt_rand(0, count($excTypes) - 1)];
                $detectOffset = mt_rand(10, 80) / 100;
                $detected = $departure->copy()->addSeconds((int) ($detectOffset * min($total, max(1, $now->diffInHours($departure, true))) * 3600));
                if ($detected->gt($now)) {
                    $detected = $now->copy()->subHours(2);
                }
                $resolved = $stage === 'DELIVERED' || mt_rand(1, 100) <= 45;
                $resolvedAt = $resolved ? $detected->copy()->addHours(mt_rand(3, 60)) : null;
                if ($resolvedAt && $resolvedAt->gt($now)) {
                    $resolvedAt = $now->copy()->subMinutes(10);
                    if ($resolvedAt->lt($detected)) {
                        $resolvedAt = null;
                        $resolved = false;
                    }
                }
                $status = $resolved ? ExceptionStatus::Resolved : (mt_rand(0, 1) ? ExceptionStatus::Investigating : ExceptionStatus::Open);

                $assigned = mt_rand(1, 100) <= 70 ? $ops[mt_rand(0, 1)] : null;
                $excRow = ShipmentException::create([
                    'shipment_id' => $s->id, 'type' => $type, 'severity' => $type->defaultSeverity(), 'status' => $status,
                    'description' => $type->label().' reported at '.$locs[$route[min(1, $n - 1)]]->city.' (synthetic demo case).',
                    'resolution_note' => $resolved ? 'Cleared with authority; cargo released.' : null,
                    'detected_at' => $detected, 'resolved_at' => $resolvedAt, 'resolved_by' => $resolved ? $op->id : null, 'created_by' => $op->id, 'assigned_to' => $assigned?->id,
                ]);
                if ($status !== ExceptionStatus::Open) {
                    $excRow->notes()->create(['user_id' => ($assigned ?? $op)->id, 'body' => 'Contacted the agent at '.$locs[$route[min(1, $n - 1)]]->city.'; awaiting clearance (synthetic demo note).', 'created_at' => $detected->copy()->addHour(), 'updated_at' => $detected->copy()->addHour()]);
                }
                $ev('EXCEPTION', null, null, $detected, $type->label().' ('.$type->defaultSeverity()->value.')', true);
            }
        }


        // ---- Couriers + task assignments. NO GPS rows are seeded: positions only exist when a courier device
        //      (or the clearly labelled DEMO button) actually sends them.
        $couriers = collect([
            ['Dimas Kurir', 'courier@rekacy.test', 'motorcycle', 'BK 1234 AB'],
            ['Sari Kurir', 'courier2@rekacy.test', 'van', 'BK 5678 CD'],
            ['Andi Kurir', 'courier3@rekacy.test', 'truck', 'BL 9012 EF'],
        ])->map(function ($c) {
            $u = $this->user($c[0], $c[1], Role::Courier);

            return Courier::create(['user_id' => $u->id, 'phone' => '+62 812-0000-0000', 'vehicle_type' => $c[2], 'vehicle_plate' => $c[3], 'availability' => 'AVAILABLE', 'is_active' => true]);
        });

        $busy = [];
        $n = 0;
        foreach (Shipment::query()->whereIn('status', ['BOOKED', 'PICKED_UP', 'IN_TRANSIT', 'DELIVERED'])->orderBy('id')->get() as $s) {
            if ($n >= 14) {
                break;
            }
            $c = $couriers[$s->id % 3];
            $status = match ($s->status->value) {
                'BOOKED' => ($s->id % 2) ? AssignmentStatus::Assigned : AssignmentStatus::Accepted,
                'PICKED_UP' => AssignmentStatus::PickedUp,
                'IN_TRANSIT' => AssignmentStatus::InTransit,
                default => AssignmentStatus::Completed,
            };
            if ($status->tracksGps() && isset($busy[$c->id])) {
                continue; // a courier carries one delivery at a time
            }

            $a = new DeliveryAssignment(['notes' => null]);
            $a->shipment_id = $s->id;
            $a->courier_id = $c->id;
            $a->assigned_by = $ops[0]->id;
            $a->status = $status;
            $a->assigned_at = $s->created_at->copy()->addHours(8);
            $a->accepted_at = $status !== AssignmentStatus::Assigned ? $a->assigned_at->copy()->addHour() : null;
            $a->picked_up_at = in_array($status, [AssignmentStatus::PickedUp, AssignmentStatus::InTransit, AssignmentStatus::Completed], true) ? $s->expected_departure_at : null;
            $a->completed_at = $status === AssignmentStatus::Completed ? $s->delivered_at : null;
            $a->recipient_name = $status === AssignmentStatus::Completed ? 'Penerima Demo' : null;
            $a->save();
            $n++;

            if ($status->tracksGps()) {
                $busy[$c->id] = true;
                $c->forceFill(['availability' => 'ON_DELIVERY'])->save();
            } elseif ($status->isActive() && ! isset($busy[$c->id])) {
                $c->forceFill(['availability' => 'ON_DELIVERY'])->save();
            }
        }
        $couriers[2]->forceFill(['availability' => isset($busy[$couriers[2]->id]) ? 'ON_DELIVERY' : 'OFFLINE'])->save();

        $subjects = [['Where is my container?', 'GENERAL', 'LOW'], ['Delay beyond estimate', 'DELAY', 'MEDIUM'], ['Customs paperwork question', 'DOCUMENT', 'MEDIUM'], ['Damaged pallets on arrival', 'DAMAGE', 'HIGH'], ['Invoice mismatch', 'BILLING', 'LOW']];
        foreach ($subjects as $k => [$subject, $cat, $prio]) {
            $cust = $customers[$k % 3];
            $ship = Shipment::where('customer_id', $cust->id)->first();
            $t = new SupportTicket(['shipment_id' => $ship?->id, 'subject' => $subject, 'category' => $cat, 'priority' => $prio]);
            $t->user_id = $cust->id;
            $t->status = [TicketStatus::Open, TicketStatus::InProgress, TicketStatus::Resolved, TicketStatus::Open, TicketStatus::Closed][$k];
            $t->reference = sprintf('TKT-%s-%04d', $now->format('ymd'), $k + 1);
            $t->assigned_to = $t->status === TicketStatus::Open ? null : $ops[0]->id;
            $t->resolved_at = in_array($t->status, [TicketStatus::Resolved, TicketStatus::Closed], true) ? $now->copy()->subDay() : null;
            $t->save();
            $t->replies()->create(['user_id' => $cust->id, 'body' => 'Hello, could you please help with this? (synthetic demo ticket)']);
            if ($t->assigned_to) {
                $t->replies()->create(['user_id' => $ops[0]->id, 'body' => 'We are looking into it and will update you shortly.']);
            }
        }
    }

    private function user(string $name, string $email, Role $role, ?string $company = null): User
    {
        $u = User::create(['name' => $name, 'email' => $email, 'password' => 'password', 'company' => $company, 'phone' => '+62 811-0000-0000']);
        $u->forceFill(['role' => $role, 'is_active' => true, 'email_verified_at' => now()])->save();

        return $u;
    }
}
