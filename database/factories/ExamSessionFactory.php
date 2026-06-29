<?php

namespace Database\Factories;

use App\Models\Center;
use App\Models\Exam;
use App\Models\ExamSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamSession>
 */
class ExamSessionFactory extends Factory
{
    protected $model = ExamSession::class;

    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('+1 day', '+2 weeks');

        return [
            'exam_id' => Exam::factory(),
            'center_id' => Center::factory(),
            'name' => fake()->words(3, true),
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->modify('+2 hours'),
            'capacity' => 100,
            'status' => ExamSession::STATUS_PENDING,
            'settings' => ['check_in_required' => true],
        ];
    }
}
