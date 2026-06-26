<?php

use App\Http\Controllers\AccessControlController;
use App\Http\Controllers\CenterController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SchoolController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Public/Welcome'))->name('home');

Route::get('/ui-preview', fn () => Inertia::render('UiPreview/Index'))->name('ui-preview');

Route::middleware(['auth', 'portal.user'])->group(function () {
    Route::get('/dashboard', fn () => Inertia::render('Dashboard'))->name('dashboard');
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

    Route::get('/organizations/{organization}', [OrganizationController::class, 'show'])
        ->middleware('role:super_admin,organization_admin')
        ->name('organizations.show');

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

    Route::middleware('role:super_admin,organization_admin,examiner,school_admin')->group(function (): void {
        Route::get('/subjects', fn () => Inertia::render('Portal/Placeholder', [
            'title' => 'Subjects',
        ]))->middleware('permission:manageQuestionBank')->name('subjects.index');

        Route::get('/topics', fn () => Inertia::render('Portal/Placeholder', [
            'title' => 'Topics',
        ]))->middleware('permission:manageQuestionBank')->name('topics.index');

        Route::get('/question-bank', fn () => Inertia::render('Portal/Placeholder', [
            'title' => 'Question Bank',
        ]))->middleware('permission:manageQuestionBank')->name('question-bank.index');

        Route::get('/exams', fn () => Inertia::render('Portal/Placeholder', [
            'title' => 'Exams',
        ]))->middleware('permission:manageExams')->name('exams.index');

        Route::get('/candidates', fn () => Inertia::render('Portal/Placeholder', [
            'title' => 'Candidates',
        ]))->middleware('permission:manageExams')->name('candidates.index');

        Route::get('/results', fn () => Inertia::render('Portal/Placeholder', [
            'title' => 'Results',
        ]))->middleware('permission:viewReports')->name('results.index');
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
