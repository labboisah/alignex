# Cross-department exam audiences

Institution exam creation now lists active candidate groups across the authorized institution, with group codes and department names. Multiple groups may be selected even when their departments differ from the selected course's department.

The first course continues to determine the exam's academic ownership. Selected groups determine the candidate audience, with duplicate candidates assigned once. Participant refresh applies the same institution-wide audience rule. Institution lecturers remain restricted to their assigned courses. Groups and members from another institution are rejected, including institutions sharing an organization.

Organization and CBT center exams retain their multi-group audience workflow, independent of paper subjects, with existing organization/center scope checks. Secondary school student-group and professional training-batch workflows keep their existing context rules.

No migration or offline-server version change is required.

## Verification

- InstitutionAssessmentFeatureTest and SharedExamWorkflowTest: 9 tests passed, 89 assertions. Includes cross-department groups, department labels, deduplication, participant refresh, foreign group/member rejection, and organization multi-group assignment.
- Production Vite build passed; patch whitespace checks passed.
- CbtCenterFeatureTest: 4 passed and 2 existing exam-creation failures. Running the same suite with the original ExamController and ExamParticipantAssignmentService loaded from Git produced the identical failures, confirming they are unrelated to this change.
- The older institution creation test fixture now creates a draft, matching the existing draft-first readiness requirement.

The group picker uses individually labelled checkboxes and a department filter (All departments, departments with available groups, and No department assigned). Switching filters preserves all selections; a count identifies selections outside the current filter. Filter changes do not change the exam department.
