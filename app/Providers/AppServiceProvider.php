<?php

namespace App\Providers;

use App\Models\Candidate;
use App\Models\Certificate;
use App\Models\Center;
use App\Models\CbtCenter;
use App\Models\Exam;
use App\Models\Organization;
use App\Models\ProfessionalSchool;
use App\Models\QuestionBank;
use App\Models\Question;
use App\Models\Report;
use App\Models\Result;
use App\Models\School;
use App\Models\SecondarySchool;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use App\Policies\CandidatePolicy;
use App\Policies\CertificatePolicy;
use App\Policies\CenterPolicy;
use App\Policies\CbtCenterPolicy;
use App\Policies\ExamPolicy;
use App\Policies\OrganizationPolicy;
use App\Policies\ProfessionalSchoolPolicy;
use App\Policies\QuestionBankPolicy;
use App\Policies\QuestionPolicy;
use App\Policies\ReadMostlyOrganizationPolicy;
use App\Policies\SchoolPolicy;
use App\Policies\SecondarySchoolPolicy;
use App\Policies\StudentPolicy;
use App\Policies\SubjectPolicy;
use App\Policies\TopicPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Organization::class, OrganizationPolicy::class);
        Gate::policy(Center::class, CenterPolicy::class);
        Gate::policy(CbtCenter::class, CbtCenterPolicy::class);
        Gate::policy(SecondarySchool::class, SecondarySchoolPolicy::class);
        Gate::policy(ProfessionalSchool::class, ProfessionalSchoolPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Subject::class, SubjectPolicy::class);
        Gate::policy(Topic::class, TopicPolicy::class);
        Gate::policy(QuestionBank::class, QuestionBankPolicy::class);
        Gate::policy(Question::class, QuestionPolicy::class);
        Gate::policy(Exam::class, ExamPolicy::class);
        Gate::policy(Candidate::class, CandidatePolicy::class);
        Gate::policy(Student::class, StudentPolicy::class);
        Gate::policy(Certificate::class, CertificatePolicy::class);
        Gate::policy(Result::class, ReadMostlyOrganizationPolicy::class);
        Gate::policy(Report::class, ReadMostlyOrganizationPolicy::class);
        Gate::policy(School::class, SchoolPolicy::class);

        Vite::prefetch(concurrency: 3);
    }
}
