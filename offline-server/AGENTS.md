# Offline Server Rules

This app is separate from the main Laravel/Inertia AlignEx portal.

- Keep offline runtime code inside `offline-server`.
- Do not add offline server routes to Laravel web routes unless creating online package export/import endpoints.
- Use SQLite for local storage.
- Keep contracts explicit and versioned.
- Candidate APIs must never return answer keys, correctness flags, scoring rubrics, or hidden moderation data.
- Local server state is authoritative while offline for timing, autosave, submission status, incidents, and disqualification.
- Main AlignEx remains authoritative for final scoring, review, result release, certificates, and institutional records.
- Every offline-sensitive action must be logged for sync back to AlignEx.
