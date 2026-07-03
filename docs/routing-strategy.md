# Routing Strategy

AlignEx uses hybrid routing.

## Public and Admin Routing

Public and admin pages must use Laravel web routes and Inertia React pages.

Examples:

- `/`
- `/login`
- `/dashboard`
- `/organizations`
- `/secondary-schools`
- `/professional-schools`
- `/cbt-centers`
- `/users`
- `/subjects`
- `/topics`
- `/question-banks`
- `/questions`
- `/exams`
- `/candidates`
- `/results`
- `/reports`
- `/settings`
- `/exams/{exam}/monitor`

Rules:

- Define routes in `routes/web.php`.
- Use controllers returning `Inertia::render()`.
- Use Inertia props for initial page data.
- Use Laravel middleware and policies for authorization.
- Do not use React Router for admin or public CRUD pages.
- Dashboard, organization, secondary school, professional school, CBT center, exam, result, and report screens are all Laravel web routes.

## Candidate Exam Routing

Laravel serves the candidate app:

```php
Route::get('/exam/{any?}', function () {
    return Inertia::render('CandidateExam/App');
})->where('any', '.*')->name('candidate.exam');
```

React Router is allowed only inside `resources/js/Pages/CandidateExam/App.tsx`.

Allowed candidate routes:

- `/exam/login`
- `/exam/instructions`
- `/exam/write`
- `/exam/submitted`
- `/exam/error`
- `/exam/disqualified`

## API Routing

Use Laravel API routes for asynchronous operations and candidate actions.

Candidate exam actions:

- `POST /api/candidate/login`
- `GET /api/candidate/exam`
- `POST /api/candidate/answer`
- `POST /api/candidate/submit`
- `POST /api/candidate/auto-submit`
- `POST /api/candidate/event`

Admin async actions may use API routes for:

- Imports
- Exports
- Live monitoring
- Answer saving
- Proctoring events
- Long-running jobs
- Background validation

## Boundary Tests

Routing tests should prove:

- Admin pages are available through Laravel routes.
- Admin pages render Inertia components.
- Candidate app is served only through `/exam/{any?}`.
- React Router routes are not registered as separate Laravel admin pages.
- Candidate API endpoints require the correct candidate session authorization.

## Audit Result

The routing audit command:

```bash
rg "react-router|BrowserRouter|createBrowserRouter|useNavigate|<Routes" resources/js
```

should return only `resources/js/Pages/CandidateExam/App.tsx`. Any React Router usage in admin pages is a routing violation.
