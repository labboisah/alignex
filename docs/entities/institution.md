# Institution knowledge base

Reviewed: 8 September 2026. Context key: `institution`. Status: institution structure and course-assessment implementation exists; some entity profile UI is incomplete.

## Purpose and identity

An institution represents a course-based academic organization, with faculties, departments, programmes and courses. It is a separate model/context from secondary and professional schools.

[Institution](../../app/Models/Institution.php) stores optional organization association, name/code, institution type, email, phone, address, description and active/inactive status. Its relationships include organization, faculties, departments, programmes, courses and question banks.

The main hierarchy is institution -> faculty -> department -> programme -> course. Learners use candidate and candidate-group records with institution/faculty/department scope. Lecturers use the shared user model.

## Implemented modules

| Module | Current behavior |
| --- | --- |
| Institution records | List, show, create/update backend actions and deactivation |
| Faculties | List and create within institution |
| Departments | List, create, update and delete within institution |
| Programmes/courses | List, create, update and delete with hierarchy relationships |
| Lecturers | Department-based listing, create/edit/delete and assigned-course selection |
| Candidates/groups | Shared candidate workflow with institutional hierarchy and group assignment |
| Question banks | Course-linked banks; institutional banks can have null subject ID |
| Questions/imports | Shared question authoring/import workflow with institution course/bank context |
| Assessments | Course paper rows, group-derived participants, refresh assignment and shared exam delivery |
| Results | Shared result summaries, candidate details and permission/plan-controlled exports |

[InstitutionStructureController](../../app/Http/Controllers/InstitutionStructureController.php) implements the hierarchy and lecturer actions. Faculties should not be described as having full update/delete CRUD merely because other structure sections do.

## Roles and authorization

Super admins create institutions. Institution administrators access their assigned institution and its structure. The profile controller uses inline authorization checks, rather than a dedicated InstitutionPolicy in the inspected source.

Lecturer creation stores `User::ROLE_FACILITATOR`, with institution, faculty and department IDs and assigned courses. “Lecturer” is context-specific terminology over the facilitator role. Exam request validation limits facilitator-created exams to assessments and institution paper rows to assigned courses.

[CurrentContextService](../../app/Services/CurrentContextService.php) recognizes institution as the fifth context and supports direct user institution membership. Its organization-child enumeration currently adds secondary schools, professional schools and CBT centers, not institutions; do not infer institutional context access from an organization association alone.

## Assessment setup

1. Prepare faculty, department, programme and courses.
2. Create lecturers and assign the courses they may manage.
3. Register candidates and place them in the appropriate institution/department candidate groups.
4. Create course-linked banks and author/import questions.
5. Create an assessment using course paper rows, bank selection, question counts and candidate groups.
6. Refresh participants when group membership changes, then preview/generate papers.
7. Use the shared candidate exam, supervision and result workflow.

Current rules:

- Only the `assessment` category is allowed for institution-owned exams.
- Traditional and adaptive mode values are permitted, but adaptive delivery is still the shared fixed-paper behavior.
- Candidate groups are required; direct candidates alone do not satisfy institutional assignment validation.
- Each paper row requires a course in this institution. Facilitators must have that course assigned.
- Secondary fields (session, term, class, student group) and professional module/batch fields are rejected.
- Institution paper rows may have null subject IDs; do not add a blanket subject-required rule to shared code.

## UI and route map

The main route family is `/institutions`, with institution-scoped structure routes and department lecturer routes in [web.php](../../routes/web.php). Assessment authoring uses shared `/exams`, `/question-bank`, `/questions` and candidate/group pages.

[Institution pages](../../resources/js/Pages/Institutions) include Index, Show, Faculties, Departments, Programmes, Courses and lecturer list/form/create/edit pages.

**Known profile UI gap:** InstitutionController renders `Institutions/Create` and `Institutions/Edit`, but matching Create.tsx/Edit.tsx pages were not present in the inspected directory. Backend actions alone should not be described as a completed institution profile creation/editing experience.

## Code and tests

- [InstitutionController](../../app/Http/Controllers/InstitutionController.php): profile data, validation and access checks.
- [StoreExamRequest](../../app/Http/Requests/StoreExamRequest.php): institution detection, groups and course validation.
- [ExamController](../../app/Http/Controllers/ExamController.php): owner-specific paper setup and assignment.
- [ExamPolicy](../../app/Policies/ExamPolicy.php): shared assessment authorization.
- [InstitutionStructureFeatureTest](../../tests/Feature/InstitutionStructureFeatureTest.php): academic hierarchy coverage.
- [InstitutionAssessmentFeatureTest](../../tests/Feature/InstitutionAssessmentFeatureTest.php): course bank/question/import/assessment and lecturer group-scope coverage.

The earlier audit selection reported no failures/errors for InstitutionAssessmentFeatureTest; InstitutionStructureFeatureTest was not part of that selection. No full adaptive institutional delivery test was established.


## Shared behavior, verification and maintenance

[Traditional CBT](../exams/traditional.md) documents the common candidate lifecycle, monitoring, scoring, result APIs, exports and known security gaps. [Adaptive examination](../exams/adaptive.md) separates the current prototype from planned delivery.

All public/admin screens must remain Laravel/Inertia pages. Candidate writing remains the React Router island under `/exam/*`. Context switching and menu visibility do not replace resource authorization or feature-entitlement checks.

The [8 September audit](../adaptive-examination-audit-2026-09-08.md) ran 69 selected tests: 47 passed, 19 failed and 3 errored. Existing code and test coverage are documented here without claiming universal acceptance. No tests were rerun for this documentation-only change.

When changing this entity, update its workflow, fields, validation, permissions, route/UI references and test status here. Check both exam-mode documents for effects on shared delivery.
