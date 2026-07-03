# Deployment Checklist

## Before Deploy

- Confirm `.env` points to the intended MySQL database.
- Run migrations in a staging environment first.
- Confirm queues, cache, Redis, Reverb, and mail settings.
- Confirm file storage permissions.
- Confirm Vite assets build successfully.

## Commands

```bash
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
npm run build
php artisan test
```

## Security

- Confirm `APP_DEBUG=false`.
- Confirm candidate API responses hide answer keys.
- Confirm admin routes require authenticated portal users.
- Confirm candidates cannot access `/dashboard`.
- Confirm supervisors and examiners cannot access unrelated tenants.
- Rotate credentials after staging imports.

## Operational Checks

- Log in as super admin.
- Switch through organization, secondary school, professional school, and CBT center contexts.
- Create one exam per context.
- Generate candidate papers.
- Complete a candidate exam using `/exam/login`.
- Verify result calculation and audit logs.
- Verify certificates for certification exams.

## Rollback Notes

- Backup database before migration.
- Keep previous build assets available.
- Use `php artisan down` only during planned maintenance windows.
- Do not roll back migrations containing live exam attempts without a data recovery plan.
