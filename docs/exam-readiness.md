# Exam readiness

Save new exams as Draft. Open the exam details page and use the readiness checklist to resolve setup issues. Generate traditional candidate papers, or complete adaptive question preparation, before returning to Edit and choosing Scheduled or Active.

Publication is validated on the server inside the exam save transaction. A failed check rolls back the save. Successful status changes to Scheduled or Active create an exam_published audit record.

Required checks cover positive question counts and marks, pass-mark bounds, an unexpired window that fits the exam duration, participant assignments, bank/difficulty availability, and complete traditional papers matching the current selection and scoring settings. Adaptive exams reuse the adaptive preparation checks. Existing scheduled exams are not automatically unpublished; the checks apply when saving them.

Camera and fullscreen requirements are displayed for planning. Actual device capability and permission checks still happen on the candidate device. Standard offline readiness verifies paper preparation, not whether a center downloaded the package; adaptive exams must use online delivery for the standard offline-app workflow.

Checks on the details page do not generate papers or synchronize assignments. Use the existing assignment refresh action when a group's membership changes.
