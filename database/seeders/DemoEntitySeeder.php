<?php

namespace Database\Seeders;

use App\Models\CbtCenter;
use App\Models\Organization;
use App\Models\ProfessionalSchool;
use App\Models\SecondarySchool;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoEntitySeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::query()->updateOrCreate(
            ['code' => 'ALIGNEX-DEMO-ORG'],
            [
                'name' => 'AlignEx Demo Organization',
                'organization_type' => 'certification_body',
                'description' => 'Safe demo organization for AlignEx platform testing.',
                'contact_person' => 'Demo Coordinator',
                'email' => 'demo.organization@alignex.test',
                'phone' => '08000000000',
                'address' => 'Demo Address',
                'status' => Organization::STATUS_ACTIVE,
            ]
        );

        $secondarySchool = SecondarySchool::query()->updateOrCreate(
            ['code' => 'ALIGNEX-SEC'],
            [
                'organization_id' => $organization->id,
                'name' => 'AlignEx Demo Secondary School',
                'contact_person' => 'Demo Principal',
                'email' => 'secondary.school@alignex.test',
                'phone' => '08000000001',
                'address' => 'Demo Secondary School Address',
                'status' => SecondarySchool::STATUS_ACTIVE,
            ]
        );

        $professionalSchool = ProfessionalSchool::query()->updateOrCreate(
            ['code' => 'ALIGNEX-PRO'],
            [
                'organization_id' => $organization->id,
                'name' => 'AlignEx Demo Professional School',
                'contact_person' => 'Demo Registrar',
                'email' => 'professional.school@alignex.test',
                'phone' => '08000000002',
                'address' => 'Demo Professional School Address',
                'status' => ProfessionalSchool::STATUS_ACTIVE,
            ]
        );

        $cbtCenter = CbtCenter::query()->updateOrCreate(
            ['code' => 'ALIGNEX-CBT'],
            [
                'organization_id' => $organization->id,
                'name' => 'AlignEx Demo CBT Center',
                'location' => 'Demo CBT Location',
                'capacity' => 100,
                'contact_person' => 'Demo Center Manager',
                'email' => 'cbt.center@alignex.test',
                'phone' => '08000000003',
                'status' => CbtCenter::STATUS_ACTIVE,
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'admin@alignex.com'],
            [
                'name' => 'Admin',
                'role' => User::ROLE_SUPER_ADMIN,
                'password' => Hash::make('admin'),
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'org.admin@alignex.test'],
            [
                'name' => 'Demo Organization Admin',
                'role' => User::ROLE_ORGANIZATION_ADMIN,
                'organization_id' => $organization->id,
                'password' => Hash::make('password'),
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'secondary.admin@alignex.test'],
            [
                'name' => 'Demo Secondary School Admin',
                'role' => User::ROLE_SECONDARY_SCHOOL_ADMIN,
                'secondary_school_id' => $secondarySchool->id,
                'password' => Hash::make('password'),
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'professional.admin@alignex.test'],
            [
                'name' => 'Demo Professional School Admin',
                'role' => User::ROLE_PROFESSIONAL_SCHOOL_ADMIN,
                'professional_school_id' => $professionalSchool->id,
                'password' => Hash::make('password'),
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'cbt.admin@alignex.test'],
            [
                'name' => 'Demo CBT Center Admin',
                'role' => User::ROLE_CBT_CENTER_ADMIN,
                'cbt_center_id' => $cbtCenter->id,
                'password' => Hash::make('password'),
            ]
        );
    }
}
