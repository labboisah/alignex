# Exam Package Format

The Center Server App MVP imports exam packages as JSON files. Import processing is not implemented yet, but this format defines the contract that the online AlignEx portal will export and the offline center server will later validate.

## File Type

```text
.json
```

## Top-Level Structure

```json
{
  "manifest": {},
  "subjects": [],
  "questions": [],
  "options": [],
  "candidates": []
}
```

## Manifest

The manifest describes the exam package and gives the offline server enough information to preview and validate the package before import.

| Field | Type | Description |
| --- | --- | --- |
| `package_id` | string UUID | Unique id for this exported package. |
| `exam_id` | string UUID | Online AlignEx exam id. |
| `exam_code` | string | Human-readable exam code. |
| `title` | string | Exam title. |
| `organization_name` | string | School, professional institution, organization, or CBT center name. |
| `center_id` | string UUID | Approved center id for this package. |
| `start_at` | ISO datetime string | Scheduled start time. |
| `end_at` | ISO datetime string | Scheduled end time. |
| `duration_minutes` | number | Candidate exam duration in minutes. |
| `total_questions` | number | Number of questions included. |
| `candidate_count` | number | Number of candidates included. |
| `shuffle_questions` | boolean | Whether the offline exam should shuffle question order. |
| `shuffle_options` | boolean | Whether the offline exam should shuffle option order. |

## Subjects

Subjects group questions for secondary school exams or subject-based center exams.

```json
{
  "id": "subj-uuid",
  "exam_id": "exam-uuid",
  "name": "Mathematics",
  "code": "MTH"
}
```

## Questions

Questions do not include correct answers, answer keys, scoring rubrics, or correctness flags.

```json
{
  "id": "question-uuid",
  "exam_id": "exam-uuid",
  "subject_id": "subj-uuid",
  "question_type": "single_choice",
  "body": "What is 2 + 2?",
  "marks": 1,
  "display_order": 1
}
```

Supported question types for the MVP:

- `single_choice`
- `multiple_choice`
- `short_answer`
- `essay`

## Options

Options belong to questions. They must not include correctness flags.

```json
{
  "id": "option-uuid",
  "question_id": "question-uuid",
  "option_label": "A",
  "body": "4",
  "display_order": 1
}
```

## Candidates

Candidate records include access code hashes, not plain access codes.

```json
{
  "id": "candidate-uuid",
  "exam_id": "exam-uuid",
  "candidate_no": "CAND-001",
  "full_name": "Ada Okafor",
  "access_code_hash": "$argon2id$hash-placeholder",
  "group_name": "Batch A"
}
```

## Complete Sample

```json
{
  "manifest": {
    "package_id": "pkg-2026-demo-001",
    "exam_id": "exam-2026-demo-001",
    "exam_code": "MTH-JSS3-001",
    "title": "JSS 3 Mathematics Mock Assessment",
    "organization_name": "AlignEx Demonstration School",
    "center_id": "center-demo-001",
    "start_at": "2026-07-20T09:00:00+01:00",
    "end_at": "2026-07-20T11:00:00+01:00",
    "duration_minutes": 90,
    "total_questions": 2,
    "candidate_count": 2,
    "shuffle_questions": true,
    "shuffle_options": true
  },
  "subjects": [
    {
      "id": "subject-mathematics",
      "exam_id": "exam-2026-demo-001",
      "name": "Mathematics",
      "code": "MTH"
    }
  ],
  "questions": [
    {
      "id": "question-001",
      "exam_id": "exam-2026-demo-001",
      "subject_id": "subject-mathematics",
      "question_type": "single_choice",
      "body": "What is 2 + 2?",
      "marks": 1,
      "display_order": 1
    },
    {
      "id": "question-002",
      "exam_id": "exam-2026-demo-001",
      "subject_id": "subject-mathematics",
      "question_type": "short_answer",
      "body": "Write the next prime number after 5.",
      "marks": 2,
      "display_order": 2
    }
  ],
  "options": [
    {
      "id": "option-001-a",
      "question_id": "question-001",
      "option_label": "A",
      "body": "3",
      "display_order": 1
    },
    {
      "id": "option-001-b",
      "question_id": "question-001",
      "option_label": "B",
      "body": "4",
      "display_order": 2
    }
  ],
  "candidates": [
    {
      "id": "candidate-001",
      "exam_id": "exam-2026-demo-001",
      "candidate_no": "CAND-001",
      "full_name": "Ada Okafor",
      "access_code_hash": "$argon2id$hash-placeholder-001",
      "group_name": "Batch A"
    },
    {
      "id": "candidate-002",
      "exam_id": "exam-2026-demo-001",
      "candidate_no": "CAND-002",
      "full_name": "Musa Bello",
      "access_code_hash": "$argon2id$hash-placeholder-002",
      "group_name": "Batch A"
    }
  ]
}
```

## Security Notes

- Do not include correct answers in the MVP package.
- Do not include scoring rubrics or correctness flags.
- Do not include plain candidate access codes.
- Future versions should add package signing, encryption, checksum verification, package expiration, and center binding before import is enabled.
