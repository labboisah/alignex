<?php

namespace Database\Factories;

use App\Models\AdminRegistrationRequest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<AdminRegistrationRequest>
 */
class AdminRegistrationRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'entity_type' => AdminRegistrationRequest::TYPE_ORGANIZATION,
            'admin_name' => fake()->name(),
            'admin_email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'entity_name' => fake()->company(),
            'entity_code' => fake()->unique()->bothify('REG-####'),
            'location' => fake()->address(),
            'capacity' => fake()->numberBetween(100, 1000),
            'contact_person' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'entity_email' => fake()->unique()->companyEmail(),
            'address' => fake()->address(),
            'legal_registration_number' => fake()->bothify('RC-#####'),
            'website' => fake()->url(),
            'years_in_operation' => fake()->numberBetween(1, 20),
            'operating_scope' => 'Statewide',
            'accreditation_body' => 'Ministry of Education',
            'accreditation_number' => fake()->bothify('ACC-#####'),
            'facility_summary' => 'Computer laboratory, backup power, and trained technical staff.',
            'exam_experience' => 'Previously delivered internal CBT assessments.',
            'expected_candidates' => fake()->numberBetween(100, 2000),
            'status' => AdminRegistrationRequest::STATUS_PENDING,
        ];
    }
}
