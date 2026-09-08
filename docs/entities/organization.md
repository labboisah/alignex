# Organization knowledge base

Reviewed: 8 September 2026. Context key: `organization`. Status: organization administration and shared exam workflows are implemented, with unresolved regression findings.

## Purpose and identity

An organization represents a company, NGO, recruitment agency, government agency, certification body, education foundation, association, community group, school-group owner or another operating body.

It is its own exam/content owner, not a school subtype. [Organization](../../app/Models/Organization.php) stores name/code/type, description, logo, website, contact person, email, phone, address, status and pricing-plan association.

An organization has users, candidates, subjects, banks and exams. Its model also relates to secondary schools, professional schools and CBT centers. Child entities retain their own contexts and rules.

## Implemented modules

- Organization listing, creation, profile view/edit, update and deactivation.
- Organization-scoped users and context-aware navigation.
- Candidate registration and reusable candidate groups through shared modules.
- Subjects/topics, question banks, questions and imports.
- Recruitment, assessment, certification, professional, practice and general exams.
- Shared paper generation, candidate attempts, supervision, results and reports.
- Pricing-plan association; selected capabilities are controlled by plan-feature middleware.

[OrganizationController](../../app/Http/Controllers/OrganizationController.php) uses dedicated store/update requests and [OrganizationPolicy](../../app/Policies/OrganizationPolicy.php). Form and page data are served through Inertia.

## Context and permissions

Super admins have platform-level capabilities. Organization administrators work within authorized organization resources and available child contexts. Other user roles still depend on explicit permissions and relevant resource policies.

[CurrentContextService](../../app/Services/CurrentContextService.php) includes the organization and its secondary-school, professional-school and CBT-center contexts. It validates a requested context against the user's available list before persisting the selection. The selected context changes menus and terminology; it should never be treated as independent authorization.

Organization-linked institution data exists in the institution model, but this service does not automatically enumerate institutions among an organization's child contexts.

## Exam rules

| Setting | Current rule |
| --- | --- |
| Owner | `organization`, with owner ID and organization foreign key |
| Categories | Recruitment, assessment, certification, professional, practice, general |
| Modes | Traditional and adaptive configuration permitted |
| Assignment | Direct candidate IDs and/or reusable candidate groups |
| Banks | At least one bank must resolve for each paper setup row |
| Academic structure | Secondary academic fields and professional structure fields are not required; current request rules prohibit several of these for organization context |

Adaptive mode is configuration only at present; its candidate workflow is not response-dependent. Organization certification and professional categories share available certificate/eligibility tooling where supported by those services and permissions.

## Operational workflow

1. Set up organization identity, users and relevant plan.
2. Select organization context rather than a child school/center when the organization owns the exam.
3. Register candidates and arrange reusable groups where useful.
4. Create subjects, banks and questions.
5. Create the exam, choose an allowed category/mode, configure schedule/paper rows and assign candidates/groups.
6. Preview/generate papers; use participant refresh when group-derived membership changes.
7. Monitor writing and incidents.
8. Review results, use authorized reports/exports and apply supported certificate settings.

Choosing a recruitment category does not by itself create a complete applicant-tracking or hiring-decision system. An exam result is not an implemented recruitment pipeline.

## Data and implementation map

| Area | Source |
| --- | --- |
| Entity profile | [Organization model](../../app/Models/Organization.php), [controller](../../app/Http/Controllers/OrganizationController.php), [pages](../../resources/js/Pages/Organizations) |
| Access | [OrganizationPolicy](../../app/Policies/OrganizationPolicy.php), [shared organization authorization](../../app/Policies/Concerns/AuthorizesOrganizationAccess.php) |
| Exam rules | [ExamOwnershipRules](../../app/Support/ExamOwnershipRules.php), [StoreExamRequest](../../app/Http/Requests/StoreExamRequest.php) |
| Assignments | [ExamParticipantAssignmentService](../../app/Services/ExamParticipantAssignmentService.php), [CandidateGroupController](../../app/Http/Controllers/CandidateGroupController.php) |
| Routes | [web.php](../../routes/web.php): `/organizations` and shared candidate, content, exam and result routes |
| Tests | [OrganizationModuleTest](../../tests/Feature/OrganizationModuleTest.php), [SharedExamWorkflowTest](../../tests/Feature/SharedExamWorkflowTest.php) |

## Known limitations

The previous audit run found organization validation-error-key and navigation-label expectation failures, and result/export access failures in the broader selection. Diagnose current permissions, feature entitlement and test fixtures before declaring these paths fully verified.

The shared audit also found score disclosure at submission, pre-start paper disclosure and an answer/submission race risk. Those apply to organization exams too.


## Shared behavior, verification and maintenance

[Traditional CBT](../exams/traditional.md) documents the common candidate lifecycle, monitoring, scoring, result APIs, exports and known security gaps. [Adaptive examination](../exams/adaptive.md) separates the current prototype from planned delivery.

All public/admin screens must remain Laravel/Inertia pages. Candidate writing remains the React Router island under `/exam/*`. Context switching and menu visibility do not replace resource authorization or feature-entitlement checks.

The [8 September audit](../adaptive-examination-audit-2026-09-08.md) ran 69 selected tests: 47 passed, 19 failed and 3 errored. Existing code and test coverage are documented here without claiming universal acceptance. No tests were rerun for this documentation-only change.

When changing this entity, update its workflow, fields, validation, permissions, route/UI references and test status here. Check both exam-mode documents for effects on shared delivery.
