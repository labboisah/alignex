# Management screen edit/delete audit

Reviewed: 9 September 2026.
Scope: current AlignEx cloud repository, including management screens for organizations, institutions, secondary schools, professional schools, and CBT centers.

## Overall assessment

Edit/delete support is uneven, but the source does not support the conclusion that most management screens lack both operations. Many core modules already have forms, routes, and controller actions. The main gaps are missing pages, create-only professional structure screens, partial secondary-school edit forms, and inconsistent record lifecycle controls.

This is a static implementation audit, not a browser certification. I inspected the page inventory, literal Inertia render targets, web routes, relevant controllers, selected policies, and test source. No production records were edited/deleted and no application behavior was changed. Existing tests were not executed for this report. A present button/route is classified as implemented in source, not guaranteed to work for every role or database state.

The inventory contains 157 files under Pages, including forms, types, charts, and authentication components; these are not 157 independent CRUD screens. No misleading completion percentage is assigned.

## Prioritized findings

### 1. High — Institution create/edit routes target missing pages

[InstitutionController.php:37](../app/Http/Controllers/InstitutionController.php#L37) renders Institutions/Create; [line 84](../app/Http/Controllers/InstitutionController.php#L84) renders Institutions/Edit. Neither matching TSX nor JSX file exists in the current Pages tree.

The institution list links to creation and the detail page links to editing. The update endpoint exists, but an organizer cannot use the expected form through these missing page targets.

**Required work:** implement Create, Edit, and a shared validated institution form; verify navigation and save behavior for super admins and the institution's authorized admin. This is a broken page chain, not just a missing action button.

### 2. High — Professional programmes, courses, and modules are create-only

The three screens contain creation forms and read-only tables without edit/delete actions:
- [Programmes.tsx](../resources/js/Pages/ProfessionalSchools/Programmes.tsx)
- [Courses.tsx](../resources/js/Pages/ProfessionalSchools/Courses.tsx)
- [Modules.tsx](../resources/js/Pages/ProfessionalSchools/Modules.tsx)

[routes/web.php:236](../routes/web.php#L236) registers GET/POST for these collections, but no corresponding update/delete routes. ProfessionalSchoolController has their store methods but no update/destroy counterparts.

**Impact:** an organizer cannot correct an existing programme/course/module through these screens, even though institution structure has those operations.

**Required work:** implement validated owner-scoped edit flows and lifecycle actions. Allow deletion only for unused records; provide inactivation/archive behavior for records referenced by batches, candidates, banks, or exams. Preserve active/frozen exam references.

### 3. High — Secondary class-arm management is not connected end to end

[SecondarySchoolController.php:297](../app/Http/Controllers/SecondarySchoolController.php#L297) and [line 901](../app/Http/Controllers/SecondarySchoolController.php#L901) render SecondarySchools/Arms, but that page is missing.

Legacy /secondary-school/arms routes include listing, creation, update, and deletion. The owner-specific controller methods arms/storeArmForSchool/updateArmForSchool/destroyArmForSchool exist, but equivalent /secondary-schools/{secondarySchool}/arms routes are absent from web.php.

**Required work:** restore the shared Arms screen and explicitly wire the supported owner-specific routes, navigation, permissions, and base paths. Keep the existing rule that an arm containing students cannot be deleted until those students are moved.

### 4. Medium — Secondary edit actions exist but do not expose full editing

These are partial implementations, not missing endpoints:

| Screen | What the edit interaction permits | Important fields not offered by that interaction |
| --- | --- | --- |
| [Academic sessions](../resources/js/Pages/SecondarySchools/AcademicSessions.tsx#L15) | Rename through a browser prompt | Code, start/end dates, status; active selection is a separate action |
| [Terms](../resources/js/Pages/SecondarySchools/Terms.tsx#L17) | Rename through a browser prompt | Academic session, code, dates, status, active flag |
| [Classes](../resources/js/Pages/SecondarySchools/Classes.tsx#L15) | Name and level prompts | Display order and status |
| [Students](../resources/js/Pages/SecondarySchools/Students.tsx#L18) | Full name and admission number prompts | Class, gender, contact details, guardian details, status |
| [Student groups](../resources/js/Pages/SecondarySchools/StudentGroups.tsx#L52) | Rename; membership has a separate editor | Class, code, status in the rename interaction |
| [Teachers](../resources/js/Pages/SecondarySchools/Teachers.tsx) | Several browser prompts | Structured class/subject selection and a normal validated edit form are missing; users enter identifiers for assignments |

Several actions submit old values for fields the user cannot change. Browser prompts also make loading, field validation, cancellation, and password handling harder to communicate.

**Required work:** replace prompts with prefilled page or dialog forms matching backend validation, searchable assignment controls, saving states, inline errors, and success feedback. Verify both legacy and owner-specific paths.

### 5. Medium — Institution deactivation exists only in the backend

[InstitutionController.php:114](../app/Http/Controllers/InstitutionController.php#L114) and web.php expose deactivation. Neither Institutions/Index nor Institutions/Show provides that action. The index offers View/Manage structure; Show offers Edit.

**Required work:** add a permission-aware Deactivate/Reactivate or explicit status-edit path. Do not add hard deletion merely to make the screen resemble other CRUD lists.

### 6. Medium — Owner lifecycle actions are inconsistent

Organizations, legacy schools, and legacy centers have explicit Deactivate actions. Secondary schools, professional schools, and CBT centers have editable status fields but no dedicated destroy action in their controllers/routes.

Their edit flows exist:
- [SecondarySchools/Form.tsx](../resources/js/Pages/SecondarySchools/Form.tsx)
- [ProfessionalSchools/Form.tsx](../resources/js/Pages/ProfessionalSchools/Form.tsx)
- [CbtCenters/Form.tsx](../resources/js/Pages/CbtCenters/Form.tsx)

**Assessment:** deletion is absent, but retirement through status editing is available. This is a product consistency gap; hard deletion is not automatically the right replacement.

**Required work:** standardize visible lifecycle actions and explain the effect on new assignments, logins, existing exams, and historical results. Test enforcement separately; the presence of a status field alone does not prove all downstream access behavior.

### 7. High — Existing assessment deletion can remove historical evidence

[ExamController.php:125](../app/Http/Controllers/ExamController.php#L125) normally blocks deleting exams with attempts, but [canPurgeAttemptedAssessment](../app/Http/Controllers/ExamController.php#L196) allows an assessment-specific exception for teaching roles after the normal delete authorization.

[purgeAssessmentWithAttempts](../app/Http/Controllers/ExamController.php#L203) explicitly deletes candidate papers, answers, proctoring events, audit logs, certificates where present, attempts, and other result-related rows, then force-deletes the exam. The normal UI confirmation simply asks whether to delete the assessment/exam.

**Impact:** deletion support is present here, but it is considerably more destructive than a user may expect. The predicate does not separately exclude adaptive mode. Interaction with adaptive foreign keys/history needs regression verification; this audit did not execute a purge.

**Required work:** define the intended retention policy before expanding delete support. Prefer archive/cancel for attempted assessments. If permanent purge remains an authorized feature, make its scope explicit, retain an independent audit record, and test both traditional and adaptive relationships.

## Coverage matrix

“Present” means source wiring exists. “Lifecycle” means deactivate/status change instead of record deletion. “Restricted” indicates domain/history checks or a deliberately non-CRUD workflow.

| Management area | Edit | Delete/lifecycle | Assessment |
| --- | --- | --- | --- |
| Organizations | Present | Deactivate | Implemented; role-dependent |
| Institutions | Broken page target | Deactivate endpoint lacks UI | Findings 1 and 5 |
| Institution faculties | Inline form | Delete | Present |
| Institution departments | Inline form | Delete | Present |
| Institution programmes | Inline form | Delete | Present |
| Institution courses | Inline form | Delete | Present |
| Institution lecturers | Dedicated edit page | Delete | Present |
| Secondary schools | Dedicated edit page | Status edit; no destroy | Lifecycle inconsistency |
| Secondary academic sessions | Partial prompt | Delete / set active | Improve edit form |
| Secondary terms | Partial prompt | Delete | Improve edit form |
| Secondary classes | Partial prompt | Delete | Improve edit form |
| Secondary class arms | Missing page | Legacy endpoints; owner routes absent | Finding 3 |
| Secondary students | Partial prompt | Delete | Improve edit form |
| Secondary student groups | Partial prompt + membership editor | Delete | Improve edit form |
| Secondary teachers | Prompt-based | Delete | Improve assignment/password UX |
| Professional schools | Dedicated edit page | Status edit; no destroy | Lifecycle inconsistency |
| Professional programmes | Missing | Missing | Create-only |
| Professional courses | Missing | Missing | Create-only |
| Professional modules | Missing | Missing | Create-only |
| Professional training batches | Inline form | Delete, disabled when candidates exist | Present with restriction |
| Professional facilitators | Dedicated edit page | Delete | Present |
| Professional candidates | Links to shared candidate editor | Shared candidate delete | Present in source |
| Professional question banks/questions | Links to shared editors | Shared delete actions | Present in source |
| CBT centers | Dedicated edit page | Status edit; no destroy | Lifecycle inconsistency |
| CBT-center candidates/question banks | Shared edit links | Shared delete actions | Present in source |
| Legacy schools / centers | Dedicated edit pages | Deactivate | Present |
| Users | Inline editor, status and password controls | Delete | Present |
| Subjects / topics | Dedicated edit pages | Delete | Present |
| Question banks / questions | Dedicated edit pages | Delete | Present; history behavior merits review |
| Candidates | Dedicated edit page | Delete | Present; permission scoped |
| Candidate groups | Inline editor and membership | Delete | Present |
| Candidate exam assignments | Assignment workflow | Unassign endpoint | Workflow action, not record editing |
| Exams / assessments | Dedicated editor | Cancel / delete with exception above | Present; review purge behavior |
| Exam supervisors | Add/role assignment workflow | Remove | Present workflow |
| Pricing plans / app releases | Inline edit | Delete | Present |
| Admin registration requests | Edit | Reject/deactivate | Lifecycle workflow |
| Certificate templates | Template editing | Delete | Present; distinct from issued certificates |
| Recruitment / certification settings | Settings update | No generic record delete expected | Workflow screens |
| Access controls / profile | Update; profile includes account deletion | Domain-specific | Not general CRUD |
| Offline activation | Issue/reset/device removal | Domain-specific | Not ordinary editable exam content |
| Adaptive preparation/research/packages | Version/state transitions | Preserve issued/frozen history | Do not add unrestricted CRUD |
| Results, monitoring, incident reports, issued certificates | Review/release/operational actions | No blanket edit/delete expected | Preserve evidence and controlled corrections |
| Public/auth/candidate examination screens | Task-specific interactions | Not admin CRUD | Outside missing-management-action count |

Other unfinished screens: web.php still maps /settings, /reports, /supervisor-monitor, and /candidate-activity to Portal/Placeholder. These are incomplete entry points, not proof that dedicated exam monitoring and result-reporting screens are absent.

## Cross-cutting observations

- An Edit.tsx filename is not required when a screen has a working inline editor. Institutions' missing pages were confirmed from actual render targets, not guessed from naming.
- Some actions live in dropdown menus or only on detail pages. Use consistent placement and labels.
- Core questions/question-bank lists consume per-record capabilities, while some owner-specific lists show shared actions without equivalent per-row capability flags. Align these props so unauthorized actions are explained or hidden; backend authorization must remain authoritative. Visibility differences alone are not evidence of an authorization bypass.
- Several delete actions use browser confirmation only. Dependency failures should explain which linked records prevent deletion and what alternative is available.
- Soft deletion is not by itself a full history-retention guarantee. For example, QuestionController removes an image file during deletion; policies and historical rendering should be checked before broadening question deletion.
- Existing test files include InstitutionStructureFeatureTest, SecondarySchoolFeatureTest, ProfessionalFacilitatorManagementTest, QuestionModuleTest, CandidateModuleTest, and related modules. Their presence does not establish that the actual browser edit form exposes every field or that missing Inertia components load.

## Recommended implementation order

1. Restore institution Create/Edit and the missing class-arms page/routes. Add browser checks for every linked Inertia target.
2. Implement professional programme/course/module edit and safe removal/archive flows.
3. Replace partial secondary-school prompt editing with complete validated forms.
4. Standardize owner lifecycle controls, permission props, and explanations for blocked actions.
5. Review attempted-assessment purge and linked-record retention before broadening deletion.
6. Verify the resulting screens across all five owner contexts, including allowed/denied roles and dependent records.

For each corrected module, verify: opening the editor; prefilled values; updating all supported fields; validation errors; cross-owner denial; cancellation; double submission; safe unused-record deletion; linked-record rejection; history preservation; and clear success/error feedback.

No implementation changes were made as part of this report.


## Implementation update — 9 September 2026

The findings above describe the original audit. The following changes have now been implemented:

- Restored institution creation and editing pages.
- Added professional programme, course and module editing/deletion, owner-scoped validation, and protection against reparenting records with dependants.
- Restored class-arm management for modern and legacy secondary-school routes.
- Replaced partial secondary-school prompt editors with prefilled forms for sessions, terms, classes, students, groups and teachers, including dates, membership and assignments.
- Added explicit deactivate/reactivate controls for institutions, secondary schools, professional schools and CBT centers. Organizations retain their existing lifecycle controls.
- Added visible dependency errors and navigation links to topics and class arms.
- Removed the teaching-role exception that purged attempted assessments and their examination history.

### Deletion and historical integrity

Management deletion now uses RecordDeletionService for the audited deletion endpoints. It locks the selected row in a transaction and checks incoming database foreign keys, including soft-deleted dependants. Candidate/student polymorphic exam assignments are checked explicitly. Referenced records cannot be deleted; administrators must retain them, deactivate them where supported, or reassign eligible unused dependants first.

Unused records retain their model's existing soft/hard deletion behavior. Questions are soft-deleted with their options and image files retained. Attempted examinations, answers and dependent historical records are protected from the former assessment purge.

This is deliberately conservative: a draft with child records can also be blocked. It is not an automatic cascade-delete or historical-data cleanup feature. Existing orphan records were not scanned or repaired, and no production records were deleted. No database migration is required.

### Verification

Feature coverage includes authorization, cross-owner denial, professional structure updates, dependency rejection, retained soft-deleted references, owner status changes and question option retention. Secondary administration and adaptive lifecycle regression tests have also been exercised. Browser workflows cover institution creation/edit/status, professional structure editing/deletion, and secondary session dates/class-arm navigation.

The report does not certify unrelated placeholder modules, every role/browser combination, offline applications, or production MySQL concurrency. Runtime verification here uses isolated SQLite test databases.

### Final check results

- Final management regression selection: 36 tests passed, 416 assertions.
- Secondary/question/adaptive follow-up selection: 52 tests passed, 429 assertions (overlaps the management selection).
- Browser workflows: institution and secondary tests passed together; professional workflow passed on its final rerun after correcting its dropdown selector. All three workflows have passing results.
- Production build succeeded; changed PHP files were formatted; source whitespace checks passed.


## Bulk question status updates

Open a question bank and choose **Manage question statuses**, or open **Questions** and use **Filter by bank**. Tick individual question checkboxes or **Select all in this bank**, choose Draft, Review, Approved, Rejected or Archived, then click **Apply status**.

Selecting all applies to every editable question currently listed for the chosen bank (the list is not paginated). With **All banks** selected, it applies across the listed banks. Changing the bank filter clears the selection. The selection count shows the scope before applying. Successful updates clear the selection and report how many statuses actually changed.

The server validates the status and every selected ID, authorizes each question using the existing update policy, and applies the batch in one transaction. Missing, deleted or unauthorized questions prevent the entire batch from changing. Each changed status is logged with the question, bank, actor and previous/new status. Only status is changed; question content, options and exam records are not edited or deleted. This follows the existing question editing permissions and does not introduce a separate reviewer role or re-score completed examinations.

Bulk status verification: 8 backend tests passed (85 assertions), the bank select-all/individual-update/reload browser test passed, and the production build succeeded.

## Record filters and question import — 10 September 2026

Search and cascading filters are available on professional programmes, courses, modules, question banks and questions, the shared question-bank/question lists, and institution programme/course lists. Filters appear when the loaded records contain the relevant programme, course, module, subject, bank, status or difficulty. Search matches names, codes, descriptions and question text. Clear filters restores the list; the displayed count shows matching versus loaded records. Empty results retain the filter controls.

The question list's bulk selection acts on matching editable questions. The list filters operate on the existing owner-scoped Inertia records; this change does not add database pagination or expand permissions.

For question import:
1. Select a course for institution/professional banks, or a subject for subject-based banks.
2. Optionally narrow professional course banks to a module.
3. Select the matching question bank, choose a CSV and upload.

Changing course/subject clears module and bank choices; changing module clears the bank. The upload button requires a bank and file. The global form derives the subject from the chosen bank and retains optional topic selection where applicable. Both global and professional-school import routes retain their server-side owner/assignment checks.

Verified with 14 backend regression tests and an end-to-end browser test covering hierarchy filtering, filtered bulk selection, stale-selection clearing, and real CSV imports into the chosen banks on both screens. Production and browser builds passed. No records were deleted or reassigned.

### Upload course visibility correction

The import picker now receives active, authorized courses and modules independently of question banks. Previously it inferred the hierarchy from banks, and the professional upload page included only Active banks. This hid active courses whose banks were Draft or absent.

Professional import now includes Active and Draft banks. A bankless course/module remains selectable and displays a message explaining that a matching bank must be created or access checked. No bank is automatically created, activated or approved. Owner and facilitator assignment scoping remains enforced.

Verification: 12 regression tests passed together, and the assignment/owner-isolation regression passed on its final rerun after repairing its test fixture (13 passing checks total). The browser test covered bankless hierarchy on both import pages and a real CSV import into a Draft bank. Production and browser builds passed.

### Management filter visibility correction ? 10 September 2026

Management filters now combine the loaded records with independent, authorized hierarchy choices. Empty programmes, courses, modules and question banks remain selectable; a selection with no children displays zero matching records and keeps the filter controls available. Draft records are included by default, and status choices remain available when another filter produces no matches.

- Professional structure and bank screens use their complete scoped parent lists. Institution course filters include programmes without courses.
- Shared bank filters receive independent programmes, courses, modules and subjects. Both question screens include accessible banks even when those banks contain no questions.
- Upload course/module choices include inactive records, and the professional bank selector includes Draft, Active and Archived banks. These are management choices; examination question eligibility and approval requirements are unchanged.
- Shared question rows expose course/module IDs for reliable filtering, including when different records have the same name.
- Ownership, context and facilitator-assignment restrictions remain in force. Clearing filters restores the supplied list; soft-deleted records are not restored.
- Filtering remains client-side over the supplied scoped records; this change does not introduce server pagination.

Validation: the initial 14 backend regression tests and the final 15 professional/institution/question-bank checks passed. The browser regression passed across hierarchy lists, empty/inactive selections, empty Draft banks, Draft questions, bulk selection and both CSV upload flows. Production and browser assets were rebuilt.
