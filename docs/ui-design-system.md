# UI Design System

## Stack

- React + TypeScript
- Inertia React for public/admin pages
- React Router only in `/exam/*`
- Tailwind CSS
- shadcn/ui conventions with Radix UI primitives
- lucide-react icons
- Recharts for charts and reporting visuals

## Theme Colors

- Primary Green: `#0F7A3A`
- Dark Green: `#064E3B`
- Accent Orange: `#F59E0B`
- Brown Accent: `#7C4A21`
- Slate Dark: `#0F172A`
- Light Background: `#F8FAFC`
- Border: `#E2E8F0`
- Success: `#16A34A`
- Danger: `#DC2626`
- Info: `#2563EB`
- Warning: `#F59E0B`

## Product Feel

AlignEx is an operational CBT platform. Admin screens should be calm, dense, scannable, and efficient. Candidate exam screens should be focused, low-distraction, and resilient under stress.

Avoid marketing-heavy admin layouts. Prioritize:

- Clear navigation.
- Compact tables and filters.
- Strong status indicators.
- Accessible forms.
- Predictable actions.
- Plain language.
- Responsive layouts for tablets and laptops.

## Components

Use shadcn/ui-style components for:

- Buttons
- Inputs
- Selects
- Dialogs
- Dropdown menus
- Tabs
- Toast notifications
- Tables
- Badges
- Cards for individual records only

Use lucide-react icons in buttons and navigation where an icon improves recognition.

Use Recharts for:

- Result summaries
- Exam participation trends
- Candidate performance distribution
- Supervisor event counts
- Organization-level reports

## UI States

Every module UI should include:

- Loading state
- Error state
- Empty state
- Success state
- Toast feedback for user actions
- Validation feedback on forms
- Disabled/submitting state for write actions

## Candidate Exam UI Rules

- Keep the exam interface minimal and focused.
- Do not show correct answers, explanations, scores, or review state during the exam.
- Make autosave state visible but not distracting.
- Keep timer display clear.
- Confirm final submission.
- Clearly show submitted, error, and disqualified states.
- Avoid admin navigation or public layout inside the candidate exam area.

## Accessibility

- Maintain keyboard navigability.
- Use semantic buttons and form labels.
- Preserve visible focus states.
- Ensure contrast is adequate against the theme colors.
- Do not rely on color alone for status.
