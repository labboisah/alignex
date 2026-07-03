<?php

namespace Database\Factories;

use App\Models\CandidateGroup;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class CandidateGroupFactory extends Factory
{
    protected $model = CandidateGroup::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => $this->faker->unique()->words(2, true),
            'code' => strtoupper($this->faker->unique()->bothify('CG-###')),
            'description' => $this->faker->sentence(),
            'status' => CandidateGroup::STATUS_ACTIVE,
        ];
    }
}
