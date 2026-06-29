<?php

namespace Database\Factories;

use App\Models\Candidate;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Candidate>
 */
class CandidateFactory extends Factory
{
    protected $model = Candidate::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'school_id' => null,
            'user_id' => User::factory(['role' => User::ROLE_CANDIDATE]),
            'candidate_number' => strtoupper(fake()->unique()->bothify('CAN-#####')),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'date_of_birth' => fake()->date(),
            'metadata' => ['source' => 'factory'],
            'status' => Candidate::STATUS_ACTIVE,
        ];
    }
}
