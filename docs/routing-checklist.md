# Routing Checklist

## Required Boundary

- Public pages use Laravel routes in `routes/web.php` and render Inertia pages.
- Admin pages use Laravel routes in `routes/web.php` and render Inertia pages.
- Admin CRUD pages must not use React Router.
- React Router is allowed only in `resources/js/Pages/CandidateExam/App.tsx`.
- Candidate screen routes live under `/exam/*`.
- Candidate exam actions live in `routes/api.php`.

## Candidate Screens

Laravel serves the candidate app with:

```php
Route::get('/exam/{any?}', function () {
    return Inertia::render('CandidateExam/App');
})->where('any', '.*')->name('candidate.exam');
```

Allowed React Router paths:

- `/exam/login`
- `/exam/instructions`
- `/exam/write`
- `/exam/submitted`
- `/exam/error`
- `/exam/disqualified`

## Candidate API Routes

- `POST /api/candidate/login`
- `GET /api/candidate/exam`
- `POST /api/candidate/answer`
- `POST /api/candidate/submit`
- `POST /api/candidate/auto-submit`
- `POST /api/candidate/event`

## Admin Web Routes

These must remain Laravel web routes with Inertia responses:

- `/dashboard`
- `/organizations`
- `/secondary-schools`
- `/professional-schools`
- `/cbt-centers`
- `/subjects`
- `/topics`
- `/question-bank`
- `/questions`
- `/exams`
- `/candidates`
- `/results`
- `/reports`
- `/settings`

## QA Command

```bash
rg "react-router|BrowserRouter|createBrowserRouter|useNavigate|<Routes" resources/js
php artisan route:list
```

Expected result: React Router references appear only in `resources/js/Pages/CandidateExam/App.tsx`.
