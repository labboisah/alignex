# Autoboot Production Readiness Audit - 20 September 2026

**Status:** No-go for final Center Server and Candidate Client packages

**Scope:** Autoboot readiness package management, portal APIs, Center Server readiness drill runtime, Candidate Client runtime, operational reporting, release packaging, and capacity validation.

**Implementation update:** Core source remediations were applied on 20 September 2026. Final package publication remains blocked until signed Windows artifacts are produced and the required capacity certification is completed on the intended production hardware and LAN.

## 1. Decision

Final Autoboot application packages must not be produced or distributed yet.

The portal package-management foundation and readiness reporting are present, but the current runtime does not meet the security, live-exam isolation, reconnect recovery, build, and reproducible-capacity requirements for a production CBT center.

This audit is a source review and targeted validation, not a certification of a deployed center, signed Windows installer, or 15% backup capacity run.

## 2. Scope and Positive Controls

The following controls were confirmed in source:

- The portal requires an active offline activation and activating administrator credentials before returning a readiness package or accepting a readiness report.
- Package and report payloads use canonical JSON SHA-256 verification.
- Active readiness packages cannot be regenerated through the portal package-management workflow.
- Readiness attempts, answers, events, clients, and reports use dedicated local tables rather than official candidate-result tables.
- Readiness reporting retains client IP, raw user agent, operating-system label, RTT, reconnects, heartbeat misses, errors, latest/average/peak memory telemetry, attempt outcome, and event evidence.
- The Center Server readiness admin endpoints require a local administrator session token.

These controls are necessary but are not sufficient for package release because the client and LAN trust boundaries remain incomplete.

## 2.1 Implemented Remediations

- Candidate Electron memory telemetry now uses the supported `app.getAppMetrics()` API and the Candidate Electron/renderer TypeScript checks pass.
- Center Server readiness start and official exam start now enforce bidirectional exclusion through their authoritative service transactions.
- Readiness devices must be explicitly enrolled by fingerprint for each ready drill. Unknown fingerprints are rejected, duplicate active sessions for one enrolled device are replaced, and sessions are drill/device bound with a 15-minute lifetime.
- Candidate Clients re-register after reconnect, obtain a fresh session, replay only unacknowledged queued answers, and resume from the server acknowledgement sequence.
- Portal package/report APIs and Center Server readiness synchronization now use the activation-bound bearer token and device ID. Local configuration no longer writes portal administrator passwords and removes the legacy field when it is read.
- Result upload and update retrieval were migrated to the same activation-token transport so removing the local password does not leave a parallel secret-dependent path.
- Wildcard browser origins were removed from the Center Server. Only local Electron/no-origin and local development origins are accepted; Candidate Clients reject public HTTP server URLs.
- Center Server production packaging now requires executable signing and timestamp verification and writes a SHA-256 release manifest. Candidate `dist` now routes through an Electron Builder Windows release build requiring code signing.

## 3. Release-Blocking Findings

### P0-1: Candidate Client release build fails

**Status: Remediated in source.**

The Candidate Client Electron typecheck fails in `alignex-candidate/electron/main.ts` because `WebContents` does not provide `getProcessMemoryInfo()` in the installed Electron type definitions.

**Impact:** The Candidate Client cannot be treated as a reproducible, release-buildable application. Memory telemetry is also unverified.

**Required remediation:** Use a supported Electron process-memory API, add an Electron compile check to CI, and run the packaged application to verify that readiness heartbeats send valid memory values.

### P0-2: Readiness client identity is not authenticated

**Status: Remediated in source, pending field verification.**

`alignex-server/src/server/app.ts` accepts `readiness_client_register` socket payloads containing a caller-selected `client_id` and issues a readiness session token without binding it to an enrolled device, device fingerprint, or local candidate-client credential. The server uses the reported value when counting connected clients and preparing readiness attempts.

**Impact:** Any reachable socket client can impersonate multiple synthetic clients, satisfy the start threshold, inject answers/events, and make a readiness assessment unreliable.

**Required remediation:** Bind readiness registration to the authenticated Candidate Client device identity. Issue a short-lived, signed drill-specific token after server-side device enrollment; enforce one active readiness attempt per enrolled device; reject duplicate or unknown device identities; include device identity and enrollment evidence in the report.

### P0-3: Autoboot can overlap an official exam

**Status: Remediated in source.**

`startReadinessDrill` only checks for another running readiness drill. `startExam` only checks for another active official exam. Neither operation atomically prevents the other mode from starting.

**Impact:** A synthetic load run can contend with an official examination, violating the documented safety boundary and risking candidate delivery.

**Required remediation:** Add transactionally enforced, bidirectional exclusion:

- refuse readiness creation/start/finalization when an official exam is active or starting;
- refuse official exam start when a readiness drill is ready, running, or closing;
- surface the blocking operation in the admin UI;
- add regression tests for both ordering scenarios.

## 4. High-Priority Findings

### P1-1: Client reconnection does not recover an interrupted drill

**Status: Remediated in source, pending restart/load verification.**

The Candidate Client stores queued readiness answers in local storage, but it does not replay them after reconnection, obtain a new readiness session, reconcile acknowledged answers, or resume the synthetic attempt. The Center Server removes the readiness session when the socket disconnects.

**Impact:** A brief network interruption can turn a recoverable drill into a failed or timed-out run and makes the report a measure of client implementation gaps rather than center resilience.

**Required remediation:** Persist the drill/attempt context, re-register after socket reconnection, issue a new device-bound session, fetch authoritative attempt state, replay idempotent queued answers, and resume only from the first unacknowledged sequence. Test server restart, client restart, lost acknowledgement, and duplicate replay.

### P1-2: Portal administrator password is stored in plaintext locally

**Status: Remediated in source.**

`alignex-server/src/server/activation-service.ts` writes `admin_password` to the Center Server `center-config.json` and later reuses it for package import and report upload.

**Impact:** A user with access to the Windows profile or local backup can obtain portal administrator credentials.

**Required remediation:** Replace stored portal passwords with a revocable, scoped sync credential held using Windows DPAPI or another OS-backed secret store. Rotate existing credentials during upgrade and ensure backups/logs exclude secrets.

### P1-3: LAN transport and browser origins are overly permissive

**Status: Partially remediated in source.** Device enrollment and origin restrictions are implemented. Production network segmentation, firewall policy, and TLS/isolated-LAN operational approval remain required.

The Center Server listens on `0.0.0.0`, Express returns `Access-Control-Allow-Origin: *`, Socket.IO permits all origins, and the Candidate Client accepts both HTTP and HTTPS URLs.

**Impact:** Any reachable network participant can attempt readiness registration or use a captured bearer token. The threat increases if the workstation is bridged to another network.

**Required remediation:** Document a supported wired LAN/firewall profile; restrict origins and Socket.IO handshake origins to the configured center network; require authenticated device enrollment; prefer TLS or explicitly documented isolated-LAN controls; do not allow arbitrary remote origins.

### P1-4: Release packaging lacks signing and integrity metadata

**Status: Remediated in source, pending signed artifacts.**

The Candidate Client defines an NSIS target but no Windows signing configuration. The Center Server creates an unpacked ZIP and does not produce an installer, Authenticode signature, release manifest, or published artifact checksum.

**Impact:** Operators cannot verify publisher identity or artifact integrity, and support cannot reliably identify installed builds.

**Required remediation:** Produce versioned installers for both applications, sign executables/installers with the production certificate, publish SHA-256 checksums and a signed release manifest, verify version/update compatibility, and test clean install, upgrade, rollback, and uninstall without losing approved local data.

### P1-5: Capacity certification is not yet reproducible

**Status: Still open.** Source-level protocol checks are not a substitute for the required physical capacity run.

No automated Autoboot tests or load-test harness were found in the Center Server or Candidate Client repositories. Existing portal coverage tests package management only; it does not validate package API authorization, report upload, socket registration, disconnect recovery, or capacity behavior.

**Impact:** A completed low-volume smoke drill cannot certify the supported center capacity tiers, especially the 288-client readiness target for a 250-candidate live capacity.

**Required remediation:** Add an automated protocol/integration suite and a controlled load harness. Certify each supported tier on the exact Center Server hardware, Candidate Client release, operating-system image, wired switch, and LAN configuration that will host the live sitting.

## 5. Capacity and Evidence Acceptance Criteria

For a selected live capacity $C$, the required Autoboot target is:

$$
\text{Autoboot target} = \left\lceil C \times 1.15 \right\rceil
$$

The final readiness report must preserve the package code/version/hash, app versions, server hardware and OS, client device identities/OS/RAM, LAN configuration, exact target, registered/started/submitted/timed-out counts, per-client RTT/reconnect/error/heartbeat evidence, average and peak RAM, server CPU/RAM/disk/WAL measurements, and the assessment calculation inputs.

A center may receive production approval only when the required run:

1. Uses the exact release candidates and the intended production hardware and wired LAN.
2. Starts only after verified, enrolled clients meet the frozen target.
3. Prevents all official-exam overlap.
4. Completes with no unauthorized registrations, report identity conflicts, or unexplained client duplication.
5. Demonstrates reconnect, client restart, lost acknowledgement, server restart, deadline, and upload-retry recovery scenarios.
6. Records p50, p95, and p99 latency for registration, manifest load, answer acknowledgement, submission, and report upload.
7. Records server CPU, process memory, database size, WAL growth, free disk, and all SQLite errors throughout the run.
8. Produces a signed, hash-verified report accepted by the portal with an idempotent receipt.

The score in the report is operational evidence, not an academic candidate score. It must not expose correct answers or correctness flags to Candidate Clients.

## 6. Required Test Coverage Before Release

- Portal API tests: activation binding, incorrect credentials, inactive package, tampered checksum, report replay, altered replay, wrong device, wrong center, and idempotent upload receipt.
- Center Server tests: readiness/official-exam mutual exclusion, device enrollment, duplicate device rejection, token expiry/renewal, attempt ownership, deadline closing, and report evidence calculation.
- Candidate Client tests: reconnect registration, queue replay, acknowledgement reconciliation, retry exhaustion, deadline handling, and successful exit.
- Installer tests: clean install, upgrade, rollback, data retention, uninstall behavior, signature verification, and artifact hash verification.
- Load tests: supported tiers of 18, 29, 58, 115, 173, 230, and 288 readiness clients with the selected synthetic paper size.

## 7. Validation Performed

| Check | Result | Scope / limitation |
| --- | --- | --- |
| Portal migration editor diagnostics | Passed | `add_manage_readiness_packages_permission` has no reported editor errors. |
| Center Server TypeScript checks | Passed | Main-process and renderer configurations typechecked without reported errors. |
| Candidate Client TypeScript checks | Failed | Electron main-process typecheck fails on `getProcessMemoryInfo`. |
| Candidate Client TypeScript checks after remediation | Passed | Electron process and renderer checks passed after moving memory telemetry to `app.getAppMetrics()`. |
| Readiness activation-token API regression | Passed | `OfflineReadinessApiTest` verifies an active package is available with activation token/device ID and rejects an invalid token. |
| Portal `AutobootResourceManagementTest` | Completed | Confirms synthetic package creation/generation and active-package immutability; does not cover the delivery runtime. |
| Center/Client automated Autoboot suite | Not available | No dedicated protocol, integration, browser, or load suite was found. |
| Packaged application validation | Not performed | No final artifacts existed during this audit. |

## 8. Packaging Gate

The release owner must record every item below as complete before publishing either final installer:

- [ ] P0 findings resolved and covered by automated regression tests.
- [ ] Candidate and Center Server production builds pass from a clean checkout.
- [ ] Both Windows installers are signed and their hashes/manifests are published.
- [ ] Device enrollment, token scope/expiry, and origin/network restrictions are verified.
- [ ] Official-exam and readiness-drill mutual exclusion is verified in both directions.
- [ ] Reconnect and queued-answer replay tests pass.
- [ ] A capacity run passes on the intended production hardware and LAN with preserved evidence.
- [ ] The portal accepts a hash-verified report and returns an idempotent receipt.
- [ ] Operations approves the documented center readiness report and capacity limit.

Until every check is complete, distribute development or test builds only and do not represent Autoboot as production-certified.