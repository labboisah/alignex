<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pricing_plans')) {
            return;
        }

        foreach ($this->featuresBySlug() as $slug => $features) {
            DB::table('pricing_plans')
                ->where('slug', $slug)
                ->update([
                    'features' => json_encode($features),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Keep current plan feature settings. This migration only enriches seeded defaults.
    }

    /**
     * @return array<string, array<string, bool>>
     */
    private function featuresBySlug(): array
    {
        return [
            'free' => [
                'online_delivery' => true,
                'offline_delivery' => true,
                'traditional_cbt' => true,
                'adaptive_exam' => false,
                'candidate_import' => true,
                'question_import' => true,
                'teacher_management' => false,
                'facilitator_management' => false,
                'csv_export' => true,
                'pdf_export' => false,
                'certificate_generation' => false,
                'custom_branding' => false,
                'webcam_proctoring' => false,
                'result_sync' => false,
                'multi_center' => false,
                'custom_reports' => false,
                'advanced_analytics' => false,
                'priority_support' => false,
                'dedicated_support' => false,
                'api_integration' => false,
                'offline_activation' => true,
                'exam_package_import' => true,
                'result_package_export' => true,
                'email_notifications' => false,
                'sms_notifications' => false,
                'official_live_exam_allowed' => false,
                'demo_watermark' => true,
            ],
            'basic' => [
                'online_delivery' => true,
                'offline_delivery' => true,
                'traditional_cbt' => true,
                'adaptive_exam' => false,
                'candidate_import' => true,
                'question_import' => true,
                'teacher_management' => false,
                'facilitator_management' => false,
                'csv_export' => true,
                'pdf_export' => false,
                'certificate_generation' => false,
                'custom_branding' => false,
                'webcam_proctoring' => false,
                'result_sync' => false,
                'multi_center' => false,
                'custom_reports' => false,
                'advanced_analytics' => false,
                'priority_support' => false,
                'dedicated_support' => false,
                'api_integration' => false,
                'offline_activation' => true,
                'exam_package_import' => true,
                'result_package_export' => true,
                'email_notifications' => false,
                'sms_notifications' => false,
                'official_live_exam_allowed' => true,
                'demo_watermark' => false,
            ],
            'standard' => [
                'online_delivery' => true,
                'offline_delivery' => true,
                'traditional_cbt' => true,
                'adaptive_exam' => false,
                'candidate_import' => true,
                'question_import' => true,
                'teacher_management' => true,
                'facilitator_management' => false,
                'csv_export' => true,
                'pdf_export' => true,
                'certificate_generation' => false,
                'custom_branding' => true,
                'webcam_proctoring' => false,
                'result_sync' => true,
                'multi_center' => false,
                'custom_reports' => false,
                'advanced_analytics' => true,
                'priority_support' => false,
                'dedicated_support' => false,
                'api_integration' => false,
                'offline_activation' => true,
                'exam_package_import' => true,
                'result_package_export' => true,
                'email_notifications' => true,
                'sms_notifications' => false,
                'official_live_exam_allowed' => true,
                'demo_watermark' => false,
            ],
            'professional' => [
                'online_delivery' => true,
                'offline_delivery' => true,
                'traditional_cbt' => true,
                'adaptive_exam' => true,
                'candidate_import' => true,
                'question_import' => true,
                'teacher_management' => true,
                'facilitator_management' => true,
                'csv_export' => true,
                'pdf_export' => true,
                'certificate_generation' => true,
                'custom_branding' => true,
                'webcam_proctoring' => true,
                'result_sync' => true,
                'multi_center' => false,
                'custom_reports' => true,
                'advanced_analytics' => true,
                'priority_support' => true,
                'dedicated_support' => false,
                'api_integration' => false,
                'offline_activation' => true,
                'exam_package_import' => true,
                'result_package_export' => true,
                'email_notifications' => true,
                'sms_notifications' => true,
                'official_live_exam_allowed' => true,
                'demo_watermark' => false,
            ],
            'enterprise' => [
                'online_delivery' => true,
                'offline_delivery' => true,
                'traditional_cbt' => true,
                'adaptive_exam' => true,
                'candidate_import' => true,
                'question_import' => true,
                'teacher_management' => true,
                'facilitator_management' => true,
                'csv_export' => true,
                'pdf_export' => true,
                'certificate_generation' => true,
                'custom_branding' => true,
                'webcam_proctoring' => true,
                'result_sync' => true,
                'multi_center' => true,
                'custom_reports' => true,
                'advanced_analytics' => true,
                'priority_support' => true,
                'dedicated_support' => true,
                'api_integration' => true,
                'offline_activation' => true,
                'exam_package_import' => true,
                'result_package_export' => true,
                'email_notifications' => true,
                'sms_notifications' => true,
                'official_live_exam_allowed' => true,
                'demo_watermark' => false,
            ],
        ];
    }
};
