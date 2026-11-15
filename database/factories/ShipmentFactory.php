<?php

namespace Database\Factories;

use App\Enums\ShipmentPriority;
use App\Enums\ShipmentStatus;
use App\Models\Carrier;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Shipment> */
class ShipmentFactory extends Factory
{
    protected $model = Shipment::class;

    public function definition(): array
    {
        return [
            'shipment_number' => 'RCY-TEST-'.fake()->unique()->numerify('######'),
            'customer_id' => User::factory(),
            'carrier_id' => Carrier::factory(),
            'origin_id' => Location::factory(),
            'destination_id' => Location::factory(),
            'cargo_type' => 'Coffee beans',
            'weight_kg' => 1200,
            'volume_cbm' => 18.5,
            'expected_departure_at' => now()->addDay(),
            'expected_arrival_at' => now()->addDays(20),
            'status' => ShipmentStatus::Created,
            'priority' => ShipmentPriority::Normal,
            'is_delayed' => false,
            'delay_hours' => 0,
        ];
    }

    public function status(ShipmentStatus $status): static
    {
        return $this->state(['status' => $status]);
    }
}
