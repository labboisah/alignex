# AlignEx CBT Center Server App

This is the initial Electron desktop app for running an AlignEx CBT center server on a local machine. It starts a local Node/Express server when Electron launches, opens a React admin UI, prepares a SQLite database, exposes server health, and reports LAN access details for candidate machines.

Exam import, candidate login, answer saving, scoring, and sync logic are intentionally not implemented yet.

## Stack

- Electron
- React + TypeScript
- Vite
- Tailwind CSS
- shadcn/ui-style components
- lucide-react
- Node.js
- Express.js
- SQLite with better-sqlite3
- Socket.IO

## Features In This Initial App

- Electron main process starts the local Express server.
- `GET /api/health` returns server status.
- `GET /api/server-info` returns database connectivity and MVP record counts.
- SQLite database is created automatically.
- SQLite WAL mode is enabled.
- Local LAN IP address is detected.
- Candidate URL is generated from the LAN IP and server port.
- Socket.IO server is attached for future live monitoring.
- Admin UI has a sidebar, top status bar, dashboard cards, loading state, and API error state.
- Import Exam screen accepts a unique import code or a manual JSON package upload.
- Import Exam can retrieve, validate, and save an MVP JSON package into SQLite.

## Setup

Install dependencies from this directory:

```bash
cd offline-server
npm install
```

Start the Electron app in development mode:

```bash
npm run dev
```

Build the main process and renderer:

```bash
npm run build
```

Run the built Electron app:

```bash
npm start
```

## Local Server

Default server port:

```text
4080
```

Health endpoint:

```text
http://127.0.0.1:4080/api/health
```

Server information endpoint:

```text
http://127.0.0.1:4080/api/server-info
```

Candidate URL shown in the app:

```text
http://<local-lan-ip>:4080/exam
```

The candidate route is only reserved for the future candidate exam interface. It is not implemented yet.

## Environment

Copy `.env.example` if you want to customize local settings later:

```bash
cp .env.example .env
```

Current useful values:

```text
OFFLINE_SERVER_PORT=4080
OFFLINE_CENTER_ID=
OFFLINE_STORAGE_PATH=./data/offline.sqlite
ALIGNEX_SYNC_BASE_URL=
ALIGNEX_SYNC_TOKEN=
```

Code-based import calls:

```text
GET <ALIGNEX_SYNC_BASE_URL>/api/offline/exam-packages/{IMPORT_CODE}?center_id=<OFFLINE_CENTER_ID>&exam_code=<EXAM_CODE>
```

The endpoint may return the exam package directly, or wrap it under `package`, `exam_package`, or `data`.

## Directory Map

```text
offline-server/
  docs/                  Architecture notes.
  src/
    contracts/           Offline package and sync bundle contracts.
    electron/            Electron main process.
    renderer/            React admin UI.
    server/              Express, SQLite schema, LAN IP, Socket.IO, and status service.
    services/            Shared services for future package/runtime work.
    storage/             SQLite schema reference.
```

## Exam Package Format

The MVP package format is documented in:

```text
docs/exam-package-format.md
```

The current package is JSON with these top-level keys:

```text
manifest, subjects, questions, options, candidates
```

Sample packages:

```text
docs/samples/valid-exam-package.json
docs/samples/invalid-exam-package.json
```

Manual MVP test steps:

```text
docs/mvp-test-checklist.md
```

## Next Step

The next implementation phase should add offline exam package import without exposing answer keys to the candidate side.
