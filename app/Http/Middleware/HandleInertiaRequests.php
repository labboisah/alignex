<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\CurrentContextService;
use App\Services\ExamSetupGuideService;
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
        $contextService = app(CurrentContextService::class);
        $currentContext = $user ? $contextService->current($user) : null;
        $availableContexts = $user ? $contextService->available($user)->values()->all() : [];

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
                ] : null,
                'role' => $user ? [
                    'name' => $user->role,
                    'label' => AccessControl::roleLabel($user->role),
                ] : null,
                'permissions' => $user ? $this->permissionsFor($user) : [],
                'current_context' => $currentContext,
                'available_contexts' => $availableContexts,
                'navigation' => $user ? $this->navigationFor($user, $currentContext) : [],
                'setup_guide' => $user ? app(ExamSetupGuideService::class)->forUser($user, $currentContext) : null,
            ],
            'current_context' => $currentContext,
            'available_contexts' => $availableContexts,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'import_summary' => fn () => $request->session()->get('import_summary'),
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
     * @return array<int, array{label: string, href?: string, permission?: string, children?: array<int, array{label: string, href: string, permission?: string}>}>
     */
    private function navigationFor(User $user, ?array $context = null): array
    {
        $contextType = $context['type'] ?? null;
        $contextId = $context['id'] ?? null;
        $contextSource = $context['source'] ?? null;
        $secondaryBase = $contextType === 'secondary_school' && $contextId
            ? ($contextSource === 'legacy_school' ? '/secondary-school' : '/secondary-schools/'.$contextId)
            : '/secondary-school';
        $secondaryHref = fn (string $path): string => $secondaryBase.$path;
        $professionalBase = $contextType === 'professional_school' && $contextId
            ? '/professional-schools/'.$contextId
            : '/professional-schools';
        $cbtCenterBase = $contextType === 'cbt_center' && $contextId
            ? '/cbt-centers/'.$contextId
            : '/cbt-centers';

        if ($user->isSuperAdmin() && ! $contextType) {
            $navigation = collect([
                ['label' => 'Dashboard', 'href' => '/dashboard'],
                ['label' => 'Platform', 'children' => [
                    ['label' => 'Organizations', 'href' => '/organizations', 'permission' => 'manageOrganizations'],
                    ['label' => 'Applications', 'href' => '/admin-registrations', 'permission' => 'manageAdminRegistrations'],
                    ['label' => 'CBT Centers', 'href' => '/cbt-centers', 'permission' => 'manageCenters'],
                ]],
                ['label' => 'Admin', 'children' => [
                    ['label' => 'Users', 'href' => '/users', 'permission' => 'manageUsers'],
                    ['label' => 'Access Controls', 'href' => '/access-controls', 'permission' => 'manageAccessControls'],
                ]],
                ['label' => 'Reports', 'children' => [
                    ['label' => 'Reports', 'href' => '/reports', 'permission' => 'viewReports'],
                ]],
            ]);
        } elseif ($user->isTeacher()) {
            $navigation = collect([
                ['label' => 'Dashboard', 'href' => '/dashboard'],
                ['label' => 'Question Management', 'children' => [
                    ['label' => 'Subjects', 'href' => '/subjects', 'permission' => 'manageQuestionBank'],
                    ['label' => 'Question Bank', 'href' => '/question-bank', 'permission' => 'manageQuestionBank'],
                    ['label' => 'Questions', 'href' => '/questions', 'permission' => 'manageQuestionBank'],
                ]],
                ['label' => 'Exam', 'children' => [
                    ['label' => 'Assessments', 'href' => '/exams?category=assessment', 'permission' => 'manageExams'],
                ]],
                ['label' => 'Results', 'href' => '/results', 'permission' => 'viewReports'],
            ]);
        } else {
            $navigation = collect(match ($contextType) {
                'secondary_school' => [
                    ['label' => 'Dashboard', 'href' => '/dashboard'],
                    ['label' => 'Admin', 'children' => [
                        ['label' => 'Academic Sessions', 'href' => $secondaryHref('/academic-sessions'), 'permission' => 'manageSchools'],
                        ['label' => 'Terms', 'href' => $secondaryHref('/terms'), 'permission' => 'manageSchools'],
                        ['label' => 'Classes', 'href' => $secondaryHref('/classes'), 'permission' => 'manageSchools'],
                        ['label' => 'Students', 'href' => $secondaryHref('/students')],
                        ['label' => 'Student Groups', 'href' => $secondaryHref('/student-groups'), 'permission' => 'manageSchools'],
                        ['label' => 'Teachers', 'href' => $secondaryHref('/teachers'), 'permission' => 'manageSchools'],
                    ]],
                    ['label' => 'Exam', 'children' => [
                        ['label' => 'Subjects', 'href' => '/subjects', 'permission' => 'manageQuestionBank'],
                        ['label' => 'Question Bank', 'href' => '/question-bank', 'permission' => 'manageQuestionBank'],
                        ['label' => 'Questions', 'href' => '/questions', 'permission' => 'manageQuestionBank'],
                        ['label' => 'Exams', 'href' => '/exams?category=terminal', 'permission' => 'manageExams'],
                        ['label' => 'Assessments', 'href' => '/exams?category=assessment', 'permission' => 'manageExams'],
                    ]],
                    ['label' => 'Results', 'href' => '/results', 'permission' => 'viewReports'],
                    ['label' => 'Reports', 'href' => '/reports', 'permission' => 'viewReports'],
                    ['label' => 'Settings', 'href' => '/settings', 'permission' => 'manageSettings'],
                ],
                'professional_school' => [
                    ['label' => 'Dashboard', 'href' => '/dashboard'],
                    ['label' => 'Academics', 'children' => [
                        ['label' => 'Programmes', 'href' => $professionalBase.'/programmes', 'permission' => 'manageSchools'],
                        ['label' => 'Courses', 'href' => $professionalBase.'/courses', 'permission' => 'manageSchools'],
                        ['label' => 'Modules', 'href' => $professionalBase.'/modules', 'permission' => 'manageSchools'],
                        ['label' => 'Training Batches', 'href' => $professionalBase.'/training-batches', 'permission' => 'manageSchools'],
                    ]],
                    ['label' => 'Candidates', 'children' => [
                        ['label' => 'Candidates / Trainees', 'href' => $professionalBase.'/candidates', 'permission' => 'manageExams'],
                    ]],
                    ['label' => 'Exam', 'children' => [
                        ['label' => 'Question Bank', 'href' => $professionalBase.'/question-banks', 'permission' => 'manageQuestionBank'],
                        ['label' => 'Questions', 'href' => $professionalBase.'/questions', 'permission' => 'manageQuestionBank'],
                        ['label' => 'Traditional Exams', 'href' => '/exams?mode=traditional', 'permission' => 'manageExams'],
                        ['label' => 'Adaptive Exams', 'href' => '/exams?mode=adaptive', 'permission' => 'manageExams'],
                        ['label' => 'Certification Exams', 'href' => '/exams?category=certification', 'permission' => 'manageExams'],
                    ]],
                    ['label' => 'Reports', 'children' => [
                        ['label' => 'Results', 'href' => '/results', 'permission' => 'viewReports'],
                        ['label' => 'Certificates', 'href' => '/verify-certificate', 'permission' => 'viewReports'],
                        ['label' => 'Reports', 'href' => '/reports', 'permission' => 'viewReports'],
                    ]],
                    ['label' => 'Settings', 'href' => '/settings', 'permission' => 'manageSettings'],
                ],
                'cbt_center' => [
                    ['label' => 'Dashboard', 'href' => '/dashboard'],
                    ['label' => 'Candidates', 'children' => [
                        ['label' => 'Candidates', 'href' => $cbtCenterBase.'/candidates', 'permission' => 'manageExams'],
                        ['label' => 'Candidate Groups', 'href' => '/candidate-groups', 'permission' => 'manageExams'],
                    ]],
                    ['label' => 'Exam', 'children' => [
                        ['label' => 'Subjects', 'href' => '/subjects', 'permission' => 'manageQuestionBank'],
                        ['label' => 'Question Bank', 'href' => $cbtCenterBase.'/question-banks', 'permission' => 'manageQuestionBank'],
                        ['label' => 'Questions', 'href' => '/questions', 'permission' => 'manageQuestionBank'],
                        ['label' => 'Exams', 'href' => '/exams', 'permission' => 'manageExams'],
                        ['label' => 'Traditional CBT Exams', 'href' => '/exams?mode=traditional', 'permission' => 'manageExams'],
                        ['label' => 'Adaptive CBT Exams', 'href' => '/exams?mode=adaptive', 'permission' => 'manageExams'],
                    ]],
                    ['label' => 'Reports', 'children' => [
                        ['label' => 'Results', 'href' => '/results', 'permission' => 'viewReports'],
                        ['label' => 'Reports', 'href' => '/reports', 'permission' => 'viewReports'],
                    ]],
                    ['label' => 'Center Settings', 'href' => $cbtCenterBase.'/edit', 'permission' => 'manageCenters'],
                ],
                default => [
                    ['label' => 'Dashboard', 'href' => '/dashboard'],
                    ['label' => 'Candidates', 'children' => [
                        ['label' => 'Candidates', 'href' => '/candidates', 'permission' => 'manageExams'],
                        ['label' => 'Candidate Groups', 'href' => '/candidate-groups', 'permission' => 'manageExams'],
                    ]],
                    ['label' => 'Questions', 'children' => [
                        ['label' => 'Subjects', 'href' => '/subjects', 'permission' => 'manageQuestionBank'],
                        ['label' => 'Question Bank', 'href' => '/question-bank', 'permission' => 'manageQuestionBank'],
                        ['label' => 'Questions', 'href' => '/questions', 'permission' => 'manageQuestionBank'],
                    ]],
                    ['label' => 'Exams', 'children' => [
                        ['label' => 'Exams', 'href' => '/exams', 'permission' => 'manageExams'],
                        ['label' => 'Recruitment Exams', 'href' => '/exams?category=recruitment', 'permission' => 'manageExams'],
                        ['label' => 'Assessment Exams', 'href' => '/exams?category=assessment', 'permission' => 'manageExams'],
                        ['label' => 'Certification Exams', 'href' => '/exams?category=certification', 'permission' => 'manageExams'],
                        ['label' => 'Adaptive Exams', 'href' => '/exams?mode=adaptive', 'permission' => 'manageExams'],
                    ]],
                    ['label' => 'Reports', 'children' => [
                        ['label' => 'Results', 'href' => '/results'],
                        ['label' => 'Reports', 'href' => '/reports', 'permission' => 'viewReports'],
                    ]],
                    ['label' => 'Admin', 'children' => [
                        ['label' => 'Users', 'href' => '/users', 'permission' => 'manageUsers'],
                    ]],
                    ['label' => 'Settings', 'href' => '/settings', 'permission' => 'manageSettings'],
                ],
            });
        }

        if ($contextType === 'organization' && $user->organization_id) {
            $organizationHref = '/organizations/'.$user->organization_id;
            $institutionItems = [];

            if ($user->organization?->secondarySchools()->exists()) {
                $institutionItems[] = ['label' => 'Secondary Schools', 'href' => $organizationHref];
            }

            if ($user->organization?->professionalSchools()->exists()) {
                $institutionItems[] = ['label' => 'Professional Schools', 'href' => $organizationHref];
            }

            if ($user->organization?->cbtCenters()->exists()) {
                $institutionItems[] = ['label' => 'CBT Centers', 'href' => $organizationHref];
            }

            if ($institutionItems !== []) {
                $navigation->push(['label' => 'Institutions', 'children' => $institutionItems]);
            }
        }

        return $this->filterNavigation($navigation->all(), $user);
    }

    /**
     * @param array<int, array<string, mixed>> $navigation
     * @return array<int, array<string, mixed>>
     */
    private function filterNavigation(array $navigation, User $user): array
    {
        return collect($navigation)
            ->map(function (array $item) use ($user): ?array {
                if (isset($item['children'])) {
                    $item['children'] = $this->filterNavigation($item['children'], $user);

                    return count($item['children']) > 0 ? $item : null;
                }

                return ! isset($item['permission']) || $user->hasPermission($item['permission']) ? $item : null;
            })
            ->filter()
            ->values()
            ->all();
    }
}
