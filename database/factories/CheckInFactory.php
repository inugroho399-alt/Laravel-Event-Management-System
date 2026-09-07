<?php

namespace Database\Factories;

use App\Models\CheckIn;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CheckIn>
 */
class CheckInFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'registration_id' => Registration::factory(),
            'checked_in_by' => User::factory()->organizer(),
            'checked_in_at' => now(),
        ];
    }
}
