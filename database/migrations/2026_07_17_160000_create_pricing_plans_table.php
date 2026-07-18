<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description');
            $table->unsignedInteger('price');
            $table->string('currency', 10)->default('NGN');
            $table->string('billing_cycle')->default('yearly')->index();
            $table->json('delivery_modes')->nullable();
            $table->json('limits')->nullable();
            $table->json('features')->nullable();
            $table->json('highlights')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_featured')->default(false)->index();
            $table->string('cta_label')->default('Register');
            $table->unsignedSmallInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });

        DB::table('pricing_plans')->insert($this->defaultPlans());

        Schema::table('admin_registration_requests', function (Blueprint $table): void {
            $table->foreignId('pricing_plan_id')
                ->nullable()
                ->after('entity_id')
                ->constrained('pricing_plans')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('admin_registration_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('pricing_plan_id');
        });

        Schema::dropIfExists('pricing_plans');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function defaultPlans(): array
    {
        $now = now();

        return [
            [
                'slug' => 'free',
                'name' => 'Free Plan',
                'description' => 'For demonstrations, testing, onboarding, and light practice use. Not for official live exams.',
                'price' => 0,
                'currency' => 'NGN',
                'billing_cycle' => 'forever',
                'delivery_modes' => json_encode(['online', 'offline']),
                'limits' => json_encode([
                    'max_candidates' => 30,
                    'max_exams_per_month' => 2,
                    'max_admin_users' => 1,
                    'max_devices' => 1,
                ]),
                'features' => json_encode([
                    'online_delivery' => true,
                    'offline_delivery' => true,
                    'official_live_exam_allowed' => false,
                    'demo_watermark' => true,
                ]),
                'highlights' => json_encode(['30 candidates', '2 exams per month', 'Exam package import', 'Result package export', 'Demo watermark']),
                'is_active' => true,
                'is_featured' => false,
                'cta_label' => 'Start Free',
                'sort_order' => 10,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug' => 'basic',
                'name' => 'Basic',
                'description' => 'For small users who need simple online or offline traditional CBT exams.',
                'price' => 50000,
                'currency' => 'NGN',
                'billing_cycle' => 'yearly',
                'delivery_modes' => json_encode(['online', 'offline']),
                'limits' => json_encode([
                    'max_candidates' => 100,
                    'max_exams_per_month' => 5,
                    'max_admin_users' => 1,
                    'max_devices' => 1,
                ]),
                'features' => json_encode([
                    'online_delivery' => true,
                    'offline_delivery' => true,
                    'official_live_exam_allowed' => true,
                ]),
                'highlights' => json_encode(['100 candidates', '5 exams per month', 'Candidate and question import', 'CSV result export', '1 center device']),
                'is_active' => true,
                'is_featured' => false,
                'cta_label' => 'Choose Basic',
                'sort_order' => 20,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug' => 'standard',
                'name' => 'Standard',
                'description' => 'For schools, small CBT centers, and regular exam users who need reports and branding.',
                'price' => 150000,
                'currency' => 'NGN',
                'billing_cycle' => 'yearly',
                'delivery_modes' => json_encode(['online', 'offline']),
                'limits' => json_encode([
                    'max_candidates' => 500,
                    'max_exams_per_month' => 20,
                    'max_admin_users' => 3,
                    'max_devices' => 1,
                ]),
                'features' => json_encode([
                    'online_delivery' => true,
                    'offline_delivery' => true,
                    'pdf_export' => true,
                    'custom_branding' => true,
                    'official_live_exam_allowed' => true,
                ]),
                'highlights' => json_encode(['500 candidates', '20 exams per month', 'PDF result export', 'Client branding', 'Result sync']),
                'is_active' => true,
                'is_featured' => true,
                'cta_label' => 'Choose Standard',
                'sort_order' => 30,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug' => 'professional',
                'name' => 'Professional',
                'description' => 'For academies, NGOs, certification bodies, and professional exam operators.',
                'price' => 300000,
                'currency' => 'NGN',
                'billing_cycle' => 'yearly',
                'delivery_modes' => json_encode(['online', 'offline']),
                'limits' => json_encode([
                    'max_candidates' => 1500,
                    'max_exams_per_month' => 50,
                    'max_admin_users' => 10,
                    'max_devices' => 2,
                ]),
                'features' => json_encode([
                    'online_delivery' => true,
                    'offline_delivery' => true,
                    'adaptive_exam' => true,
                    'certificate_generation' => true,
                    'priority_support' => true,
                    'official_live_exam_allowed' => true,
                ]),
                'highlights' => json_encode(['1,500 candidates', 'Adaptive exams', 'Certificates', 'Webcam proctoring', 'Priority support']),
                'is_active' => true,
                'is_featured' => false,
                'cta_label' => 'Choose Professional',
                'sort_order' => 40,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug' => 'enterprise',
                'name' => 'Enterprise',
                'description' => 'For large CBT centers, agencies, recruitment bodies, and multi-location clients.',
                'price' => 750000,
                'currency' => 'NGN',
                'billing_cycle' => 'contract',
                'delivery_modes' => json_encode(['online', 'offline']),
                'limits' => json_encode([
                    'max_candidates' => null,
                    'max_exams_per_month' => null,
                    'max_admin_users' => null,
                    'max_devices' => null,
                ]),
                'features' => json_encode([
                    'online_delivery' => true,
                    'offline_delivery' => true,
                    'multi_center' => true,
                    'api_integration' => true,
                    'dedicated_support' => true,
                    'official_live_exam_allowed' => true,
                ]),
                'highlights' => json_encode(['Custom limits', 'Multi-center support', 'API integration', 'Dedicated support', 'Custom reports']),
                'is_active' => true,
                'is_featured' => false,
                'cta_label' => 'Contact Sales',
                'sort_order' => 50,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];
    }
};
