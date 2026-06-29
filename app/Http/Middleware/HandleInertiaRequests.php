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
                    'secondary_school_id' => $user->secondary_school_id,
                    'professional_school_id' => $user->professional_school_id,
                    'cbt_center_id' => $user->cbt_center_id,
                    'current_context' => $user->currentContext(),
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
            'manageAdminRegistrations' => $user->hasPermission('manageAdminRegistrations'),
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
                ['label' => 'Applications', 'href' => '/admin-registrations', 'permission' => 'manageAdminRegistrations'],
                ['label' => 'Centers', 'href' => '/centers', 'permission' => 'manageCenters'],
                ['label' => 'Schools', 'href' => '/schools', 'permission' => 'manageSchools'],
                ['label' => 'Users', 'href' => '/users', 'permission' => 'manageUsers'],
                ['label' => 'Subjects', 'href' => '/subjects'],
                ['label' => 'Topics', 'href' => '/topics'],
                ['label' => 'Question Bank', 'href' => '/question-bank', 'permission' => 'manageQuestionBank'],
                ['label' => 'Questions', 'href' => '/questions', 'permission' => 'manageQuestionBank'],
                ['label' => 'Exams', 'href' => '/exams', 'permission' => 'manageExams'],
                ['label' => 'Candidates', 'href' => '/candidates'],
                ['label' => 'Results', 'href' => '/results'],
                ['label' => 'Reports', 'href' => '/reports', 'permission' => 'viewReports'],
                ['label' => 'Settings', 'href' => '/settings', 'permission' => 'manageSettings'],
            ],
            User::ROLE_ORGANIZATION_ADMIN => [
                ['label' => 'Dashboard', 'href' => '/dashboard'],
                ['label' => 'Candidates', 'href' => '/candidates', 'permission' => 'manageExams'],
                ['label' => 'Question Bank', 'href' => '/question-bank', 'permission' => 'manageQuestionBank'],
                ['label' => 'Exams', 'href' => '/exams', 'permission' => 'manageExams'],
                ['label' => 'Recruitment Exams', 'href' => '/exams?category=recruitment', 'permission' => 'manageExams'],
                ['label' => 'Assessment Exams', 'href' => '/exams?category=assessment', 'permission' => 'manageExams'],
                ['label' => 'Certification Exams', 'href' => '/exams?category=certification', 'permission' => 'manageExams'],
                ['label' => 'Adaptive Exams', 'href' => '/exams?mode=adaptive', 'permission' => 'manageExams'],
                ['label' => 'Results', 'href' => '/results'],
                ['label' => 'Reports', 'href' => '/reports', 'permission' => 'viewReports'],
                ['label' => 'Users', 'href' => '/users', 'permission' => 'manageUsers'],
                ['label' => 'Settings', 'href' => '/settings', 'permission' => 'manageSettings'],
            ],
            User::ROLE_CENTER_ADMIN => [
                ['label' => 'Dashboard', 'href' => '/dashboard'],
                ['label' => 'Centers', 'href' => '/centers', 'permission' => 'manageCenters'],
                ['label' => 'Subjects', 'href' => '/subjects', 'permission' => 'manageQuestionBank'],
                ['label' => 'Topics', 'href' => '/topics', 'permission' => 'manageQuestionBank'],
                ['label' => 'Question Bank', 'href' => '/question-bank', 'permission' => 'manageQuestionBank'],
                ['label' => 'Questions', 'href' => '/questions', 'permission' => 'manageQuestionBank'],
                ['label' => 'Exams', 'href' => '/exams', 'permission' => 'manageExams'],
                ['label' => 'Candidates', 'href' => '/candidates', 'permission' => 'manageExams'],
                ['label' => 'Assigned Exams', 'href' => '/assigned-exams'],
                ['label' => 'Supervisor Monitor', 'href' => '/supervisor-monitor', 'permission' => 'viewSupervisorMonitor'],
                ['label' => 'Candidate Activity', 'href' => '/candidate-activity'],
                ['label' => 'Results', 'href' => '/results', 'permission' => 'viewReports'],
                ['label' => 'Reports', 'href' => '/reports', 'permission' => 'viewReports'],
            ],
            User::ROLE_SCHOOL_ADMIN => [
                ['label' => 'Dashboard', 'href' => '/dashboard'],
                ['label' => 'My Schools', 'href' => '/schools', 'permission' => 'manageSchools'],
                ['label' => 'Subjects', 'href' => '/subjects', 'permission' => 'manageQuestionBank'],
                ['label' => 'Topics', 'href' => '/topics', 'permission' => 'manageQuestionBank'],
                ['label' => 'Question Bank', 'href' => '/question-bank', 'permission' => 'manageQuestionBank'],
                ['label' => 'Questions', 'href' => '/questions', 'permission' => 'manageQuestionBank'],
                ['label' => 'Exams', 'href' => '/exams', 'permission' => 'manageExams'],
                ['label' => 'Secondary School', 'href' => '/secondary-school', 'permission' => 'manageExams'],
                ['label' => 'Candidates', 'href' => '/candidates'],
                ['label' => 'Results', 'href' => '/results'],
                ['label' => 'Reports', 'href' => '/reports', 'permission' => 'viewReports'],
            ],
            User::ROLE_EXAMINER => [
                ['label' => 'Dashboard', 'href' => '/dashboard'],
                ['label' => 'Subjects', 'href' => '/subjects'],
                ['label' => 'Topics', 'href' => '/topics'],
                ['label' => 'Question Bank', 'href' => '/question-bank', 'permission' => 'manageQuestionBank'],
                ['label' => 'Questions', 'href' => '/questions', 'permission' => 'manageQuestionBank'],
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
                ['label' => 'Results', 'href' => '/results', 'permission' => 'viewReports'],
                ['label' => 'Reports', 'href' => '/reports', 'permission' => 'viewReports'],
            ],
        ];

        $navigation = collect($items[$user->role] ?? []);

        if (($user->currentContext()['type'] ?? null) === 'organization' && $user->organization_id) {
            $organizationHref = '/organizations/'.$user->organization_id;

            if ($user->organization?->secondarySchools()->exists()) {
                $navigation->push(['label' => 'Secondary Schools', 'href' => $organizationHref]);
            }

            if ($user->organization?->professionalSchools()->exists()) {
                $navigation->push(['label' => 'Professional Schools', 'href' => $organizationHref]);
            }

            if ($user->organization?->cbtCenters()->exists()) {
                $navigation->push(['label' => 'CBT Centers', 'href' => $organizationHref]);
            }
        }

        return $navigation
            ->filter(fn (array $item) => ! isset($item['permission']) || $user->hasPermission($item['permission']))
            ->values()
            ->all();
    }
}
