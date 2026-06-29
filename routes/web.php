<?php

use App\Http\Controllers\AccessControlController;
use App\Http\Controllers\AdminRegistrationController;
use App\Http\Controllers\CandidateController;
use App\Http\Controllers\CenterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\ExamMonitorController;
use App\Http\Controllers\ExamPaperController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicWelcomeController;
use App\Http\Controllers\ProfessionalExamController;
use App\Http\Controllers\QuestionBankController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\RecruitmentController;
use App\Http\Controllers\ResultController;
use App\Http\Controllers\SchoolController;
use App\Http\Controllers\SecondarySchoolController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\TopicController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', PublicWelcomeController::class)->name('home');

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

    Route::get('/organizations/{organization}', [OrganizationController::class, 'show'])
        ->middleware('role:super_admin,organization_admin')
        ->name('organizations.show');

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
        Route::get('/users', fn () => Inertia::render('Portal/Placeholder', [
            'title' => 'Users',
        ]))->name('users.index');
    });

    Route::middleware(['role:super_admin,organization_admin', 'permission:manageSettings'])->group(function (): void {
        Route::get('/settings', fn () => Inertia::render('Portal/Placeholder', [
            'title' => 'Settings',
        ]))->name('settings.index');
    });

    Route::middleware('role:super_admin,organization_admin,examiner,school_admin,center_admin,supervisor')->group(function (): void {
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
            Route::patch('/exams/{exam}/professional/templates/{template}', [ProfessionalExamController::class, 'updateTemplate'])->name('exams.professional.templates.update');
            Route::delete('/exams/{exam}/professional/templates/{template}', [ProfessionalExamController::class, 'destroyTemplate'])->name('exams.professional.templates.destroy');
            Route::patch('/exams/{exam}/professional/attempts/{attempt}/payment', [ProfessionalExamController::class, 'updatePayment'])->name('exams.professional.payment');
            Route::post('/exams/{exam}/professional/certificates', [ProfessionalExamController::class, 'generateCertificates'])->name('exams.professional.certificates.generate');
            Route::post('/exams/{exam}/professional/attempts/{attempt}/certificate', [ProfessionalExamController::class, 'generateCertificate'])->name('exams.professional.certificate.generate');
            Route::get('/exams/{exam}/certification', [ProfessionalExamController::class, 'show'])->name('exams.certification.show');
            Route::patch('/exams/{exam}/certification/settings', [ProfessionalExamController::class, 'updateSettings'])->name('exams.certification.settings');
            Route::post('/exams/{exam}/certification/templates', [ProfessionalExamController::class, 'storeTemplate'])->name('exams.certification.templates.store');
            Route::patch('/exams/{exam}/certification/templates/{template}', [ProfessionalExamController::class, 'updateTemplate'])->name('exams.certification.templates.update');
            Route::delete('/exams/{exam}/certification/templates/{template}', [ProfessionalExamController::class, 'destroyTemplate'])->name('exams.certification.templates.destroy');
            Route::patch('/exams/{exam}/certification/attempts/{attempt}/payment', [ProfessionalExamController::class, 'updatePayment'])->name('exams.certification.payment');
            Route::post('/exams/{exam}/certification/certificates', [ProfessionalExamController::class, 'generateCertificates'])->name('exams.certification.certificates.generate');
            Route::post('/exams/{exam}/certification/attempts/{attempt}/certificate', [ProfessionalExamController::class, 'generateCertificate'])->name('exams.certification.certificate.generate');
        });

        Route::middleware('permission:manageExams')->group(function (): void {
            Route::get('/secondary-school', [SecondarySchoolController::class, 'index'])->name('secondary-school.index');
            Route::post('/secondary-school/sessions', [SecondarySchoolController::class, 'storeSession'])->name('secondary-school.sessions.store');
            Route::post('/secondary-school/terms', [SecondarySchoolController::class, 'storeTerm'])->name('secondary-school.terms.store');
            Route::post('/secondary-school/classes', [SecondarySchoolController::class, 'storeClass'])->name('secondary-school.classes.store');
            Route::post('/secondary-school/groups', [SecondarySchoolController::class, 'storeGroup'])->name('secondary-school.groups.store');
            Route::patch('/secondary-school/exams/{exam}/ca-setup', [SecondarySchoolController::class, 'setupAssessment'])->name('secondary-school.ca-setup');
            Route::post('/secondary-school/exams/{exam}/assessments', [SecondarySchoolController::class, 'storeAssessment'])->name('secondary-school.assessments.store');
            Route::get('/secondary-school/exams/{exam}/candidates/{candidate}/report-card.pdf', [SecondarySchoolController::class, 'reportCard'])->name('secondary-school.report-card');
        });

        Route::get('/exams/{exam}/monitor', [ExamMonitorController::class, 'show'])->name('exams.monitor.show');
        Route::get('/exams/{exam}/monitor/summary', [ExamMonitorController::class, 'summary'])->name('exams.monitor.summary');
        Route::get('/exams/{exam}/monitor/rows', [ExamMonitorController::class, 'rows'])->name('exams.monitor.rows');
        Route::get('/exams/{exam}/monitor/feed', [ExamMonitorController::class, 'feed'])->name('exams.monitor.feed');
        Route::get('/exams/{exam}/monitor/events', [ExamMonitorController::class, 'events'])->name('exams.monitor.events');
        Route::post('/exams/{exam}/monitor/attempts/{attempt}/reset', [ExamMonitorController::class, 'reset'])->name('exams.monitor.reset');

        Route::middleware('permission:manageExams')->group(function (): void {
            Route::get('/candidates/template', [CandidateController::class, 'template'])->name('candidates.template');
            Route::post('/candidates/import', [CandidateController::class, 'import'])->name('candidates.import');
            Route::get('/candidates/import-errors/{filename}', [CandidateController::class, 'errorReport'])->name('candidates.import-errors');
            Route::get('/candidates/assignments', [CandidateController::class, 'assignments'])->name('candidates.assignments');
            Route::post('/candidates/assign', [CandidateController::class, 'assign'])->name('candidates.assign');
            Route::delete('/exams/{exam}/candidates/{candidate}', [CandidateController::class, 'unassign'])->name('candidates.unassign');
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
            Route::get('/results/exams/{exam}/export.csv', [ResultController::class, 'exportCsv'])->name('results.exams.export-csv');
            Route::get('/results/exams/{exam}/summary.pdf', [ResultController::class, 'exportPdf'])->name('results.exams.export-pdf');
        });
    });

    Route::middleware('role:super_admin,organization_admin,examiner,supervisor,center_admin,school_admin')->group(function (): void {
        Route::get('/reports', fn () => Inertia::render('Portal/Placeholder', [
            'title' => 'Reports',
        ]))->middleware('permission:viewReports')->name('reports.index');
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
