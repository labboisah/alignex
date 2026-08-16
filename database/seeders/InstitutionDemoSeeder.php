<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Institution;
use App\Models\Programme;
use App\Models\QuestionBank;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class InstitutionDemoSeeder extends Seeder
{
    public function run(): void
    {
        $institution = Institution::query()->updateOrCreate(
            ['code' => 'NDU'],
            [
                'name' => 'Niger Delta University',
                'institution_type' => 'university',
                'email' => 'info@ndu-demo.edu.ng',
                'phone' => '08010000001',
                'address' => 'Bayelsa State, Nigeria',
                'description' => 'Higher institution demo for academic administration, course delivery, and question-bank setup.',
                'status' => Institution::STATUS_ACTIVE,
            ]
        );

        $faculty = Faculty::query()->updateOrCreate(
            ['institution_id' => $institution->id, 'code' => 'FET'],
            [
                'name' => 'Faculty of Engineering and Technology',
                'dean_name' => 'Prof. Ada Okafor',
                'email' => 'engineering@ndu-demo.edu.ng',
                'phone' => '08010000002',
                'status' => Faculty::STATUS_ACTIVE,
            ]
        );

        $department = Department::query()->updateOrCreate(
            ['institution_id' => $institution->id, 'faculty_id' => $faculty->id, 'code' => 'CSE'],
            [
                'name' => 'Computer Science Department',
                'head_name' => 'Dr. Samuel Ebi',
                'email' => 'cse@ndu-demo.edu.ng',
                'phone' => '08010000003',
                'status' => Department::STATUS_ACTIVE,
            ]
        );

        $programme = Programme::query()->updateOrCreate(
            ['institution_id' => $institution->id, 'faculty_id' => $faculty->id, 'department_id' => $department->id, 'code' => 'BSC-CSE'],
            [
                'name' => 'B.Sc. Computer Science',
                'description' => 'Undergraduate programme for software engineering and digital systems.',
                'status' => Programme::STATUS_ACTIVE,
            ]
        );

        $course = Course::query()->updateOrCreate(
            ['institution_id' => $institution->id, 'faculty_id' => $faculty->id, 'department_id' => $department->id, 'programme_id' => $programme->id, 'code' => 'CIT-201'],
            [
                'name' => 'Data Structures and Algorithms',
                'description' => 'Core course focused on algorithmic thinking, data structures, and problem solving.',
                'status' => Course::STATUS_ACTIVE,
            ]
        );

        QuestionBank::query()->updateOrCreate(
            ['institution_id' => $institution->id, 'faculty_id' => $faculty->id, 'department_id' => $department->id, 'programme_id' => $programme->id, 'course_id' => $course->id, 'code' => 'CIT-201-QB'],
            [
                'name' => 'CIT 201 Question Bank',
                'description' => 'Assessment bank for the Data Structures and Algorithms course.',
                'status' => QuestionBank::STATUS_ACTIVE,
                'created_by' => 1,
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'institution.admin@alignex.test'],
            [
                'name' => 'Institution Demo Admin',
                'role' => User::ROLE_PROFESSIONAL_SCHOOL_ADMIN,
                'organization_id' => null,
                'institution_id' => $institution->id,
                'password' => Hash::make('password'),
                'status' => User::STATUS_ACTIVE,
            ]
        );
    }
}
