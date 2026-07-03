# User Role and Context Guide

## Contexts

Current context controls dashboard metrics, sidebar modules, quick actions, and terminology.

Supported context types:

- `organization`
- `secondary_school`
- `professional_school`
- `cbt_center`

Users with one context are auto-selected. Users with multiple contexts can switch from the topbar. Super admins can choose from broad platform contexts.

## Shared Inertia Props

Every Inertia admin page receives:

- `auth.user`
- `auth.role`
- `auth.permissions`
- `auth.navigation`
- `auth.current_context`
- `auth.available_contexts`
- `current_context`
- `available_contexts`

## Role Expectations

- Super Admin: global platform access.
- Organization Admin: organization and child contexts.
- Secondary School Admin: own secondary school only.
- Professional School Admin: own professional school only.
- CBT Center Admin: own CBT center only.
- Examiner: exam/question-bank workflows in assigned scope.
- Supervisor: monitoring and reports in assigned scope.
- Candidate: candidate exam interface only.

## Sidebar by Context

Organization:

- Dashboard, Candidates, Question Bank, Exams, Recruitment Exams, Assessment Exams, Certification Exams, Adaptive Exams, Results, Reports, Users, Settings.

Secondary school:

- Dashboard, Academic Sessions, Terms, Classes, Arms / Sections, Students, Subjects, Topics, Question Bank, Terminal Exams, Continuous Assessment, Results, Report Cards, Reports, Settings.

Professional school:

- Dashboard, Programmes, Courses, Modules, Training Batches, Candidates / Trainees, Question Bank, Traditional Exams, Adaptive Exams, Certification Exams, Results, Certificates, Reports, Settings.

CBT center:

- Dashboard, Candidates, Question Bank, Exams, Traditional CBT Exams, Adaptive CBT Exams, Results, Reports, Center Settings.

## Terminology

Use `getContextTerminology(contextType)` from `resources/js/lib/terminology.ts` for learner labels, exam labels, question structure labels, and result document labels.
