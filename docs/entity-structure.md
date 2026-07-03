# Entity Structure

## Core Entities

AlignEx uses four separate operating contexts:

- `organization`
- `secondary_school`
- `professional_school`
- `cbt_center`

Organization is not a school and does not have `school_type`. Secondary schools, professional schools, and CBT centers are separate entities that may belong to an organization.

## Organization

An organization represents an NGO, company, recruitment body, association, government unit, or certification owner.

It can directly manage:

- Candidates
- Subjects and topics
- Question banks
- Recruitment exams
- Assessment exams
- Certification exams
- Professional/practice/general exams
- Traditional and adaptive delivery modes
- Results and reports

Organization exams require a question bank and assigned candidates.

## Secondary School

A secondary school owns academic structures:

- Academic sessions
- Terms
- Classes
- Arms or sections
- Student groups
- Students
- Subjects and topics

Secondary school exams are terminal exams only. They require academic session, term, class, and subject, and they must use traditional mode. Adaptive, recruitment, and professional programme/course/module workflows are rejected.

## Professional School

A professional school owns training structures:

- Programmes
- Courses
- Modules
- Training batches
- Candidates or trainees
- Question banks
- Certificates

Professional schools can create professional, certification, and practice exams. Traditional and adaptive modes are allowed. Academic session, term, and class fields are rejected.

## CBT Center

A CBT center is a delivery and exam context with:

- Candidates
- Question banks
- Traditional CBT exams
- Adaptive CBT exams
- Capacity and center settings

CBT center exams require a center question bank and center candidates. They do not require school or professional academic structures.

## Exam Ownership

Exam ownership is stored with:

- `exam_owner_type`
- `exam_owner_id`
- Context foreign keys such as `organization_id`, `secondary_school_id`, `professional_school_id`, and `cbt_center_id`

Allowed ownership rules:

- Organization: recruitment, assessment, certification, professional, practice, general; traditional or adaptive.
- Secondary school: terminal only; traditional only.
- Professional school: professional, certification, practice; traditional or adaptive.
- CBT center: recruitment, assessment, certification, professional, practice, general; traditional or adaptive.
