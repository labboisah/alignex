# AlignEx adaptive research engine

This authenticated FastAPI service runs experimental shadow evaluations only. It cannot issue candidate questions or change scores.

See [Phase 6 setup, calibration format, validation runner and acceptance gates](../../docs/exams/adaptive-phase-6.md).

Install the pinned `requirements.lock` in a local virtual environment, set a server-only `ADAPTIVE_ENGINE_SECRET` of at least 32 characters, and run `python -m uvicorn app:app --host 127.0.0.1 --port 8096 --no-access-log`. Laravel shadow requests remain disabled by default.

Run `python -m pytest -q`. `validate_dataset.py` evaluates supplied reference cases; it never approves consequential scoring.
