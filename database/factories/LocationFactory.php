<?php

namespace Database\Factories;

use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Location> */
class LocationFactory extends Factory
{
    protected $model = Location::class;

    public function definition(): array
    {
        return [
            'name' => 'Port of '.fake()->unique()->city(),
            'city' => fake()->city(),
            'country' => fake()->country(),
            'type' => 'port',
            'latitude' => fake()->latitude(-60, 60),
            'longitude' => fake()->longitude(),
        ];
    }
}
