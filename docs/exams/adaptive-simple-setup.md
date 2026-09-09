# Setting up an adaptive exam

1. Create an exam and select **Adaptive** with the **Assessment** or **Practice** category.
2. Choose subjects, question banks, candidates, dates and duration as usual.
3. Under **Adaptive learning**, choose whether candidates can take further levels. New exams default to three levels and a 10% deduction from the remaining marks at each additional level.
4. Adjust the number of levels, deduction, area completion score, time per level and closing date if needed. Leave **Show candidates their level marks, strengths and areas to improve** selected to provide feedback after each level.
5. Save. Question preparation happens automatically. If there are too few approved questions, add the requested questions before opening the exam. A draft can be saved while questions are still being prepared.

There is no separate Generate Papers step for adaptive exams. Candidate login prepares Level 1 automatically. If an older setup generated a fixed paper, the system can replace it only while the attempt is unstarted and has no saved answers; this repair is recorded in the audit log. Started attempts and saved answers are preserved.

Candidates begin at Level 1. Questions adjust as they answer. Use **Confirm and continue** to save each answer and move forward. On the last question, this becomes **Finish level**, which saves the final answer and completes the level. The finish button is hidden on earlier questions; the server can still end a level when time expires. After finishing a level, candidates see their marks, strengths and areas to improve. **Start next level** appears when further attempts are allowed, marks and time remain, and weaker areas need more practice. Each further level uses fresh questions from those areas. Select **Start next level**, review the deduction in the on-page confirmation, then select **Begin next level**. Cancel keeps the completed level unchanged. After an application update, reload the browser page to load the latest interface; **Refresh exam state** updates exam data only.

For example, a candidate with 60 marks remaining and a 10% deduction has 54 marks available for the next level. Previously earned marks are kept. The exam stops offering further levels when its configured limits are reached.

For offline or hybrid delivery, use **Center delivery** on the exam page to assign a package to the center and candidates. The center supervisor starts the local session when ready.

Existing completed attempts keep their original records. A completed traditional attempt cannot acquire adaptive level history retrospectively; create a fresh adaptive exam to test the new experience. Secondary-school terminal exams remain traditional.


The **Your improvement** chart shows cumulative earned marks after each completed level. Level 1 provides the starting point; subsequent levels show marks gained through recovery. The chart uses the original exam total as its scale and appears only when candidate level feedback is enabled.


Planned next version: [document-based question generation and adaptive recovery](adaptive-resource-generation-plan.md). This proposal covers uploaded learning resources, generated questions, weakness-focused subsequent levels, and online/offline delivery.
