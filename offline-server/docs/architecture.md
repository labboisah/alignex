# Offline Server Architecture

## High-Level Flow

1. Main AlignEx portal prepares an offline package for an exam or assessment.
2. Package is signed, encrypted, downloaded, and copied to the center server.
3. Offline server verifies the package signature and imports allowed candidates, exam settings, and question payloads.
4. Candidates connect to the local server over LAN and write the exam.
5. Supervisor monitors sessions, incidents, and submissions locally.
6. Offline server exports a signed sync bundle containing attempts, answers, incidents, and audit events.
7. Main AlignEx imports the sync bundle, validates it, and performs authoritative scoring/review/result release.

## Online AlignEx Responsibilities

- Decide which center, institution, exam, assessment, candidates, and questions are allowed offline.
- Generate paper snapshots and candidate access credentials.
- Sign and encrypt offline packages.
- Reject stale, replayed, or tampered sync bundles.
- Score and release results after sync.

## Offline Server Responsibilities

- Import only valid packages.
- Serve only candidates contained in the imported package.
- Enforce local exam timing from server state.
- Persist autosaves immediately.
- Preserve event and incident logs.
- Export a complete sync bundle.

## Candidate Browser Responsibilities

- Request only the current exam payload.
- Save answers through the local API.
- Report client-side security events.
- Submit through the local API.

The candidate browser must never receive answer keys, scoring logic, or correctness flags.

## Supervisor Responsibilities

- Confirm imported package details before exam start.
- Monitor candidate status.
- Review incidents.
- Apply allowed interventions such as reset session, end session, or mark disqualified.
- Export sync bundle after the exam.

## Data Ownership

Main AlignEx remains the permanent record. The offline server is a temporary operational record for the exam event. After successful sync, the offline server should retain an audit copy according to the center retention policy.
