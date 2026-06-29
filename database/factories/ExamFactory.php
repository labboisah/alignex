<?php

namespace Database\Factories;

use App\Models\Exam;
use App\Models\ExamType;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Exam>
 */
class ExamFactory extends Factory
{
    protected $model = Exam::class;

    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('+1 day', '+2 weeks');

        return [
            'organization_id' => Organization::factory(),
            'school_id' => null,
            'exam_type_id' => ExamType::factory(),
            'created_by' => User::factory(),
            'title' => fake()->unique()->sentence(3),
            'code' => strtoupper(fake()->unique()->bothify('EXAM-####')),
            'description' => fake()->sentence(),
            'delivery_mode' => 'online',
            'duration_minutes' => 90,
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->modify('+2 hours'),
            'timezone' => 'Africa/Lagos',
            'status' => Exam::STATUS_SCHEDULED,
            'security_settings' => ['fullscreen_required' => true],
            'navigation_settings' => ['allow_backtracking' => true],
            'result_release_settings' => ['release_mode' => 'manual'],
        ];
    }
}
