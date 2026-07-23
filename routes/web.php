<?php

use App\Http\Controllers\AccessControlController;
use App\Http\Controllers\AdminRegistrationController;
use App\Http\Controllers\AppReleaseController;
use App\Http\Controllers\CandidateController;
use App\Http\Controllers\CandidateClientDownloadController;
use App\Http\Controllers\CandidateGroupController;
use App\Http\Controllers\CenterController;
use App\Http\Controllers\CbtCenterController;
use App\Http\Controllers\CurrentContextController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\ExamMonitorController;
use App\Http\Controllers\ExamPaperController;
use App\Http\Controllers\OfflineActivationCodeController;
use App\Http\Controllers\OfflineServerDownloadController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\PricingPlanController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicPricingController;
use App\Http\Controllers\PublicWelcomeController;
use App\Http\Controllers\ProfessionalExamController;
use App\Http\Controllers\ProfessionalSchoolController;
use App\Http\Controllers\QuestionBankController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\RecruitmentController;
use App\Http\Controllers\ResultController;
use App\Http\Controllers\SchoolController;
use App\Http\Controllers\SecondarySchoolController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\TopicController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', PublicWelcomeController::class)->name('home');
Route::get('/documentation', fn () => Inertia::render('Public/Documentation'))->name('documentation.public');
Route::get('/pricing', PublicPricingController::class)->name('pricing.public');

Route::get('/ui-preview', fn () => Inertia::render('UiPreview/Index'))->name('ui-preview');

Route::get('/exam/{any?}', function () {
    return Inertia::render('CandidateExam/App');
})->where('any', '.*')->name('candidate.exam');
Route::get('/candidate-result', [ResultController::class, 'selfResult'])->name('candidate-result.self');
Route::get('/verify-result', [ResultController::class, 'verification'])->name('results.verification');
Route::get('/verify-certificate', [ProfessionalExamController::class, 'verifyPage'])->name('certificates.verify-page');

Route::middleware('guest')->group(function (): void {
    Route::get('/register-admin', [AdminRegistrationController::class, 'create'])->name('admin-registrations.create');
    Route::post('/register-admin', [AdminRegistrationController::class, 'store'])->name('admin-registrations.store');
    Route::get('/register-admin/submitted', [AdminRegistrationController::class, 'thankYou'])->name('admin-registrations.thank-you');
});

Route::middleware(['auth', 'portal.user'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/offline-server/download', OfflineServerDownloadController::class)
        ->middleware('plan.feature:offline_activation')
        ->name('offline-server.download');
    Route::get('/candidate-client/download', CandidateClientDownloadController::class)
        ->name('candidate-client.download');
    Route::get('/offline-activation-codes', [OfflineActivationCodeController::class, 'index'])
        ->middleware(['permission:downloadOfflineServer', 'plan.feature:offline_activation'])
        ->name('offline-activation-codes.index');
    Route::post('/offline-activation-codes', [OfflineActivationCodeController::class, 'store'])
        ->middleware(['permission:downloadOfflineServer', 'plan.feature:offline_activation'])
        ->name('offline-activation-codes.store');
    Route::get('/admin/manage-activation', [OfflineActivationCodeController::class, 'resetIndex'])
        ->middleware('role:super_admin')
        ->name('offline-activation-codes.reset-index');
    Route::post('/admin/manage-activation/{offlineActivationCode}/reset', [OfflineActivationCodeController::class, 'reset'])
        ->middleware('role:super_admin')
        ->name('offline-activation-codes.reset');
    Route::get('/platform/offline-activation-resets', fn () => redirect()->route('offline-activation-codes.reset-index'))
        ->middleware('role:super_admin');
    Route::patch('/current-context', [CurrentContextController::class, 'update'])->name('current-context.update');
    Route::delete('/current-context', [CurrentContextController::class, 'destroy'])->name('current-context.destroy');
    Route::get('/access-denied', fn () => Inertia::render('AccessDenied'))->name('access-denied');

    Route::get('/organizations', [OrganizationController::class, 'index'])
        ->middleware(['role:super_admin', 'permission:manageOrganizations'])
        ->name('organizations.index');

    Route::get('/organizations/create', [OrganizationController::class, 'create'])
        ->middleware(['role:super_admin', 'permission:manageOrganizations'])
        ->name('organizations.create');

    Route::post('/organizations', [OrganizationController::class, 'store'])
        ->middleware(['role:super_admin', 'permission:manageOrganizations'])
        ->name('organizations.store');

    Route::get('/organizations/{organization}/edit', [OrganizationController::class, 'edit'])
        ->middleware(['role:super_admin', 'permission:manageOrganizations'])
        ->name('organizations.edit');

    Route::patch('/organizations/{organization}', [OrganizationController::class, 'update'])
        ->middleware(['role:super_admin', 'permission:manageOrganizations'])
        ->name('organizations.update');

    Route::patch('/organizations/{organization}/deactivate', [OrganizationController::class, 'deactivate'])
        ->middleware(['role:super_admin', 'permission:manageOrganizations'])
        ->name('organizations.deactivate');

    Route::middleware(['role:super_admin', 'permission:manageAccessControls'])->group(function (): void {
        Route::get('/access-controls', [AccessControlController::class, 'index'])->name('access-controls.index');
        Route::patch('/access-controls', [AccessControlController::class, 'update'])->name('access-controls.update');
    });

    Route::middleware(['role:super_admin', 'permission:manageAdminRegistrations'])->group(function (): void {
        Route::get('/admin-registrations', [AdminRegistrationController::class, 'index'])->name('admin-registrations.index');
        Route::get('/admin-registrations/{adminRegistration}', [AdminRegistrationController::class, 'show'])->name('admin-registrations.show');
        Route::get('/admin-registrations/{adminRegistration}/edit', [AdminRegistrationController::class, 'edit'])->name('admin-registrations.edit');
        Route::patch('/admin-registrations/{adminRegistration}', [AdminRegistrationController::class, 'update'])->name('admin-registrations.update');
        Route::patch('/admin-registrations/{adminRegistration}/approve', [AdminRegistrationController::class, 'approve'])->name('admin-registrations.approve');
        Route::patch('/admin-registrations/{adminRegistration}/reject', [AdminRegistrationController::class, 'reject'])->name('admin-registrations.reject');
        Route::patch('/admin-registrations/{adminRegistration}/deactivate', [AdminRegistrationController::class, 'deactivate'])->name('admin-registrations.deactivate');
    });

    Route::middleware(['role:super_admin', 'permission:managePricingPlans'])->group(function (): void {
        Route::get('/pricing-plans', [PricingPlanController::class, 'index'])->name('pricing-plans.index');
        Route::post('/pricing-plans', [PricingPlanController::class, 'store'])->name('pricing-plans.store');
        Route::patch('/pricing-plans/{pricingPlan}', [PricingPlanController::class, 'update'])->name('pricing-plans.update');
        Route::delete('/pricing-plans/{pricingPlan}', [PricingPlanController::class, 'destroy'])->name('pricing-plans.destroy');
    });

    Route::middleware(['role:super_admin', 'permission:manageAppReleases'])->group(function (): void {
        Route::get('/app-releases', [AppReleaseController::class, 'index'])->name('app-releases.index');
        Route::post('/app-releases', [AppReleaseController::class, 'store'])->name('app-releases.store');
        Route::patch('/app-releases/{appRelease}', [AppReleaseController::class, 'update'])->name('app-releases.update');
        Route::delete('/app-releases/{appRelease}', [AppReleaseController::class, 'destroy'])->name('app-releases.destroy');
        Route::get('/app-releases/{appRelease}/download', [AppReleaseController::class, 'download'])->name('app-releases.download');
    });

    Route::get('/organizations/{organization}', [OrganizationController::class, 'show'])
        ->middleware('role:super_admin,organization_admin')
        ->name('organizations.show');

    Route::middleware(['role:super_admin,organization_admin,secondary_school_admin,school_admin,examiner', 'permission:manageSchools'])->group(function (): void {
        Route::get('/secondary-schools', [SecondarySchoolController::class, 'list'])->name('secondary-schools.index');
        Route::get('/secondary-schools/create', [SecondarySchoolController::class, 'create'])->name('secondary-schools.create');
        Route::post('/secondary-schools', [SecondarySchoolController::class, 'store'])->name('secondary-schools.store');
        Route::get('/secondary-schools/{secondarySchool}', [SecondarySchoolController::class, 'show'])->name('secondary-schools.show');
        Route::get('/secondary-schools/{secondarySchool}/edit', [SecondarySchoolController::class, 'edit'])->name('secondary-schools.edit');
        Route::patch('/secondary-schools/{secondarySchool}', [SecondarySchoolController::class, 'update'])->name('secondary-schools.update');
        Route::get('/secondary-schools/{secondarySchool}/academic-sessions', [SecondarySchoolController::class, 'academicSessions'])->name('secondary-schools.academic-sessions.index');
        Route::post('/secondary-schools/{secondarySchool}/academic-sessions', [SecondarySchoolController::class, 'storeAcademicSession'])->name('secondary-schools.academic-sessions.store');
        Route::patch('/secondary-schools/{secondarySchool}/academic-sessions/{academicSession}', [SecondarySchoolController::class, 'updateAcademicSession'])->name('secondary-schools.academic-sessions.update');
        Route::delete('/secondary-schools/{secondarySchool}/academic-sessions/{academicSession}', [SecondarySchoolController::class, 'destroyAcademicSession'])->name('secondary-schools.academic-sessions.destroy');
        Route::patch('/secondary-schools/{secondarySchool}/academic-sessions/{academicSession}/active', [SecondarySchoolController::class, 'setActiveAcademicSession'])->name('secondary-schools.academic-sessions.active');
        Route::get('/secondary-schools/{secondarySchool}/terms', [SecondarySchoolController::class, 'terms'])->name('secondary-schools.terms.index');
        Route::post('/secondary-schools/{secondarySchool}/terms', [SecondarySchoolController::class, 'storeTermForSchool'])->name('secondary-schools.terms.store');
        Route::patch('/secondary-schools/{secondarySchool}/terms/{academicTerm}', [SecondarySchoolController::class, 'updateTermForSchool'])->name('secondary-schools.terms.update');
        Route::delete('/secondary-schools/{secondarySchool}/terms/{academicTerm}', [SecondarySchoolController::class, 'destroyTermForSchool'])->name('secondary-schools.terms.destroy');
        Route::get('/secondary-schools/{secondarySchool}/classes', [SecondarySchoolController::class, 'classes'])->name('secondary-schools.classes.index');
        Route::post('/secondary-schools/{secondarySchool}/classes', [SecondarySchoolController::class, 'storeClassForSchool'])->name('secondary-schools.classes.store');
        Route::patch('/secondary-schools/{secondarySchool}/classes/{schoolClass}', [SecondarySchoolController::class, 'updateClassForSchool'])->name('secondary-schools.classes.update');
        Route::delete('/secondary-schools/{secondarySchool}/classes/{schoolClass}', [SecondarySchoolController::class, 'destroyClassForSchool'])->name('secondary-schools.classes.destroy');
        Route::get('/secondary-schools/{secondarySchool}/student-groups', [SecondarySchoolController::class, 'studentGroups'])->name('secondary-schools.student-groups.index');
        Route::post('/secondary-schools/{secondarySchool}/student-groups', [SecondarySchoolController::class, 'storeStudentGroupForSchool'])->name('secondary-schools.student-groups.store');
        Route::patch('/secondary-schools/{secondarySchool}/student-groups/{studentGroup}', [SecondarySchoolController::class, 'updateStudentGroupForSchool'])->name('secondary-schools.student-groups.update');
        Route::delete('/secondary-schools/{secondarySchool}/student-groups/{studentGroup}', [SecondarySchoolController::class, 'destroyStudentGroupForSchool'])->name('secondary-schools.student-groups.destroy');
        Route::get('/secondary-schools/{secondarySchool}/students', [SecondarySchoolController::class, 'students'])->name('secondary-schools.students.index');
        Route::post('/secondary-schools/{secondarySchool}/students', [SecondarySchoolController::class, 'storeStudent'])->name('secondary-schools.students.store');
        Route::patch('/secondary-schools/{secondarySchool}/students/{student}', [SecondarySchoolController::class, 'updateStudent'])->name('secondary-schools.students.update');
        Route::delete('/secondary-schools/{secondarySchool}/students/{student}', [SecondarySchoolController::class, 'destroyStudent'])->name('secondary-schools.students.destroy');
        Route::get('/secondary-schools/{secondarySchool}/teachers', [SecondarySchoolController::class, 'teachers'])->middleware('plan.feature:teacher_management')->name('secondary-schools.teachers.index');
        Route::post('/secondary-schools/{secondarySchool}/teachers', [SecondarySchoolController::class, 'storeTeacher'])->middleware('plan.feature:teacher_management')->name('secondary-schools.teachers.store');
        Route::patch('/secondary-schools/{secondarySchool}/teachers/{teacher}', [SecondarySchoolController::class, 'updateTeacher'])->middleware('plan.feature:teacher_management')->name('secondary-schools.teachers.update');
        Route::delete('/secondary-schools/{secondarySchool}/teachers/{teacher}', [SecondarySchoolController::class, 'destroyTeacher'])->middleware('plan.feature:teacher_management')->name('secondary-schools.teachers.destroy');
        Route::post('/secondary-schools/{secondarySchool}/subjects', [SecondarySchoolController::class, 'storeSubjectForSchool'])->name('secondary-schools.subjects.store');
        Route::get('/secondary-schools/{secondarySchool}/{section}/template', [SecondarySchoolController::class, 'structureTemplate'])->name('secondary-schools.structure.template');
        Route::post('/secondary-schools/{secondarySchool}/{section}/import', [SecondarySchoolController::class, 'importStructure'])->name('secondary-schools.structure.import');
    });

    Route::middleware('role:super_admin,organization_admin,professional_school_admin,examiner,facilitator')->group(function (): void {
        Route::get('/professional-schools', [ProfessionalSchoolController::class, 'index'])->name('professional-schools.index');
        Route::get('/professional-schools/create', [ProfessionalSchoolController::class, 'create'])->name('professional-schools.create');
        Route::post('/professional-schools', [ProfessionalSchoolController::class, 'store'])->name('professional-schools.store');
        Route::get('/professional-schools/{professionalSchool}', [ProfessionalSchoolController::class, 'show'])->name('professional-schools.show');
        Route::get('/professional-schools/{professionalSchool}/edit', [ProfessionalSchoolController::class, 'edit'])->name('professional-schools.edit');
        Route::patch('/professional-schools/{professionalSchool}', [ProfessionalSchoolController::class, 'update'])->name('professional-schools.update');
        Route::get('/professional-schools/{professionalSchool}/programmes', [ProfessionalSchoolController::class, 'programmes'])->name('professional-schools.programmes.index');
        Route::post('/professional-schools/{professionalSchool}/programmes', [ProfessionalSchoolController::class, 'storeProgramme'])->name('professional-schools.programmes.store');
        Route::get('/professional-schools/{professionalSchool}/courses', [ProfessionalSchoolController::class, 'courses'])->name('professional-schools.courses.index');
        Route::post('/professional-schools/{professionalSchool}/courses', [ProfessionalSchoolController::class, 'storeCourse'])->name('professional-schools.courses.store');
        Route::get('/professional-schools/{professionalSchool}/modules', [ProfessionalSchoolController::class, 'modules'])->name('professional-schools.modules.index');
        Route::post('/professional-schools/{professionalSchool}/modules', [ProfessionalSchoolController::class, 'storeModule'])->name('professional-schools.modules.store');
        Route::get('/professional-schools/{professionalSchool}/training-batches', [ProfessionalSchoolController::class, 'batches'])->name('professional-schools.training-batches.index');
        Route::post('/professional-schools/{professionalSchool}/training-batches', [ProfessionalSchoolController::class, 'storeBatch'])->name('professional-schools.training-batches.store');
        Route::patch('/professional-schools/{professionalSchool}/training-batches/{trainingBatch}', [ProfessionalSchoolController::class, 'updateBatch'])->name('professional-schools.training-batches.update');
        Route::delete('/professional-schools/{professionalSchool}/training-batches/{trainingBatch}', [ProfessionalSchoolController::class, 'destroyBatch'])->name('professional-schools.training-batches.destroy');
        Route::get('/professional-schools/{professionalSchool}/facilitators', [ProfessionalSchoolController::class, 'facilitators'])->middleware('plan.feature:facilitator_management')->name('professional-schools.facilitators.index');
        Route::post('/professional-schools/{professionalSchool}/facilitators', [ProfessionalSchoolController::class, 'storeFacilitator'])->middleware('plan.feature:facilitator_management')->name('professional-schools.facilitators.store');
        Route::patch('/professional-schools/{professionalSchool}/facilitators/{facilitator}', [ProfessionalSchoolController::class, 'updateFacilitator'])->middleware('plan.feature:facilitator_management')->name('professional-schools.facilitators.update');
        Route::delete('/professional-schools/{professionalSchool}/facilitators/{facilitator}', [ProfessionalSchoolController::class, 'destroyFacilitator'])->middleware('plan.feature:facilitator_management')->name('professional-schools.facilitators.destroy');
        Route::get('/professional-schools/{professionalSchool}/question-banks', [ProfessionalSchoolController::class, 'questionBanks'])->name('professional-schools.question-banks.index');
        Route::post('/professional-schools/{professionalSchool}/question-banks', [ProfessionalSchoolController::class, 'storeQuestionBank'])->name('professional-schools.question-banks.store');
        Route::get('/professional-schools/{professionalSchool}/questions', [ProfessionalSchoolController::class, 'questions'])->name('professional-schools.questions.index');
        Route::get('/professional-schools/{professionalSchool}/questions/template', [ProfessionalSchoolController::class, 'questionTemplate'])->name('professional-schools.questions.template');
        Route::post('/professional-schools/{professionalSchool}/questions/import', [ProfessionalSchoolController::class, 'importQuestions'])->name('professional-schools.questions.import');
        Route::get('/professional-schools/{professionalSchool}/certificates', [ProfessionalSchoolController::class, 'certificates'])->middleware('plan.feature:certificate_generation')->name('professional-schools.certificates.index');
        Route::get('/professional-schools/{professionalSchool}/candidates', [ProfessionalSchoolController::class, 'candidates'])->name('professional-schools.candidates.index');
        Route::get('/professional-schools/{professionalSchool}/candidates/template', [ProfessionalSchoolController::class, 'candidateTemplate'])->name('professional-schools.candidates.template');
        Route::post('/professional-schools/{professionalSchool}/candidates', [ProfessionalSchoolController::class, 'storeCandidate'])->name('professional-schools.candidates.store');
        Route::post('/professional-schools/{professionalSchool}/candidates/import', [ProfessionalSchoolController::class, 'importCandidates'])->name('professional-schools.candidates.import');
    });

    Route::middleware(['role:super_admin,organization_admin,examiner', 'permission:manageExams'])->group(function (): void {
        Route::get('/organizations/{organization}/candidates', [CandidateController::class, 'index'])->name('organizations.candidates.index');
        Route::get('/organizations/{organization}/candidates/create', [CandidateController::class, 'create'])->name('organizations.candidates.create');
        Route::get('/organizations/{organization}/candidates/{candidate}', [CandidateController::class, 'show'])->name('organizations.candidates.show');
        Route::get('/organizations/{organization}/candidates/{candidate}/edit', [CandidateController::class, 'edit'])->name('organizations.candidates.edit');
        Route::get('/organizations/{organization}/exams', [ExamController::class, 'index'])->name('organizations.exams.index');
        Route::get('/organizations/{organization}/exams/create', [ExamController::class, 'create'])->name('organizations.exams.create');
        Route::get('/organizations/{organization}/exams/{exam}', [ExamController::class, 'show'])->name('organizations.exams.show');
        Route::get('/organizations/{organization}/exams/{exam}/edit', [ExamController::class, 'edit'])->name('organizations.exams.edit');
    });

    Route::middleware(['role:super_admin,organization_admin,examiner', 'permission:manageQuestionBank'])->group(function (): void {
        Route::get('/organizations/{organization}/question-bank', [QuestionBankController::class, 'index'])->name('organizations.question-bank.index');
        Route::get('/organizations/{organization}/question-bank/create', [QuestionBankController::class, 'create'])->name('organizations.question-bank.create');
        Route::get('/organizations/{organization}/question-bank/{questionBank}/edit', [QuestionBankController::class, 'edit'])->name('organizations.question-bank.edit');
    });

    Route::middleware(['role:super_admin,organization_admin,examiner', 'permission:viewReports'])->group(function (): void {
        Route::get('/organizations/{organization}/results', [ResultController::class, 'index'])->name('organizations.results.index');
        Route::get('/organizations/{organization}/results/exams/{exam}', [ResultController::class, 'show'])->name('organizations.results.exams.show');
    });

    Route::middleware(['role:super_admin,center_admin', 'permission:manageCenters'])->group(function (): void {
        Route::get('/centers', [CenterController::class, 'index'])->name('centers.index');
        Route::get('/centers/create', [CenterController::class, 'create'])->name('centers.create');
        Route::post('/centers', [CenterController::class, 'store'])->name('centers.store');
        Route::get('/centers/{center}', [CenterController::class, 'show'])->name('centers.show');
        Route::get('/centers/{center}/edit', [CenterController::class, 'edit'])->name('centers.edit');
        Route::patch('/centers/{center}', [CenterController::class, 'update'])->name('centers.update');
        Route::patch('/centers/{center}/deactivate', [CenterController::class, 'deactivate'])->name('centers.deactivate');
    });

    Route::middleware(['role:super_admin,organization_admin,cbt_center_admin,examiner', 'permission:manageCenters'])->group(function (): void {
        Route::get('/cbt-centers', [CbtCenterController::class, 'index'])->name('cbt-centers.index');
        Route::get('/cbt-centers/create', [CbtCenterController::class, 'create'])->name('cbt-centers.create');
        Route::post('/cbt-centers', [CbtCenterController::class, 'store'])->name('cbt-centers.store');
        Route::get('/cbt-centers/{cbtCenter}', [CbtCenterController::class, 'show'])->name('cbt-centers.show');
        Route::get('/cbt-centers/{cbtCenter}/edit', [CbtCenterController::class, 'edit'])->name('cbt-centers.edit');
        Route::patch('/cbt-centers/{cbtCenter}', [CbtCenterController::class, 'update'])->name('cbt-centers.update');
        Route::get('/cbt-centers/{cbtCenter}/candidates', [CbtCenterController::class, 'candidates'])->name('cbt-centers.candidates.index');
        Route::post('/cbt-centers/{cbtCenter}/candidates', [CbtCenterController::class, 'storeCandidate'])->name('cbt-centers.candidates.store');
        Route::get('/cbt-centers/{cbtCenter}/candidates/template', [CbtCenterController::class, 'candidatesTemplate'])->name('cbt-centers.candidates.template');
        Route::post('/cbt-centers/{cbtCenter}/candidates/import', [CbtCenterController::class, 'importCandidates'])->name('cbt-centers.candidates.import');
        Route::get('/cbt-centers/{cbtCenter}/question-banks', [CbtCenterController::class, 'questionBanks'])->name('cbt-centers.question-banks.index');
        Route::post('/cbt-centers/{cbtCenter}/question-banks', [CbtCenterController::class, 'storeQuestionBank'])->name('cbt-centers.question-banks.store');
        Route::post('/cbt-centers/{cbtCenter}/external-exams', [CbtCenterController::class, 'assignExternalExam'])->name('cbt-centers.external-exams.store');
    });

    Route::middleware(['role:super_admin,school_admin', 'permission:manageSchools'])->group(function (): void {
        Route::get('/schools', [SchoolController::class, 'index'])->name('schools.index');
        Route::get('/schools/create', [SchoolController::class, 'create'])->name('schools.create');
        Route::post('/schools', [SchoolController::class, 'store'])->name('schools.store');
        Route::get('/schools/{school}', [SchoolController::class, 'show'])->name('schools.show');
        Route::get('/schools/{school}/edit', [SchoolController::class, 'edit'])->name('schools.edit');
        Route::patch('/schools/{school}', [SchoolController::class, 'update'])->name('schools.update');
        Route::patch('/schools/{school}/deactivate', [SchoolController::class, 'deactivate'])->name('schools.deactivate');
    });

    Route::middleware(['role:super_admin,organization_admin', 'permission:manageUsers'])->group(function (): void {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });

    Route::middleware(['role:super_admin,organization_admin', 'permission:manageSettings'])->group(function (): void {
        Route::get('/settings', fn () => Inertia::render('Portal/Placeholder', [
            'title' => 'Settings',
        ]))->name('settings.index');
    });

    Route::middleware('role:super_admin,organization_admin,examiner,teacher,facilitator,school_admin,secondary_school_admin,professional_school_admin,center_admin,cbt_center_admin,supervisor')->group(function (): void {
        Route::middleware('permission:manageQuestionBank')->group(function (): void {
            Route::get('/subjects/template', [SubjectController::class, 'template'])->name('subjects.template');
            Route::post('/subjects/import', [SubjectController::class, 'import'])->name('subjects.import');
            Route::get('/subjects', [SubjectController::class, 'index'])->name('subjects.index');
            Route::get('/subjects/create', [SubjectController::class, 'create'])->name('subjects.create');
            Route::post('/subjects', [SubjectController::class, 'store'])->name('subjects.store');
            Route::get('/subjects/{subject}/edit', [SubjectController::class, 'edit'])->name('subjects.edit');
            Route::patch('/subjects/{subject}', [SubjectController::class, 'update'])->name('subjects.update');
            Route::delete('/subjects/{subject}', [SubjectController::class, 'destroy'])->name('subjects.destroy');

            Route::get('/topics/template', [TopicController::class, 'template'])->name('topics.template');
            Route::post('/topics/import', [TopicController::class, 'import'])->name('topics.import');
            Route::get('/topics', [TopicController::class, 'index'])->name('topics.index');
            Route::get('/topics/create', [TopicController::class, 'create'])->name('topics.create');
            Route::post('/topics', [TopicController::class, 'store'])->name('topics.store');
            Route::get('/topics/{topic}/edit', [TopicController::class, 'edit'])->name('topics.edit');
            Route::patch('/topics/{topic}', [TopicController::class, 'update'])->name('topics.update');
            Route::delete('/topics/{topic}', [TopicController::class, 'destroy'])->name('topics.destroy');

            Route::get('/question-bank/template', [QuestionBankController::class, 'template'])->name('question-bank.template');
            Route::post('/question-bank/import', [QuestionBankController::class, 'import'])->name('question-bank.import');
            Route::get('/question-bank', [QuestionBankController::class, 'index'])->name('question-bank.index');
            Route::get('/question-bank/create', [QuestionBankController::class, 'create'])->name('question-bank.create');
            Route::post('/question-bank', [QuestionBankController::class, 'store'])->name('question-bank.store');
            Route::get('/question-bank/{questionBank}', [QuestionBankController::class, 'show'])->name('question-bank.show');
            Route::get('/question-bank/{questionBank}/edit', [QuestionBankController::class, 'edit'])->name('question-bank.edit');
            Route::patch('/question-bank/{questionBank}', [QuestionBankController::class, 'update'])->name('question-bank.update');
            Route::delete('/question-bank/{questionBank}', [QuestionBankController::class, 'destroy'])->name('question-bank.destroy');

            Route::get('/questions/template', [QuestionController::class, 'template'])->name('questions.template');
            Route::post('/questions/import', [QuestionController::class, 'import'])->name('questions.import');
            Route::get('/questions', [QuestionController::class, 'index'])->name('questions.index');
            Route::get('/questions/create', [QuestionController::class, 'create'])->name('questions.create');
            Route::post('/questions', [QuestionController::class, 'store'])->name('questions.store');
            Route::get('/questions/{question}', [QuestionController::class, 'show'])->name('questions.show');
            Route::get('/questions/{question}/edit', [QuestionController::class, 'edit'])->name('questions.edit');
            Route::patch('/questions/{question}', [QuestionController::class, 'update'])->name('questions.update');
            Route::delete('/questions/{question}', [QuestionController::class, 'destroy'])->name('questions.destroy');
        });

        Route::middleware('permission:manageExams')->group(function (): void {
            Route::get('/exams', [ExamController::class, 'index'])->name('exams.index');
            Route::get('/exams/create', [ExamController::class, 'create'])->name('exams.create');
            Route::post('/exams', [ExamController::class, 'store'])->name('exams.store');
            Route::get('/exams/{exam}', [ExamController::class, 'show'])->name('exams.show');
            Route::get('/exams/{exam}/edit', [ExamController::class, 'edit'])->name('exams.edit');
            Route::patch('/exams/{exam}', [ExamController::class, 'update'])->name('exams.update');
            Route::patch('/exams/{exam}/cancel', [ExamController::class, 'cancel'])->name('exams.cancel');
            Route::delete('/exams/{exam}', [ExamController::class, 'destroy'])->name('exams.destroy');
            Route::post('/exams/{exam}/participants/refresh', [ExamController::class, 'refreshParticipants'])->name('exams.participants.refresh');
            Route::get('/exams/{exam}/papers', [ExamPaperController::class, 'show'])->name('exams.papers.show');
            Route::post('/exams/{exam}/papers/generate', [ExamPaperController::class, 'generate'])->name('exams.papers.generate');
            Route::get('/exams/{exam}/recruitment', [RecruitmentController::class, 'show'])->name('exams.recruitment.show');
            Route::patch('/exams/{exam}/recruitment/settings', [RecruitmentController::class, 'updateSettings'])->name('exams.recruitment.settings');
            Route::post('/exams/{exam}/recruitment/access-codes', [RecruitmentController::class, 'generateAccessCodes'])->name('exams.recruitment.access-codes');
            Route::post('/exams/{exam}/recruitment/shortlist', [RecruitmentController::class, 'applyShortlist'])->name('exams.recruitment.shortlist');
            Route::get('/exams/{exam}/recruitment/shortlist.csv', [RecruitmentController::class, 'exportShortlist'])->name('exams.recruitment.shortlist-export');
            Route::get('/exams/{exam}/recruitment/access-codes.csv', [RecruitmentController::class, 'exportAccessCodes'])->name('exams.recruitment.access-codes-export');
            Route::get('/exams/{exam}/professional', [ProfessionalExamController::class, 'show'])->name('exams.professional.show');
            Route::patch('/exams/{exam}/professional/settings', [ProfessionalExamController::class, 'updateSettings'])->name('exams.professional.settings');
            Route::post('/exams/{exam}/professional/templates', [ProfessionalExamController::class, 'storeTemplate'])->name('exams.professional.templates.store');
            Route::post('/exams/{exam}/professional/templates/{template}', [ProfessionalExamController::class, 'updateTemplate'])->name('exams.professional.templates.update-post');
            Route::patch('/exams/{exam}/professional/templates/{template}', [ProfessionalExamController::class, 'updateTemplate'])->name('exams.professional.templates.update');
            Route::delete('/exams/{exam}/professional/templates/{template}', [ProfessionalExamController::class, 'destroyTemplate'])->name('exams.professional.templates.destroy');
            Route::patch('/exams/{exam}/professional/attempts/{attempt}/payment', [ProfessionalExamController::class, 'updatePayment'])->name('exams.professional.payment');
            Route::post('/exams/{exam}/professional/certificates', [ProfessionalExamController::class, 'generateCertificates'])->middleware('plan.feature:certificate_generation')->name('exams.professional.certificates.generate');
            Route::get('/exams/{exam}/professional/certificates/{certificate}/download', [ProfessionalExamController::class, 'downloadCertificate'])->middleware('plan.feature:certificate_generation')->name('exams.professional.certificates.download');
            Route::post('/exams/{exam}/professional/attempts/{attempt}/certificate', [ProfessionalExamController::class, 'generateCertificate'])->middleware('plan.feature:certificate_generation')->name('exams.professional.certificate.generate');
            Route::get('/exams/{exam}/certification', [ProfessionalExamController::class, 'show'])->name('exams.certification.show');
            Route::patch('/exams/{exam}/certification/settings', [ProfessionalExamController::class, 'updateSettings'])->name('exams.certification.settings');
            Route::post('/exams/{exam}/certification/templates', [ProfessionalExamController::class, 'storeTemplate'])->name('exams.certification.templates.store');
            Route::post('/exams/{exam}/certification/templates/{template}', [ProfessionalExamController::class, 'updateTemplate'])->name('exams.certification.templates.update-post');
            Route::patch('/exams/{exam}/certification/templates/{template}', [ProfessionalExamController::class, 'updateTemplate'])->name('exams.certification.templates.update');
            Route::delete('/exams/{exam}/certification/templates/{template}', [ProfessionalExamController::class, 'destroyTemplate'])->name('exams.certification.templates.destroy');
            Route::patch('/exams/{exam}/certification/attempts/{attempt}/payment', [ProfessionalExamController::class, 'updatePayment'])->name('exams.certification.payment');
            Route::post('/exams/{exam}/certification/certificates', [ProfessionalExamController::class, 'generateCertificates'])->middleware('plan.feature:certificate_generation')->name('exams.certification.certificates.generate');
            Route::get('/exams/{exam}/certification/certificates/{certificate}/download', [ProfessionalExamController::class, 'downloadCertificate'])->middleware('plan.feature:certificate_generation')->name('exams.certification.certificates.download');
            Route::post('/exams/{exam}/certification/attempts/{attempt}/certificate', [ProfessionalExamController::class, 'generateCertificate'])->middleware('plan.feature:certificate_generation')->name('exams.certification.certificate.generate');
        });

        Route::middleware('permission:manageExams')->group(function (): void {
            Route::get('/secondary-school', [SecondarySchoolController::class, 'index'])->name('secondary-school.index');
            Route::get('/secondary-school/academic-sessions', [SecondarySchoolController::class, 'legacyAcademicSessions'])->name('secondary-school.academic-sessions.index');
            Route::post('/secondary-school/academic-sessions', [SecondarySchoolController::class, 'storeLegacyAcademicSession'])->name('secondary-school.academic-sessions.store');
            Route::patch('/secondary-school/academic-sessions/{academicSession}', [SecondarySchoolController::class, 'updateLegacyAcademicSession'])->name('secondary-school.academic-sessions.update');
            Route::delete('/secondary-school/academic-sessions/{academicSession}', [SecondarySchoolController::class, 'destroyLegacyAcademicSession'])->name('secondary-school.academic-sessions.destroy');
            Route::patch('/secondary-school/academic-sessions/{academicSession}/active', [SecondarySchoolController::class, 'setActiveLegacyAcademicSession'])->name('secondary-school.academic-sessions.active');
            Route::get('/secondary-school/terms', [SecondarySchoolController::class, 'legacyTerms'])->name('secondary-school.terms.index');
            Route::post('/secondary-school/terms', [SecondarySchoolController::class, 'storeLegacyTerm'])->name('secondary-school.terms.store');
            Route::patch('/secondary-school/terms/{academicTerm}', [SecondarySchoolController::class, 'updateLegacyTerm'])->name('secondary-school.terms.update');
            Route::delete('/secondary-school/terms/{academicTerm}', [SecondarySchoolController::class, 'destroyLegacyTerm'])->name('secondary-school.terms.destroy');
            Route::get('/secondary-school/classes', [SecondarySchoolController::class, 'legacyClasses'])->name('secondary-school.classes.index');
            Route::post('/secondary-school/classes', [SecondarySchoolController::class, 'storeLegacyClass'])->name('secondary-school.classes.store');
            Route::patch('/secondary-school/classes/{schoolClass}', [SecondarySchoolController::class, 'updateLegacyClass'])->name('secondary-school.classes.update');
            Route::delete('/secondary-school/classes/{schoolClass}', [SecondarySchoolController::class, 'destroyLegacyClass'])->name('secondary-school.classes.destroy');
            Route::get('/secondary-school/arms', [SecondarySchoolController::class, 'legacyArms'])->name('secondary-school.arms.index');
            Route::post('/secondary-school/arms', [SecondarySchoolController::class, 'storeLegacyArm'])->name('secondary-school.arms.store');
            Route::patch('/secondary-school/arms/{classArm}', [SecondarySchoolController::class, 'updateLegacyArm'])->name('secondary-school.arms.update');
            Route::delete('/secondary-school/arms/{classArm}', [SecondarySchoolController::class, 'destroyLegacyArm'])->name('secondary-school.arms.destroy');
            Route::get('/secondary-school/students', [SecondarySchoolController::class, 'legacyStudents'])->name('secondary-school.students.index');
            Route::post('/secondary-school/students', [SecondarySchoolController::class, 'storeLegacyStudent'])->name('secondary-school.students.store');
            Route::patch('/secondary-school/students/{student}', [SecondarySchoolController::class, 'updateLegacyStudent'])->name('secondary-school.students.update');
            Route::delete('/secondary-school/students/{student}', [SecondarySchoolController::class, 'destroyLegacyStudent'])->name('secondary-school.students.destroy');
            Route::get('/secondary-school/teachers', [SecondarySchoolController::class, 'legacyTeachers'])->middleware(['permission:manageSchools', 'plan.feature:teacher_management'])->name('secondary-school.teachers.index');
            Route::post('/secondary-school/teachers', [SecondarySchoolController::class, 'storeLegacyTeacher'])->middleware(['permission:manageSchools', 'plan.feature:teacher_management'])->name('secondary-school.teachers.store');
            Route::patch('/secondary-school/teachers/{teacher}', [SecondarySchoolController::class, 'updateLegacyTeacher'])->middleware(['permission:manageSchools', 'plan.feature:teacher_management'])->name('secondary-school.teachers.update');
            Route::delete('/secondary-school/teachers/{teacher}', [SecondarySchoolController::class, 'destroyLegacyTeacher'])->middleware(['permission:manageSchools', 'plan.feature:teacher_management'])->name('secondary-school.teachers.destroy');
            Route::get('/secondary-school/student-groups', [SecondarySchoolController::class, 'legacyStudentGroups'])->name('secondary-school.student-groups.index');
            Route::post('/secondary-school/student-groups', [SecondarySchoolController::class, 'storeLegacyStudentGroup'])->name('secondary-school.student-groups.store');
            Route::patch('/secondary-school/student-groups/{studentGroup}', [SecondarySchoolController::class, 'updateLegacyStudentGroup'])->name('secondary-school.student-groups.update');
            Route::delete('/secondary-school/student-groups/{studentGroup}', [SecondarySchoolController::class, 'destroyLegacyStudentGroup'])->name('secondary-school.student-groups.destroy');
            Route::get('/secondary-school/{section}/template', [SecondarySchoolController::class, 'legacyStructureTemplate'])->name('secondary-school.structure.template');
            Route::post('/secondary-school/{section}/import', [SecondarySchoolController::class, 'importLegacyStructure'])->name('secondary-school.structure.import');
            Route::post('/secondary-school/sessions', [SecondarySchoolController::class, 'storeSession'])->name('secondary-school.sessions.store');
            Route::post('/secondary-school/groups', [SecondarySchoolController::class, 'storeGroup'])->name('secondary-school.groups.store');
        });

        Route::get('/exams/{exam}/monitor', [ExamMonitorController::class, 'show'])->name('exams.monitor.show');
        Route::get('/exams/{exam}/monitor/summary', [ExamMonitorController::class, 'summary'])->name('exams.monitor.summary');
        Route::get('/exams/{exam}/monitor/rows', [ExamMonitorController::class, 'rows'])->name('exams.monitor.rows');
        Route::get('/exams/{exam}/monitor/feed', [ExamMonitorController::class, 'feed'])->name('exams.monitor.feed');
        Route::get('/exams/{exam}/monitor/events', [ExamMonitorController::class, 'events'])->name('exams.monitor.events');
        Route::post('/exams/{exam}/monitor/end', [ExamMonitorController::class, 'end'])->name('exams.monitor.end');
        Route::post('/exams/{exam}/monitor/attempts/{attempt}/reset', [ExamMonitorController::class, 'reset'])->name('exams.monitor.reset');

        Route::middleware('permission:manageExams')->group(function (): void {
            Route::get('/candidates/template', [CandidateController::class, 'template'])->name('candidates.template');
            Route::post('/candidates/import', [CandidateController::class, 'import'])->name('candidates.import');
            Route::get('/candidates/import-errors/{filename}', [CandidateController::class, 'errorReport'])->name('candidates.import-errors');
            Route::get('/candidates/assignments', [CandidateController::class, 'assignments'])->name('candidates.assignments');
            Route::post('/candidates/assign', [CandidateController::class, 'assign'])->name('candidates.assign');
            Route::delete('/exams/{exam}/candidates/{candidate}', [CandidateController::class, 'unassign'])->name('candidates.unassign');
            Route::get('/candidate-groups', [CandidateGroupController::class, 'index'])->name('candidate-groups.index');
            Route::post('/candidate-groups', [CandidateGroupController::class, 'store'])->name('candidate-groups.store');
            Route::patch('/candidate-groups/{candidateGroup}', [CandidateGroupController::class, 'update'])->name('candidate-groups.update');
            Route::delete('/candidate-groups/{candidateGroup}', [CandidateGroupController::class, 'destroy'])->name('candidate-groups.destroy');
            Route::get('/candidates', [CandidateController::class, 'index'])->name('candidates.index');
            Route::get('/candidates/create', [CandidateController::class, 'create'])->name('candidates.create');
            Route::post('/candidates', [CandidateController::class, 'store'])->name('candidates.store');
            Route::get('/candidates/{candidate}', [CandidateController::class, 'show'])->name('candidates.show');
            Route::get('/candidates/{candidate}/edit', [CandidateController::class, 'edit'])->name('candidates.edit');
            Route::patch('/candidates/{candidate}', [CandidateController::class, 'update'])->name('candidates.update');
            Route::delete('/candidates/{candidate}', [CandidateController::class, 'destroy'])->name('candidates.destroy');
        });

        Route::middleware('permission:viewReports')->group(function (): void {
            Route::get('/results', [ResultController::class, 'index'])->name('results.index');
            Route::get('/results/exams/{exam}', [ResultController::class, 'show'])->name('results.exams.show');
            Route::get('/results/attempts/{attempt}', [ResultController::class, 'candidate'])->name('results.attempts.show');
            Route::get('/results/attempts/{attempt}/marked-paper.pdf', [ResultController::class, 'markedPaperPdf'])->name('results.attempts.marked-paper');
            Route::get('/results/exams/{exam}/export.csv', [ResultController::class, 'exportCsv'])->middleware('plan.feature:csv_export')->name('results.exams.export-csv');
            Route::get('/results/exams/{exam}/summary.pdf', [ResultController::class, 'exportPdf'])->middleware('plan.feature:pdf_export')->name('results.exams.export-pdf');
        });
    });

    Route::middleware(['role:super_admin,organization_admin,examiner,supervisor,center_admin,school_admin,secondary_school_admin,professional_school_admin,cbt_center_admin', 'permission:viewReports', 'plan.feature:custom_reports'])->group(function (): void {
        Route::get('/reports', fn () => Inertia::render('Portal/Placeholder', [
            'title' => 'Reports',
        ]))->name('reports.index');
    });

    Route::middleware('role:super_admin,supervisor,center_admin')->group(function (): void {
        Route::get('/assigned-exams', fn () => Inertia::render('Portal/Placeholder', [
            'title' => 'Assigned Exams',
        ]))->middleware('permission:viewSupervisorMonitor')->name('assigned-exams.index');

        Route::get('/supervisor-monitor', fn () => Inertia::render('Portal/Placeholder', [
            'title' => 'Supervisor Monitor',
        ]))->middleware('permission:viewSupervisorMonitor')->name('supervisor-monitor.index');

        Route::get('/candidate-activity', fn () => Inertia::render('Portal/Placeholder', [
            'title' => 'Candidate Activity',
        ]))->middleware('permission:viewSupervisorMonitor')->name('candidate-activity.index');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
