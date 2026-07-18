<?php

namespace Tests\Feature;

use App\Models\NotificationDelivery;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationDispatcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_candidate_exam_details_are_recorded_for_email_and_sms_in_dry_run(): void
    {
        config([
            'notifications.dry_run' => true,
            'services.bulksms_nigeria.dry_run' => true,
        ]);

        Mail::fake();
        Http::preventStrayRequests();

        $deliveries = app(NotificationDispatcher::class)->dispatch(
            'candidate_exam_details',
            [
                'name' => 'Aisha Bello',
                'email' => 'aisha@example.test',
                'phone' => '2348012345678',
            ],
            [
                'candidate_name' => 'Aisha Bello',
                'exam_title' => 'Scholarship CBT',
                'start_time' => '20 July 2026, 9:00 AM',
                'duration' => '2 hours',
                'center_name' => 'Main CBT Center',
                'exam_code' => 'AX-2026',
                'registration_number' => 'REG-001',
                'organization_name' => 'AlignEx Academy',
            ],
        );

        $this->assertCount(2, $deliveries);
        Mail::assertNothingSent();

        $this->assertDatabaseHas('notification_deliveries', [
            'type' => 'candidate_exam_details',
            'channel' => 'email',
            'status' => NotificationDelivery::STATUS_DRY_RUN,
            'provider' => 'smtp',
            'recipient_email' => 'aisha@example.test',
            'subject' => 'Exam Details: Scholarship CBT',
        ]);

        $this->assertDatabaseHas('notification_deliveries', [
            'type' => 'candidate_exam_details',
            'channel' => 'sms',
            'status' => NotificationDelivery::STATUS_DRY_RUN,
            'provider' => 'bulksms_nigeria',
            'recipient_phone' => '2348012345678',
        ]);
    }
}
