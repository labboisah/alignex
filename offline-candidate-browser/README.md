# AlignEx Candidate Client

Electron desktop client for candidates writing offline CBT exams through an AlignEx Center Server on a local network.

## Development Setup

```bash
npm install
npm run dev
```

The development app starts Vite on `127.0.0.1:5175` and opens Electron against that renderer.

Create `.env` from `.env.example` when local defaults are needed:

```env
VITE_CANDIDATE_SERVER_URL=http://127.0.0.1:4080
VITE_DEFAULT_SERVER_URL=http://127.0.0.1:4080
VITE_ENABLE_EXAM_LOCKDOWN=false
VITE_USE_MOCK_API=false
VITE_MOCK_SAVE_FAILURES=false
```

`VITE_CANDIDATE_SERVER_URL` is the production build fallback. The app still lets each candidate computer configure and test the actual Center Server URL before login.

## Server Configuration

1. Start the AlignEx Center Server App.
2. Confirm the candidate computer is on the same LAN.
3. Open AlignEx Candidate Client.
4. Enter the Center Server URL, for example `http://192.168.1.10`.
5. Click `Test`, then `Continue`.

The configured server URL is stored locally on the candidate computer and is reused on restart. `VITE_CANDIDATE_SERVER_URL` or `VITE_DEFAULT_SERVER_URL` only provides a build-time fallback; the runtime setup screen remains the normal production path.

## Mock Mode

Mock mode is for development only and is not shown in production builds.

```env
VITE_USE_MOCK_API=true
VITE_MOCK_SAVE_FAILURES=true
```

Mock login:

- Registration number: `CAND-001`

Mock socket event example:

```js
window.dispatchEvent(new CustomEvent('alignex:mock-socket-event', {
  detail: { type: 'server_message', payload: { message: 'Mock supervisor message' } }
}))
```

## Build

```bash
npm run build
```

This creates:

- Electron main process output in `dist/electron`
- Renderer output in `dist/renderer`

Run the built app locally:

```bash
npm start
```

## Windows Installer

```bash
npm run dist
```

The NSIS installer is written to:

```text
dist-release/
```

Installer name:

```text
AlignEx-Candidate-Client-Setup-<version>.exe
```

Installer configuration:

- App name: `AlignEx Candidate Client`
- Installer type: Windows NSIS
- Output folder: `dist-release/`
- Desktop and Start Menu shortcuts are created
- App icon path: `public/images/logo.ico`

## Installation

1. Copy the installer from `dist-release/` to the candidate computer.
2. Run the installer.
3. Launch `AlignEx Candidate Client`.
4. Configure the Center Server URL.
5. Candidate logs in and starts the exam.

For a center deployment, repeat the server configuration on each candidate computer or prepare the build with `VITE_CANDIDATE_SERVER_URL` set to the expected Center Server base URL.

## Candidate Flow

The MVP supports:

- server configuration
- candidate login with registration number for the active exam
- exam instructions
- exam writing
- answer auto-save
- pending answer retry
- brief disconnection recovery
- real-time server messages
- manual submission
- auto-submit at time expiry
- submitted success page
- candidate-safe recovery pages

## Lockdown

Set this only when testing or deploying exam mode:

```env
VITE_ENABLE_EXAM_LOCKDOWN=true
```

Software lockdown helps reduce accidental or casual misuse, but it cannot fully prevent cheating without physical supervision, secure operating system policy, and proper exam hall procedures.

## Folder Structure

```text
electron/
src/
src/pages/
src/components/
src/components/exam/
src/services/
src/hooks/
src/context/
src/types/
src/utils/
public/
```
