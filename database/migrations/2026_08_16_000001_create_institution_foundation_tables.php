<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('institutions')) {
            Schema::create('institutions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name');
                $table->string('code')->unique();
                $table->string('institution_type')->default('university')->index();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->text('address')->nullable();
                $table->text('description')->nullable();
                $table->string('status')->default('active')->index();
                $table->timestamps();

                $table->index(['organization_id', 'status']);
                $table->index('name');
            });
        }

        if (! Schema::hasTable('faculties')) {
            Schema::create('faculties', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('code')->unique();
                $table->string('dean_name')->nullable();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->string('status')->default('active')->index();
                $table->timestamps();

                $table->index(['institution_id', 'status']);
                $table->index('name');
            });
        }

        if (! Schema::hasTable('departments')) {
            Schema::create('departments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
                $table->foreignId('faculty_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name');
                $table->string('code');
                $table->string('head_name')->nullable();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->string('status')->default('active')->index();
                $table->timestamps();

                $table->unique(['institution_id', 'code'], 'departments_institution_code_unique');
                $table->index(['institution_id', 'faculty_id', 'status']);
            });
        }

        Schema::table('programmes', function (Blueprint $table): void {
            if (! Schema::hasColumn('programmes', 'institution_id')) {
                $table->foreignId('institution_id')->nullable()->after('professional_school_id')->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('programmes', 'faculty_id')) {
                $table->foreignId('faculty_id')->nullable()->after('institution_id')->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('programmes', 'department_id')) {
                $table->foreignId('department_id')->nullable()->after('faculty_id')->constrained()->nullOnDelete();
            }
        });

        Schema::table('courses', function (Blueprint $table): void {
            if (! Schema::hasColumn('courses', 'institution_id')) {
                $table->foreignId('institution_id')->nullable()->after('professional_school_id')->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('courses', 'faculty_id')) {
                $table->foreignId('faculty_id')->nullable()->after('institution_id')->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('courses', 'department_id')) {
                $table->foreignId('department_id')->nullable()->after('faculty_id')->constrained()->nullOnDelete();
            }
        });

        Schema::table('question_banks', function (Blueprint $table): void {
            if (! Schema::hasColumn('question_banks', 'institution_id')) {
                $table->foreignId('institution_id')->nullable()->after('organization_id')->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('question_banks', 'faculty_id')) {
                $table->foreignId('faculty_id')->nullable()->after('institution_id')->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('question_banks', 'department_id')) {
                $table->foreignId('department_id')->nullable()->after('faculty_id')->constrained()->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('question_banks', function (Blueprint $table): void {
            foreach (['department_id', 'faculty_id', 'institution_id'] as $column) {
                if (Schema::hasColumn('question_banks', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }
        });

        Schema::table('courses', function (Blueprint $table): void {
            foreach (['department_id', 'faculty_id', 'institution_id'] as $column) {
                if (Schema::hasColumn('courses', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }
        });

        Schema::table('programmes', function (Blueprint $table): void {
            foreach (['department_id', 'faculty_id', 'institution_id'] as $column) {
                if (Schema::hasColumn('programmes', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }
        });

        Schema::dropIfExists('departments');
        Schema::dropIfExists('faculties');
        Schema::dropIfExists('institutions');
    }
};
