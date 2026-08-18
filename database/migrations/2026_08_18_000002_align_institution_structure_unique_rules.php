<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faculties', function (Blueprint $table): void {
            $table->dropUnique('faculties_code_unique');
            $table->unique(['institution_id', 'name'], 'faculties_institution_name_unique');
            $table->unique(['institution_id', 'code'], 'faculties_institution_code_unique');
        });

        Schema::table('departments', function (Blueprint $table): void {
            $table->dropUnique('departments_institution_code_unique');
            $table->unique(['institution_id', 'name'], 'departments_institution_name_unique');
            $table->unique(['faculty_id', 'code'], 'departments_faculty_code_unique');
        });

        Schema::table('programmes', function (Blueprint $table): void {
            $table->unique(['institution_id', 'name'], 'programmes_institution_name_unique');
            $table->unique(['department_id', 'code'], 'programmes_department_code_unique');
        });

        Schema::table('courses', function (Blueprint $table): void {
            $table->unique(['department_id', 'name'], 'courses_department_name_unique');
            $table->unique(['department_id', 'code'], 'courses_department_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->dropUnique('courses_department_name_unique');
            $table->dropUnique('courses_department_code_unique');
        });

        Schema::table('programmes', function (Blueprint $table): void {
            $table->dropUnique('programmes_institution_name_unique');
            $table->dropUnique('programmes_department_code_unique');
        });

        Schema::table('departments', function (Blueprint $table): void {
            $table->dropUnique('departments_institution_name_unique');
            $table->dropUnique('departments_faculty_code_unique');
            $table->unique(['institution_id', 'code'], 'departments_institution_code_unique');
        });

        Schema::table('faculties', function (Blueprint $table): void {
            $table->dropUnique('faculties_institution_name_unique');
            $table->dropUnique('faculties_institution_code_unique');
            $table->unique('code', 'faculties_code_unique');
        });
    }
};
