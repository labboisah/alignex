# AlignEx

Align Examination is a platform for recruitment, certification, secondary terminal exams, and professional examinations.

## Candidate Client

The Windows candidate desktop app lives in `offline-candidate-browser/`.

```bash
cd offline-candidate-browser
npm install
npm run dev
npm run build
npm run dist
```

`npm run dist` creates the NSIS installer in `offline-candidate-browser/dist-release/` as `AlignEx-Candidate-Client-Setup-<version>.exe`.

Configure the Center Server from the client setup screen, or set `VITE_CANDIDATE_SERVER_URL` before building to provide a production fallback URL. Full development, installation, and server configuration notes are in `offline-candidate-browser/README.md`.
