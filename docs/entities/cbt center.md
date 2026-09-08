# CBT center knowledge base

Reviewed: 8 September 2026. Context key: `cbt_center`. Status: center administration, content/candidate management and shared exam delivery code exists; adaptive and offline acceptance remain incomplete.

## Purpose and identity

A CBT center is both an operating context for its own examinations and a possible delivery location for externally owned exams. It does not require a secondary-school academic structure or professional training hierarchy.

[CbtCenter](../../app/Models/CbtCenter.php) is distinct from the legacy Center model. Center profiles include optional organization association, name/code, location, capacity, contact person, email, phone and active/inactive status.

Stored capacity is administrative data; its presence alone does not prove concurrent seat reservation or automatic capacity enforcement.

## Implemented modules

- Center listing, creation, profile view/edit/update.
- Center candidate registration, list, import and template.
- Center question-bank list and creation, with shared question tools.
- Shared candidate-group management and exam setup.
- Center-owned recruitment, assessment, certification, professional, practice and general exams.
- External-exam assignment records and status tracking.
- Shared monitoring/results and offline activation/package integration points.

[CbtCenterController](../../app/Http/Controllers/CbtCenterController.php) provides center-specific actions. Candidate numbers are validated within the center scope, and the current candidate registration/import contract should be used rather than older sample payloads.

## Roles and context

CBT center administrators manage their authorized center; organization/platform users may have broader access according to policies. [CbtCenterPolicy](../../app/Policies/CbtCenterPolicy.php) and controller authorization checks apply.

[CurrentContextService](../../app/Services/CurrentContextService.php) recognizes both newer `cbt_center_id` associations and legacy `center_id` context sources. Those identifiers belong to different models and must not be treated as interchangeable.

Center context controls menus and terminology. Proctor/monitoring access still depends on exam scope and supervisor permissions.

## Exam configuration

| Setting | Current rule |
| --- | --- |
| Categories | Recruitment, assessment, certification, professional, practice, general |
| Modes | Traditional/adaptive values permitted; actual candidate delivery remains fixed-paper |
| Assignment | One or more candidate groups required |
| Direct candidate IDs | Explicitly rejected by the current CBT exam request |
| Banks | At least one selected/fallback bank for each paper row |
| School/training fields | Session, term, class, student group, programme, course, module and batch fields rejected |

The center's own candidate screen can register individual people; that is different from the exam assignment rule requiring groups.

## Typical center-owned workflow

1. Create the center profile and select its context.
2. Register/import candidates and arrange them into candidate groups.
3. Create banks, author/import questions and configure subject paper rows as required by the shared wizard.
4. Create an allowed exam with schedule, duration, settings and candidate groups.
5. Refresh participants if membership changes and preview/generate papers.
6. Use the candidate app and authorized monitoring pages to administer the exam.
7. Review results and permitted exports/certificate functions.

Traditional exams use stored papers and server-based timing/scoring as described in [traditional CBT](../exams/traditional.md).

## Hosting externally owned exams

[CbtCenterController::assignExternalExam](../../app/Http/Controllers/CbtCenterController.php) authorizes center update and creates/updates [ExamCenterAssignment](../../app/Models/ExamCenterAssignment.php), recording exam, center, assigned-by user, assigned timestamp and status.

Statuses include assigned, active, completed and cancelled. Assignment does not change the exam's content owner.

**Known authorization limitation:** the inspected action validates that the exam ID exists but does not visibly authorize that exam separately. This documentation does not establish safe cross-owner hosting authorization. Review that boundary before relying on hosted exam assignment to grant access to another owner's content, candidates or results.

## Online and offline boundaries

The candidate APIs support online server-authoritative examination. [OfflineExamPackageController](../../app/Http/Controllers/Api/OfflineExamPackageController.php) checks activation/admin access, requires generated candidate papers and builds an import package. Activation and offline download routes have separate controls.

That package code is evidence of an integration boundary, not proof of a fully accepted local runtime. Local execution, signing/tamper controls, reconnection, conflict resolution, synchronization and result reconciliation need their own verification. Adaptive offline delivery is not implemented by exporting fixed candidate papers.

## UI and source map

- Main family: `/cbt-centers`, with center candidate/bank and external assignment actions registered in [web.php](../../routes/web.php).
- [Center pages](../../resources/js/Pages/CbtCenters): profile forms, listing/show, candidates and question banks.
- [StoreExamRequest](../../app/Http/Requests/StoreExamRequest.php): group-only assignment and excluded academic fields.
- [ExamParticipantAssignmentService](../../app/Services/ExamParticipantAssignmentService.php): candidate synchronization.
- [ExamMonitorController](../../app/Http/Controllers/ExamMonitorController.php): supervision endpoints.
- Tests: [CbtCenterFeatureTest](../../tests/Feature/CbtCenterFeatureTest.php), [CbtSchemaTest](../../tests/Feature/CbtSchemaTest.php).

## Known test and readiness gaps

The prior audit selection found failed candidate/bank creation assertions, adaptive recruitment creation assertions, a missing-exam error and navigation expectation differences. Resolve payload/validation/test compatibility before declaring the center workflow fully verified. CbtSchemaTest was not included in that selection.

For future adaptive delivery, preserve content ownership, center assignment, existing fixed-paper generation and offline-package compatibility. Candidate groups and hosted-center permissions must be included in adaptive acceptance tests.


## Shared behavior, verification and maintenance

[Traditional CBT](../exams/traditional.md) documents the common candidate lifecycle, monitoring, scoring, result APIs, exports and known security gaps. [Adaptive examination](../exams/adaptive.md) separates the current prototype from planned delivery.

All public/admin screens must remain Laravel/Inertia pages. Candidate writing remains the React Router island under `/exam/*`. Context switching and menu visibility do not replace resource authorization or feature-entitlement checks.

The [8 September audit](../adaptive-examination-audit-2026-09-08.md) ran 69 selected tests: 47 passed, 19 failed and 3 errored. Existing code and test coverage are documented here without claiming universal acceptance. No tests were rerun for this documentation-only change.

When changing this entity, update its workflow, fields, validation, permissions, route/UI references and test status here. Check both exam-mode documents for effects on shared delivery.
