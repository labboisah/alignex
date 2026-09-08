# Professional school knowledge base

Reviewed: 8 September 2026. Context key: `professional_school`. Status: training administration, assessments and certification-related implementation exists; adaptive delivery remains incomplete.

## Purpose and structure

A professional school represents a training provider, academy or certification-oriented school. It is distinct from a secondary school and an institution.

The core structure is professional school -> programme -> course -> module, with training batches grouping candidates/trainees. A school may belong to an organization. Users, banks, exams and certificates carry professional-school scope.

The [ProfessionalSchool model](../../app/Models/ProfessionalSchool.php) and [ProfessionalSchoolController](../../app/Http/Controllers/ProfessionalSchoolController.php) provide profile, training, content and learner operations.

## Implemented modules

| Module | Current implementation |
| --- | --- |
| School profile | Listing, create/show/edit/update |
| Programmes/courses/modules | Hierarchy lists and creation |
| Training batches | List/create/update/delete and learner association |
| Facilitators | Dedicated list/create/edit pages, course assignments and removal |
| Candidates/trainees | Registration, listing, import/template flows and batch links |
| Question banks/questions | School-specific views, authoring and question import/template flows |
| Exams | Professional, certification, practice and assessment categories through the shared wizard |
| Certificates | School certificate list plus shared per-exam settings, templates, issuance/download and verification |

Do not assume every hierarchy module exposes identical CRUD operations. See the controller and current routes for each action.

## Roles and scope

Professional school administrators manage their authorized school. Facilitators use the shared facilitator role and course assignments; facilitator exam creation/update is restricted to assessment category by request validation.

[ProfessionalSchoolPolicy](../../app/Policies/ProfessionalSchoolPolicy.php), controller authorization helpers and shared exam/question policies provide access checks. Facilitator-management and certificate routes include plan-feature gates in [web.php](../../routes/web.php).

Context terminology uses trainees/candidates and training structures. A visible adaptive menu or metric reports saved mode data; it does not establish working adaptive delivery.

## Exam setup rules

- Allowed categories: professional, certification, practice and assessment.
- Allowed mode values: traditional and adaptive.
- Programme and training batch are required by the shared exam request for professional-school setup.
- The selected batch must belong to the applicable school.
- Secondary academic session/term/class/student-group fields and top-level subject selection are rejected.
- Course/module content is represented through the training setup and shared paper-row storage; do not infer that every hierarchy field is required just because it appears in the UI.
- Participant synchronization uses the selected batch and candidate links.
- At least one question bank must resolve for each paper row.

Professional-school candidate registration currently accepts an optional training batch and derives related associations from it. Do not document direct submitted course/programme values as universally persisted by that candidate endpoint.

## Typical workflow

1. Create the school and select its context.
2. Add programmes, courses/modules and training batches.
3. Add facilitators and assign their courses.
4. Register/import trainees and place them in the appropriate batches.
5. Prepare question banks and import/author questions.
6. Create an allowed exam or assessment with programme, batch and paper setup.
7. Generate papers and administer the shared candidate workflow.
8. Review results and use certificate tools when supported by the exam category, settings and permissions.

## Professional and certificate services

[ProfessionalExamService](../../app/Services/ProfessionalExamService.php) reads/writes attempt limit, retake policy, payment-required, certificate auto-generation and validity settings. It manages certificate templates, generation, payment state, rows and verification.

The candidate login controller has professional exam eligibility checks for attempt limit and paid/waived status when its professional-type condition applies. A configured retake policy should not be described as proof of every automated retake flow. Payment-status management is not evidence of an integrated payment gateway.

The shared objective scorer records pass/fail and certification eligibility. Certificate generation and downloads exist but depend on eligibility and feature access. Validated adaptive certification scoring is not implemented.

## UI, routes and evidence

- Main family: `/professional-schools` with school-specific programme/course/module/batch/facilitator/candidate/content/certificate pages.
- Shared exam tools: `/exams/{exam}/professional` and `/exams/{exam}/certification`, with settings, template, payment and certificate actions.
- [Professional school pages](../../resources/js/Pages/ProfessionalSchools), [ProfessionalExamController](../../app/Http/Controllers/ProfessionalExamController.php).
- [StoreExamRequest](../../app/Http/Requests/StoreExamRequest.php), [ExamParticipantAssignmentService](../../app/Services/ExamParticipantAssignmentService.php).
- Tests: [ProfessionalExamFeatureTest](../../tests/Feature/ProfessionalExamFeatureTest.php), [ProfessionalFacilitatorManagementTest](../../tests/Feature/ProfessionalFacilitatorManagementTest.php), [ProfessionalExamServiceTest](../../tests/Unit/ProfessionalExamServiceTest.php).

## Known limitations

The prior audit selection found failures for professional hierarchy/adaptive creation, mixed-mode exam creation, candidate course association expectations and certificate access. Facilitator and unit suites listed above were not included in that audit selection. Resolve the baseline before asserting complete operation.

The adaptive selector ignores course/module/batch-specific blueprint concerns if connected unchanged. Future integration must preserve training eligibility, traditional scores and historical certificates.


## Shared behavior, verification and maintenance

[Traditional CBT](../exams/traditional.md) documents the common candidate lifecycle, monitoring, scoring, result APIs, exports and known security gaps. [Adaptive examination](../exams/adaptive.md) separates the current prototype from planned delivery.

All public/admin screens must remain Laravel/Inertia pages. Candidate writing remains the React Router island under `/exam/*`. Context switching and menu visibility do not replace resource authorization or feature-entitlement checks.

The [8 September audit](../adaptive-examination-audit-2026-09-08.md) ran 69 selected tests: 47 passed, 19 failed and 3 errored. Existing code and test coverage are documented here without claiming universal acceptance. No tests were rerun for this documentation-only change.

When changing this entity, update its workflow, fields, validation, permissions, route/UI references and test status here. Check both exam-mode documents for effects on shared delivery.
