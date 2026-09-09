# Setting up an adaptive exam

1. Create an exam and select **Adaptive** with the **Assessment** or **Practice** category.
2. Choose subjects, question banks, candidates, dates and duration as usual.
3. Under **Adaptive learning**, choose whether candidates can take further levels. New exams default to three levels and a 10% reduction in marks per question at each additional level.
4. Adjust the number of levels, deduction, area completion score, time per level and closing date if needed. Leave **Show candidates their level marks, strengths and areas to improve** selected to provide feedback after each level.
5. Save. Question preparation happens automatically. If there are too few approved questions, add the requested questions before opening the exam. A draft can be saved while questions are still being prepared.

There is no separate Generate Papers step for adaptive exams. Candidate login prepares Level 1 automatically. If an older setup generated a fixed paper, the system can replace it only while the attempt is unstarted and has no saved answers; this repair is recorded in the audit log. Started attempts and saved answers are preserved.

Candidates begin at Level 1. Questions adjust as they answer. Use **Confirm and continue** to save each answer and move forward. On the last question, this becomes **Finish level**, which saves the final answer and completes the level. The finish button is hidden on earlier questions; the server can still end a level when time expires. After finishing a level, candidates see their marks, strengths and areas to improve. **Start next level** appears when further attempts are allowed, marks and time remain, and weaker areas need more practice. Each further level uses fresh questions from those areas. Select **Start next level**, review the deduction in the on-page confirmation, then select **Begin next level**. Cancel keeps the completed level unchanged. After an application update, reload the browser page to load the latest interface; **Refresh exam state** updates exam data only.

The next question count equals incorrect plus unanswered questions, without a percentage deduction. For example, 21 unresolved questions previously worth 2 marks each remain 21 questions at 1.8 marks each with a 10% mark reduction: 37.80 available marks. For 28 questions with 7 correct, then 10 correct, then 5 correct, the counts are 28 → 21 → 11 → 6. Previously earned marks are kept; configured mastery, level, budget and time limits still apply.

For offline or hybrid delivery, use **Center delivery** on the exam page to assign a package to the center and candidates. The center supervisor starts the local session when ready.

Existing completed attempts keep their original records. A completed traditional attempt cannot acquire adaptive level history retrospectively; create a fresh adaptive exam to test the new experience. Secondary-school terminal exams remain traditional.


The **Your improvement** chart shows cumulative earned marks after each completed level. Level 1 provides the starting point; subsequent levels show marks gained through recovery. The chart uses the original exam total as its scale and appears only when candidate level feedback is enabled.


Planned next version: [document-based question generation and adaptive recovery](adaptive-resource-generation-plan.md). This proposal covers uploaded learning resources, generated questions, weakness-focused subsequent levels, and online/offline delivery.


## Professional-school sections sharing a subject

Adaptive paper rows may use the same subject mapping for different module/question-bank pools. Each row retains its own ID, bank selection, course/module mapping, question quota and marks; adaptive preparation assigns each row a separate area. Pools must still be disjoint and pass the existing readiness checks.

Migration `2026_09_09_190000_allow_adaptive_sections_to_share_subjects.php` replaces the old unique exam/subject index with a regular index. It preserves rows and foreign keys. Its rollback refuses to restore uniqueness while duplicate subject mappings exist, rather than deleting sections.

Traditional exam creation and editing reject repeated nonempty subject IDs with a validation message; multiple banks for one traditional subject belong in one paper row.

Verification: 13 professional/adaptive tests passed (127 assertions), including adaptive creation/editing with two module banks sharing a subject and rejection of duplicate traditional rows. The targeted migration was applied to the local MySQL database.

Recovery quota update verified: 33 adaptive lifecycle/learning/quota tests and 12 reporting tests passed. The candidate browser workflow verified the next-level preview, shorter level and improvement chart. Production and browser builds passed. Already-started levels keep their frozen plan; subsequent levels use unresolved counts and a percentage reduction in marks per question only.
