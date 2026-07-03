<?php

namespace App\Support;

use App\Models\User;

class AccessControl
{
    /**
     * @return array<string, array{label: string, description: string}>
     */
    public static function roles(): array
    {
        return [
            User::ROLE_SUPER_ADMIN => [
                'label' => 'Super Admin',
                'description' => 'Manages platform configuration, organizations, users, access controls, and all operational data.',
            ],
            User::ROLE_ORGANIZATION_ADMIN => [
                'label' => 'Organization Admin',
                'description' => 'Manages users, exam operations, settings, and reports inside one organization.',
            ],
            User::ROLE_CENTER_ADMIN => [
                'label' => 'Center Admin',
                'description' => 'Manages one CBT center and delivery operations for allocated exams.',
            ],
            User::ROLE_CBT_CENTER_ADMIN => [
                'label' => 'CBT Center Admin',
                'description' => 'Manages one CBT center, candidates, question banks, traditional exams, and adaptive exams.',
            ],
            User::ROLE_SCHOOL_ADMIN => [
                'label' => 'School Admin',
                'description' => 'Manages one school and its candidates, exam assignments, and reports.',
            ],
            User::ROLE_SECONDARY_SCHOOL_ADMIN => [
                'label' => 'Secondary School Admin',
                'description' => 'Manages one secondary school, academic structure, exams, students, and results.',
            ],
            User::ROLE_PROFESSIONAL_SCHOOL_ADMIN => [
                'label' => 'Professional School Admin',
                'description' => 'Manages one professional school, programmes, courses, modules, trainees, exams, and certificates.',
            ],
            User::ROLE_EXAMINER => [
                'label' => 'Examiner',
                'description' => 'Builds subjects, question banks, exams, candidates, results, and reports.',
            ],
            User::ROLE_SUPERVISOR => [
                'label' => 'Supervisor',
                'description' => 'Monitors assigned exams, candidate activity, incidents, and reports.',
            ],
            User::ROLE_CANDIDATE => [
                'label' => 'Candidate',
                'description' => 'Uses only the candidate exam interface and cannot access the admin portal.',
            ],
        ];
    }

    /**
     * @return array<string, array{label: string, group: string, description: string}>
     */
    public static function permissions(): array
    {
        return [
            'manageOrganizations' => [
                'label' => 'Manage organizations',
                'group' => 'Platform',
                'description' => 'Create, view, update, and retire organizations.',
            ],
            'manageAccessControls' => [
                'label' => 'Manage access controls',
                'group' => 'Platform',
                'description' => 'Review and update system role permission assignments.',
            ],
            'manageAdminRegistrations' => [
                'label' => 'Manage applications',
                'group' => 'Platform',
                'description' => 'Review, edit, approve, or reject organization, school, and center applications.',
            ],
            'manageCenters' => [
                'label' => 'Manage centers',
                'group' => 'Administration',
                'description' => 'Manage CBT centers and their delivery facilities within the authorized scope.',
            ],
            'manageSchools' => [
                'label' => 'Manage schools',
                'group' => 'Administration',
                'description' => 'Manage school records within the authorized scope.',
            ],
            'manageUsers' => [
                'label' => 'Manage users',
                'group' => 'Administration',
                'description' => 'Invite, update, and deactivate users within the authorized scope.',
            ],
            'manageQuestionBank' => [
                'label' => 'Manage question bank',
                'group' => 'Assessment',
                'description' => 'Maintain subjects, topics, question banks, questions, options, imports, and review workflow.',
            ],
            'manageExams' => [
                'label' => 'Manage exams',
                'group' => 'Assessment',
                'description' => 'Create, configure, schedule, assign, and operate exams.',
            ],
            'viewSupervisorMonitor' => [
                'label' => 'View supervisor monitor',
                'group' => 'Supervision',
                'description' => 'Open live monitoring, assigned exams, and candidate activity screens.',
            ],
            'viewReports' => [
                'label' => 'View reports',
                'group' => 'Reporting',
                'description' => 'View reports, result summaries, exports, and operational reporting.',
            ],
            'manageSettings' => [
                'label' => 'Manage settings',
                'group' => 'Administration',
                'description' => 'Manage organization or platform settings according to scope.',
            ],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function defaults(): array
    {
        return [
            User::ROLE_SUPER_ADMIN => array_keys(self::permissions()),
            User::ROLE_ORGANIZATION_ADMIN => [
                'manageCenters',
                'manageSchools',
                'manageUsers',
                'manageQuestionBank',
                'manageExams',
                'viewReports',
                'manageSettings',
            ],
            User::ROLE_CENTER_ADMIN => [
                'manageCenters',
                'manageQuestionBank',
                'manageExams',
                'viewSupervisorMonitor',
                'viewReports',
            ],
            User::ROLE_CBT_CENTER_ADMIN => [
                'manageCenters',
                'manageQuestionBank',
                'manageExams',
                'viewSupervisorMonitor',
                'viewReports',
            ],
            User::ROLE_SCHOOL_ADMIN => [
                'manageSchools',
                'manageQuestionBank',
                'manageExams',
                'viewReports',
            ],
            User::ROLE_SECONDARY_SCHOOL_ADMIN => [
                'manageSchools',
                'manageQuestionBank',
                'manageExams',
                'viewReports',
                'manageSettings',
            ],
            User::ROLE_PROFESSIONAL_SCHOOL_ADMIN => [
                'manageSchools',
                'manageQuestionBank',
                'manageExams',
                'viewReports',
                'manageSettings',
            ],
            User::ROLE_EXAMINER => [
                'manageQuestionBank',
                'manageExams',
                'viewReports',
            ],
            User::ROLE_SUPERVISOR => [
                'viewSupervisorMonitor',
                'viewReports',
            ],
            User::ROLE_CANDIDATE => [],
            User::ROLE_STUDENT => [],
        ];
    }

    public static function roleLabel(string $role): string
    {
        return self::roles()[$role]['label'] ?? str($role)->replace('_', ' ')->title()->toString();
    }
}
