<?php

namespace App\Support;

class PlanFeatures
{
    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            'online_delivery' => 'Online exam delivery',
            'offline_delivery' => 'Offline center delivery',
            'traditional_cbt' => 'Traditional CBT',
            'adaptive_exam' => 'Adaptive exams',
            'candidate_import' => 'Candidate import',
            'question_import' => 'Question import',
            'teacher_management' => 'Teacher management',
            'facilitator_management' => 'Facilitator management',
            'csv_export' => 'CSV result export',
            'pdf_export' => 'PDF result export',
            'certificate_generation' => 'Certificate generation',
            'custom_branding' => 'Custom branding',
            'webcam_proctoring' => 'Webcam proctoring',
            'result_sync' => 'Result sync',
            'multi_center' => 'Multi-center delivery',
            'custom_reports' => 'Custom reports',
            'advanced_analytics' => 'Advanced analytics',
            'priority_support' => 'Priority support',
            'dedicated_support' => 'Dedicated support',
            'api_integration' => 'API integration',
            'offline_activation' => 'Offline activation',
            'exam_package_import' => 'Exam package import',
            'result_package_export' => 'Result package export',
            'email_notifications' => 'Email notifications',
            'sms_notifications' => 'SMS notifications',
            'official_live_exam_allowed' => 'Official live exams',
            'demo_watermark' => 'Demo watermark',
        ];
    }
}
