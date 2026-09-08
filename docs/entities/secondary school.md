# Secondary school knowledge base

Reviewed: 8 September 2026. Context key: `secondary_school`. Status: academic administration and traditional exam/assessment implementation exists, with legacy compatibility and regression gaps.

## Purpose and academic structure

A secondary school manages academic sessions, terms, classes, arms/sections, student groups, students, teachers, subjects and topics. It may belong to an organization, but it remains its own operating context.

The main relationships are school -> academic session -> term and school -> class -> arm/group -> student. Subjects and teacher assignments provide the content/teaching scope. Students link to candidate identities for the common exam engine.

[SecondarySchool](../../app/Models/SecondarySchool.php), [Student](../../app/Models/Student.php) and [StudentGroup](../../app/Models/StudentGroup.php) are central records. Legacy School/Candidate-based paths also remain in the controller.

## Implemented administration

- School listing, create/show/edit/update.
- Academic sessions, active-session selection and session update/delete.
- Terms, classes, arms and student groups with management actions.
- Student registration/update/delete and structure/student import templates.
- Teachers with assigned subjects and management actions.
- Subject/topic creation and shared question-bank/question workflows.
- Terminal exams and assessments using the common exam wizard.
- Secondary overview/teacher dashboard with descriptive weakness reporting.

[SecondarySchoolController](../../app/Http/Controllers/SecondarySchoolController.php) contains both entity-scoped and legacy administration methods. Prefer current registered routes; a controller method's existence does not ensure its old URL is available.

## Roles and authorization

Secondary school administrators work within authorized school records. Teachers use subject assignments and restricted assessment workflows. [ExamPolicy](../../app/Policies/ExamPolicy.php) limits teacher-managed assessments using category, creator and subject access checks; [StoreExamRequest](../../app/Http/Requests/StoreExamRequest.php) limits teacher creation/update to assessment category.

Teacher management routes can require the teacher-management plan feature. [SecondarySchoolPolicy](../../app/Policies/SecondarySchoolPolicy.php), [StudentPolicy](../../app/Policies/StudentPolicy.php) and controller checks also apply.

Legacy `school_id` and newer `secondary_school_id` are both referenced in context/authorization code. Do not merge their records or assume equal numeric IDs represent the same school.

## Current exam rules

| Requirement | Current behavior |
| --- | --- |
| Categories | Terminal and assessment |
| Mode | Traditional only; adaptive rejected for both categories |
| Academic assignment | Academic session, term/academic term and student group required |
| Paper setup | Subject rows with bank selection, count and marks settings |
| Participants | Derived from selected school group and synchronized student/candidate identities |
| Excluded structures | Programme, course, module and training batch fields rejected |

Older top-level documentation says “terminal only.” Current ownership rules and validation also allow `assessment`; use the current code rather than that older description.

## Typical workflow

1. Create/select the school and academic session.
2. Define the term, classes, arms and student groups.
3. Register/import students and maintain their group/candidate links.
4. Add teachers and assigned subjects.
5. Prepare subject banks and questions.
6. Create a traditional terminal exam or assessment with session, term, group and subject paper rows.
7. Synchronize participants and generate papers before the exam begins.
8. Administer the common candidate workflow and review results/weakness summaries.

Paper generation needs each assigned student to resolve to a candidate record. If that mapping is missing, the generator raises a validation error rather than producing an anonymous attempt.

## Continuous assessment and report cards

Two data foundations exist:

- [ContinuousAssessment](../../app/Models/ContinuousAssessment.php): CA score, exam score, total, grade, teacher comment and academic/candidate/exam links.
- [ReportCard](../../app/Models/ReportCard.php): school/session/term/student/candidate/exam references, totals, average, grade, metadata, status and publication timestamp.

These models should not be described as a complete report-card publishing or weighted continuous-assessment workflow. This review did not establish registered end-to-end CA aggregation/report-card publication routes. The implemented shared exam `assessment` category is separate from proving that those aggregate academic-record workflows are complete.

[SecondarySchoolService](../../app/Services/SecondarySchoolService.php) supplies scoped sessions, classes, candidates, subjects, dashboard data, weakness reporting and a grade helper.

## UI, routes and tests

- Main family: `/secondary-schools` and school-scoped academic sections, plus registered legacy `/secondary-school` routes.
- [Secondary school pages](../../resources/js/Pages/SecondarySchools), [secondary overview](../../resources/js/Pages/Secondary/Index.tsx).
- [Web routes](../../routes/web.php), [ownership rules](../../app/Support/ExamOwnershipRules.php), [assignment service](../../app/Services/ExamParticipantAssignmentService.php).
- [SecondarySchoolFeatureTest](../../tests/Feature/SecondarySchoolFeatureTest.php) covers administration, imports and exam restrictions.
- [SharedExamWorkflowTest](../../tests/Feature/SharedExamWorkflowTest.php) includes secondary adaptive rejection.

## Known limitations and adaptive boundary

The prior audit selection found secondary 404/403 responses, navigation differences, class-arm import expectation differences and a missing student-group error. Current and legacy route/test expectations need reconciliation.

Future adaptive formative assessment is a proposal, not current functionality. It should require explicit assessment-category permission while retaining traditional terminal exams, teacher scope, academic group assignment and historical marks.


## Shared behavior, verification and maintenance

[Traditional CBT](../exams/traditional.md) documents the common candidate lifecycle, monitoring, scoring, result APIs, exports and known security gaps. [Adaptive examination](../exams/adaptive.md) separates the current prototype from planned delivery.

All public/admin screens must remain Laravel/Inertia pages. Candidate writing remains the React Router island under `/exam/*`. Context switching and menu visibility do not replace resource authorization or feature-entitlement checks.

The [8 September audit](../adaptive-examination-audit-2026-09-08.md) ran 69 selected tests: 47 passed, 19 failed and 3 errored. Existing code and test coverage are documented here without claiming universal acceptance. No tests were rerun for this documentation-only change.

When changing this entity, update its workflow, fields, validation, permissions, route/UI references and test status here. Check both exam-mode documents for effects on shared delivery.
