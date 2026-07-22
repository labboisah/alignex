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

## Offline Server Download Deployment

The offline server package is intentionally not committed to Git because it is a compiled release artifact. After deployment, make one of these available on the production server:

- Upload an active `server` release from **App Releases**.
- Or copy the compiled ZIP to `public/downloads/offline-server/`.

The download route accepts `AlignEx-Center-Server-win-unpacked.zip` and versioned files such as `AlignEx-Center-Server-1.0.0.zip`. If this folder is missing, `/offline-server/download` will return `404 Offline server package has not been compiled yet.`
