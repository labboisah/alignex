<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\AccessControl;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'role_label' => AccessControl::roleLabel($user->role),
                    'organization_id' => $user->organization_id,
                    'center_id' => $user->center_id,
                    'school_id' => $user->school_id,
                ] : null,
                'permissions' => $user ? $this->permissionsFor($user) : [],
                'navigation' => $user ? $this->navigationFor($user) : [],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
            ],
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function permissionsFor(User $user): array
    {
        return [
            'manageOrganizations' => $user->hasPermission('manageOrganizations'),
            'manageAccessControls' => $user->hasPermission('manageAccessControls'),
            'manageCenters' => $user->hasPermission('manageCenters'),
            'manageSchools' => $user->hasPermission('manageSchools'),
            'manageUsers' => $user->hasPermission('manageUsers'),
            'manageQuestionBank' => $user->hasPermission('manageQuestionBank'),
            'manageExams' => $user->hasPermission('manageExams'),
            'viewSupervisorMonitor' => $user->hasPermission('viewSupervisorMonitor'),
            'viewReports' => $user->hasPermission('viewReports'),
            'manageSettings' => $user->hasPermission('manageSettings'),
        ];
    }

    /**
     * @return array<int, array{label: string, href: string, permission?: string}>
     */
    private function navigationFor(User $user): array
    {
        $items = [
            User::ROLE_SUPER_ADMIN => [
                ['label' => 'Dashboard', 'href' => '/dashboard'],
                ['label' => 'Organizations', 'href' => '/organizations', 'permission' => 'manageOrganizations'],
                ['label' => 'Access Controls', 'href' => '/access-controls', 'permission' => 'manageAccessControls'],
                ['label' => 'Centers', 'href' => '/centers', 'permission' => 'manageCenters'],
                ['label' => 'Schools', 'href' => '/schools', 'permission' => 'manageSchools'],
                ['label' => 'Users', 'href' => '/users', 'permission' => 'manageUsers'],
                ['label' => 'Subjects', 'href' => '/subjects'],
                ['label' => 'Topics', 'href' => '/topics'],
                ['label' => 'Question Bank', 'href' => '/question-bank', 'permission' => 'manageQuestionBank'],
                ['label' => 'Exams', 'href' => '/exams', 'permission' => 'manageExams'],
                ['label' => 'Candidates', 'href' => '/candidates'],
                ['label' => 'Results', 'href' => '/results'],
                ['label' => 'Reports', 'href' => '/reports', 'permission' => 'viewReports'],
                ['label' => 'Settings', 'href' => '/settings', 'permission' => 'manageSettings'],
            ],
            User::ROLE_ORGANIZATION_ADMIN => [
                ['label' => 'Dashboard', 'href' => '/dashboard'],
                ['label' => 'Users', 'href' => '/users', 'permission' => 'manageUsers'],
                ['label' => 'Subjects', 'href' => '/subjects'],
                ['label' => 'Topics', 'href' => '/topics'],
                ['label' => 'Question Bank', 'href' => '/question-bank', 'permission' => 'manageQuestionBank'],
                ['label' => 'Exams', 'href' => '/exams', 'permission' => 'manageExams'],
                ['label' => 'Candidates', 'href' => '/candidates'],
                ['label' => 'Results', 'href' => '/results'],
                ['label' => 'Reports', 'href' => '/reports', 'permission' => 'viewReports'],
                ['label' => 'Settings', 'href' => '/settings', 'permission' => 'manageSettings'],
            ],
            User::ROLE_CENTER_ADMIN => [
                ['label' => 'Dashboard', 'href' => '/dashboard'],
                ['label' => 'Centers', 'href' => '/centers', 'permission' => 'manageCenters'],
                ['label' => 'Assigned Exams', 'href' => '/assigned-exams'],
                ['label' => 'Supervisor Monitor', 'href' => '/supervisor-monitor', 'permission' => 'viewSupervisorMonitor'],
                ['label' => 'Candidate Activity', 'href' => '/candidate-activity'],
                ['label' => 'Reports', 'href' => '/reports', 'permission' => 'viewReports'],
            ],
            User::ROLE_SCHOOL_ADMIN => [
                ['label' => 'Dashboard', 'href' => '/dashboard'],
                ['label' => 'Schools', 'href' => '/schools', 'permission' => 'manageSchools'],
                ['label' => 'Exams', 'href' => '/exams', 'permission' => 'manageExams'],
                ['label' => 'Candidates', 'href' => '/candidates'],
                ['label' => 'Results', 'href' => '/results'],
                ['label' => 'Reports', 'href' => '/reports', 'permission' => 'viewReports'],
            ],
            User::ROLE_EXAMINER => [
                ['label' => 'Dashboard', 'href' => '/dashboard'],
                ['label' => 'Subjects', 'href' => '/subjects'],
                ['label' => 'Topics', 'href' => '/topics'],
                ['label' => 'Question Bank', 'href' => '/question-bank', 'permission' => 'manageQuestionBank'],
                ['label' => 'Exams', 'href' => '/exams', 'permission' => 'manageExams'],
                ['label' => 'Candidates', 'href' => '/candidates'],
                ['label' => 'Results', 'href' => '/results'],
                ['label' => 'Reports', 'href' => '/reports', 'permission' => 'viewReports'],
            ],
            User::ROLE_SUPERVISOR => [
                ['label' => 'Dashboard', 'href' => '/dashboard'],
                ['label' => 'Assigned Exams', 'href' => '/assigned-exams'],
                ['label' => 'Supervisor Monitor', 'href' => '/supervisor-monitor', 'permission' => 'viewSupervisorMonitor'],
                ['label' => 'Candidate Activity', 'href' => '/candidate-activity'],
                ['label' => 'Reports', 'href' => '/reports', 'permission' => 'viewReports'],
            ],
        ];

        return collect($items[$user->role] ?? [])
            ->filter(fn (array $item) => ! isset($item['permission']) || $user->hasPermission($item['permission']))
            ->values()
            ->all();
    }
}
