# AlignEx

Align Examination is a platform for recruitment, certification, secondary terminal exams, and professional examinations.

## Desktop Apps

The desktop apps live beside this platform repository:

- Offline server: `C:\laragon\www\offline-server`
- Candidate app: `C:\laragon\www\candidate-app`

Set `ALIGNEX_OFFLINE_SERVER_PATH` and `ALIGNEX_CANDIDATE_APP_PATH` in `.env` only if those folders are moved somewhere else.

## Candidate App

```bash
cd C:\laragon\www\candidate-app
npm install
npm run dev
npm run build
npm run dist
```

`npm run dist` creates the NSIS installer in `C:\laragon\www\candidate-app\dist-release\` as `AlignEx-Client-App-Setup-<version>.exe`.

Configure the Center Server from the client setup screen, or set `VITE_CANDIDATE_SERVER_URL` before building to provide a production fallback URL. Full development, installation, and server configuration notes are in `C:\laragon\www\candidate-app\README.md`.
