# Runtime Activation

The Electron build is generic. Client-specific values are created after installation by the first-launch activation flow.

## Startup States

- `not_configured`: no local config/license exists; show setup wizard.
- `activated`: license exists, is valid, and has not expired; show local admin login, then dashboard.
- `expired`: license expiry has passed; show license status.
- `revoked`: license was revoked; show license status.
- `maintenance`: app is locked for maintenance.
- `invalid`: local config file is missing or corrupt; show setup recovery.

## Local Storage

- SQLite data: configured by `OFFLINE_STORAGE_PATH`, default `./data/offline.sqlite`.
- Runtime config file: `center-config.json` beside the SQLite database.
- Device identity: stored in `app_settings.device_id`.
- Local users: stored in `local_users`.
- License records: stored in `license_activations`.

## Activation

The app uses a single activation screen. Enter the same portal URL that works in a browser from the center machine, including the port when Laravel is served with `php artisan serve --host=0.0.0.0 --port=8000`.

```text
Portal URL example: http://192.168.1.20:8000
Activation endpoint used by the app: http://192.168.1.20:8000/api/offline/activate
```

Payload:

```json
{
  "portal_url": "http://192.168.1.20:8000",
  "activation_code": "CODE",
  "device_id": "alignex-device-id",
  "admin_email": "admin@example.com",
  "admin_password": "platform-password"
}
```

If the portal does not return `expires_at`, the offline app creates a one-year license.
