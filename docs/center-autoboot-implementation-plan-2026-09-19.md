# Center Autoboot Implementation Plan - 19 September 2026

**Status:** Implementation plan

**Scope:** AlignEx Center Server, Candidate Client, readiness-test protocol, local SQLite workspace, and readiness-report upload.

**Target:** Prove that a center can run a synthetic examination using the same delivery behavior as an actual offline exam, using a package selected for the required capacity profile, without creating official attempts, results, or candidate records.

## 1. Purpose

Autoboot is a controlled readiness examination. A center administrator starts one synthetic drill from the Center Server after the required Candidate Clients have connected. The server selects the correct readiness package for the center's capacity profile, imports or loads the approved package, commands the frozen client set to load the test paper, begin at the server-issued start time, answer questions automatically, save progress, recover from transient connection failures, and submit before the server-issued deadline.

The drill must exercise the same operational features that matter in a live offline exam:

- Center Server activation and local SQLite persistence.
- Candidate Client discovery, connection, and reconnect behavior.
- Server-authoritative start time and deadline.
- Question delivery and option ordering.
- Answer acknowledgement and durable autosave.
- Client progress, timer, and submission states.
- Supervisor visibility into connected, active, delayed, failed, and submitted clients.
- Local event recording and a signed readiness report.

Autoboot is not an official exam. It must never use real candidate identities, production questions, official attempt tables, official results, or result-release workflows.

## 2. Existing Foundation and Gap

The current Center Server already provides the following foundations:

- A readiness drill runtime and local content pack concepts in [readiness-drill-service.ts](../alignex-server/src/server/readiness-drill-service.ts).
- Readiness-drill creation, client-count validation, start, event capture, report creation, and portal upload routes in [app.ts](../alignex-server/src/server/app.ts).
- Separate `readiness_drills`, `readiness_events`, and `readiness_reports` storage in [database.ts](../alignex-server/src/server/database.ts).
- A Socket.IO server that can broadcast `readiness_drill_started`.

The current implementation is not yet a full Autoboot execution engine. It does not persist opted-in clients or readiness attempts, persist individual synthetic answers, command clients through a test attempt, record request latency, or calculate a complete pass/fail decision. It also still assumes a fixed readiness pack rather than a Super Admin-managed catalog of capacity-specific packages. This plan closes those gaps without changing official offline-exam behavior.

## 3. Safety Boundaries

1. Readiness routes, events, and report contracts must use the explicit `readiness_drill` mode.
2. A Center Server must reject starting a readiness drill while an official exam is active. It must also reject opening an official exam while a readiness drill is running.
3. Candidate Clients must reject a readiness command unless the server mode is explicitly `readiness_drill`.
4. Autoboot must use the Candidate Client installation/device identity already supplied by its normal Center Server connection. It must not request a candidate registration number or create a second client account.
5. The server must not hardcode one readiness pack for all centers or capacities. The active drill must use a package selected from a managed catalog, validated during import, and bound to the selected capacity profile.
6. The server may keep private answer metadata only for validating the synthetic run. Candidate-facing readiness payloads must not include correct-answer flags or scores.
7. Readiness data must be stored only in readiness tables and uploaded only through the readiness-report endpoint.
8. A failed or interrupted drill produces operational evidence, never an official examination result.

## 4. Product Workflow

### 4.1 Administrator workflow

1. Start the Center Server and verify activation, database health, WAL status, free disk space, and LAN address.
2. Open the **Autoboot Readiness** workspace and create a drill.
3. Select one of the supported live capacity profiles, `15`, `25`, `50`, `100`, `150`, `200`, or `250`, and its approved readiness package from the Super Admin-managed package catalog. The server calculates the expected Autoboot clients as `ceil(capacity * 1.15)` and then confirms duration, answer cadence, and optional resilience scenario.
4. Candidate Clients connect normally to the Center Server and enable the **Autoboot** toggle.
5. Each successfully toggled and live client immediately counts as **one connected Autoboot client** in the drill's exam screen and monitor.
6. The administrator sees the connected / expected count, client versions, IP addresses, heartbeat freshness, and readiness checks.
7. When the calculated capacity-plus-backup count is connected and ready, the administrator locks the client snapshot and starts the drill.
8. The server broadcasts the start command and deadline to the frozen client set.
9. Each client loads the synthetic paper from the selected readiness package, displays its timer and progress, and automatically runs the configured answer sequence.
10. The server tracks every client through prepared, active, reconnecting, submitted, failed, or timed-out states.
11. At the deadline, the server closes remaining attempts, generates a report, and displays a readiness decision.
12. The administrator uploads the report to the portal after review.

### 4.2 Client workflow

1. Open the dedicated readiness route.
2. Connect normally to the Center Server and enable the **Autoboot** toggle. The client sends its existing device identity, capabilities, and safe telemetry automatically.
3. After the server acknowledges the toggle, display the Autoboot exam waiting screen and count the client as connected in the Center Server's Autoboot exam screen.
4. Fetch the assigned readiness manifest from the selected package and persist it locally before beginning.
5. Use the server-issued `starts_at` and `deadline_at` for the visible timer.
6. For each question, wait the configured test interval, choose the deterministic answer, save it, wait for acknowledgement, then advance.
7. Persist an outbound answer queue locally. On a temporary failure, retry with exponential backoff and jitter.
8. On reconnection, ask the server for the authoritative attempt state, reconcile acknowledged answers, and continue from the next unsaved question.
9. Submit when all questions are acknowledged or when the server deadline is reached.
10. Display a completion, interrupted, or failed state and retain a local diagnostic summary until the report closes.

## 5. Protocol and State Model

### 5.1 Server drill state

```text
draft
  -> ready
  -> collecting_clients
  -> snapshot_locked
  -> starting
  -> running
  -> closing
  -> report_ready
  -> uploaded
```

Failure paths:

```text
collecting_clients -> cancelled
snapshot_locked -> cancelled
starting -> failed
running -> interrupted
report_ready -> upload_failed
```

Rules:

- Only one drill may be in `collecting_clients`, `snapshot_locked`, `starting`, or `running` at once.
- Starting requires the configured expected count, unless an administrator explicitly records an approved lower-count exception.
- The snapshot is immutable after it is locked. Late clients may observe the server but cannot join the current run.
- The server creates the authoritative `starts_at` and `deadline_at`; clients cannot modify either value.
- The server closes all still-active readiness attempts at deadline, records them as timed out, and then enables report generation.

### 5.2 Client attempt state

```text
connected
  -> autoboot_enabled
  -> waiting
  -> paper_loading
  -> prepared
  -> active
  -> reconnecting
  -> active
  -> submitting
  -> submitted
```

Terminal states:

```text
submitted
timed_out
failed
cancelled
```

## 6. Data Model

Retain the existing readiness tables and add the following additive tables. Do not reuse `candidate_attempts`, `candidate_answers`, `candidate_papers`, or official `exam_events`.

### `readiness_packages`

| Field | Purpose |
| --- | --- |
| `id` | Package record ID |
| `code` | Stable package identifier such as `center-capacity-250` |
| `version` | Package version and schema version |
| `capacity_profile` | Supported live capacity: `15`, `25`, `50`, `100`, `150`, `200`, or `250` |
| `status` | draft, imported, validated, active, retired |
| `package_path` | Storage path or import artifact reference |
| `manifest_json` | Safe metadata about paper count, subject count, pack size, and checksum |
| `checksum_sha256` | Integrity signature for the imported package |
| `created_by`, `created_at`, `updated_at` | Audit fields |

The package catalog is managed by Super Admin and imported into the Center Server before it can be selected for a readiness drill. The package is the source of truth for question count, subject mix, and readiness capacity profile; it is never embedded as a hardcoded constant in the client or server runtime.

### `readiness_capacity_profiles`

| Field | Purpose |
| --- | --- |
| `id` | Profile row ID |
| `code` | `standard_15`, `standard_25`, `standard_50`, `standard_100`, `standard_150`, `standard_200`, or `standard_250` |
| `package_id` | Linked approved readiness package |
| `expected_clients_min`, `expected_clients_max` | Valid live-capacity range for the profile |
| `backup_percent` | Operational Autoboot backup percentage; currently 15% |
| `autoboot_target_clients` | Calculated target: `ceil(live_capacity * (1 + backup_percent))` |
| `question_count` | Base paper size for this profile |
| `default_duration_minutes` | Baseline exam duration |
| `validation_rule_json` | Allowed OS/client versions, local storage, and center compatibility conditions |

This profile table allows the server to map the selected capacity requirement to the correct package and ensures the drill is started with the correct question count and performance envelope.

Supported capacity mapping:

| Live capacity | Autoboot target with 15% backup |
| ---: | ---: |
| 15 | 18 |
| 25 | 29 |
| 50 | 58 |
| 100 | 115 |
| 150 | 173 |
| 200 | 230 |
| 250 | 288 |

The target is rounded up because a partial client cannot provide useful backup coverage. The package, profile, backup percentage, and calculated target are immutable once a drill enters `snapshot_locked`.

### `readiness_clients`

| Field | Purpose |
| --- | --- |
| `id` | Server-generated client row ID |
| `drill_id` | Owning readiness drill |
| `client_key` | Stable Candidate Client installation/device identity from the existing Center Server connection, unique per drill |
| `ip_address`, `user_agent` | Connection diagnostics |
| `client_version`, `capabilities_json` | Compatibility and environment facts |
| `device_profile_json` | Privacy-safe device, OS, display, and client configuration inventory |
| `network_profile_json` | Network adapter summary, connection type, and reliability measurements |
| `status` | Connected, autoboot_enabled, included, disconnected, failed, submitted |
| `connected_at`, `last_heartbeat_at`, `disconnected_at` | Connection timeline |
| `reconnect_count`, `error_count`, `heartbeat_miss_count` | Reliability metrics |
| `average_rtt_ms`, `p95_rtt_ms`, `packet_loss_estimate` | Network quality summary |

Unique index: `(drill_id, client_key)`.

### `readiness_attempts`

| Field | Purpose |
| --- | --- |
| `id` | Server-generated synthetic attempt ID |
| `drill_id`, `readiness_client_id` | Ownership |
| `status` | Prepared, active, reconnecting, submitted, timed_out, failed |
| `starts_at`, `deadline_at`, `submitted_at` | Authoritative timing |
| `current_question_order` | Server-observed progress |
| `answered_count`, `total_questions` | Progress summary |
| `last_acknowledged_sequence` | Retry/idempotency cursor |
| `error_count`, `latency_sum_ms`, `latency_sample_count` | Per-client diagnostics |

Unique index: `(drill_id, readiness_client_id)`.

### `readiness_answers`

| Field | Purpose |
| --- | --- |
| `id` | Server-generated answer ID |
| `readiness_attempt_id` | Owning synthetic attempt |
| `question_id`, `question_order` | Synthetic question reference |
| `option_label` | Submitted answer only; never a correct-answer flag |
| `sequence_number` | Client idempotency key |
| `client_sent_at`, `acknowledged_at` | Latency measurement |
| `created_at`, `updated_at` | Audit timeline |

Unique indexes: `(readiness_attempt_id, question_id)` and `(readiness_attempt_id, sequence_number)`.

### `readiness_metrics`

Store sampled server metrics rather than one write per request:

- Drill ID and timestamp.
- Process CPU percentage and resident memory.
- Event-loop delay percentile.
- SQLite WAL size, database size, checkpoint duration, busy/lock error count.
- Connected client count, active attempt count, queued answer count, and request latency percentiles.

The metrics sampler should run every five seconds. It must not synchronously query or block every client request.

### Client telemetry contract

The client profile is collected automatically when Autoboot is enabled and refreshed only when the values change. It must contain operational facts needed to diagnose a center, not personal data, administrator secrets, file paths, browser history, or hardware serial numbers.

Required `device_profile_json` fields:

- Candidate Client version and build identifier.
- Operating-system family, version, and architecture.
- Application runtime version.
- Device class: desktop, laptop, thin client, or virtual machine when detectable.
- Processor logical-core count and memory bucket, such as `4-7 GB` or `8-15 GB`.
- Display width, height, scale factor, and fullscreen capability.
- Local storage availability and available quota bucket.
- Webcam and microphone capability flags when the drill explicitly tests them.

Required `network_profile_json` fields:

- Connection type when available: Ethernet, Wi-Fi, cellular, unknown.
- Local IP version only and masked subnet classification; do not report public IP addresses through the client profile.
- Socket connection state and transport used.
- Median and p95 heartbeat round-trip time.
- Missed heartbeat count, reconnect count, and estimated request failure rate.
- Latest successful contact timestamp and local queued-answer count.

The server may derive its own observed client IP address, request latency, connection duration, and Socket.IO transport. Server-observed values take precedence over client-reported values in the report.

### Package import and selection contract

The server must support the following lifecycle for a readiness package:

1. Super Admin uploads or imports a package artifact into a controlled storage area.
2. The server validates the package schema, hash, question count, subject map, and required metadata.
3. The package is assigned to one of the supported capacity profiles and marked `validated`. Its manifest must declare the live capacity and the 15% Autoboot backup target.
4. A readiness drill selects the package by profile and package ID, calculates `ceil(capacity * 1.15)` expected Autoboot clients, and stores that value in the drill configuration.
5. If a package is missing, invalid, or the wrong capacity profile is selected, the drill cannot start and the server returns an explicit operational error.

This avoids a single hardcoded readiness exam and keeps the actual certification package aligned to the center's expected staffing and network profile.

## 7. API and Socket Contract

All readiness endpoints require `mode=readiness_drill` and an active Center Server activation.

### 7.1 Autoboot toggle and client discovery

```text
POST /api/readiness/autoboot/toggle
```

Request:

```json
{
  "enabled": true,
  "client_version": "0.1.6",
  "capabilities": {
    "http": true,
    "websocket": true,
    "local_storage": true,
    "fullscreen": true
  },
  "device_profile": {
    "os_family": "Windows",
    "os_version": "11",
    "architecture": "x64",
    "cpu_cores": 4,
    "memory_bucket": "8-15 GB",
    "display": { "width": 1920, "height": 1080, "scale_factor": 1 }
  },
  "network_profile": {
    "connection_type": "ethernet",
    "transport": "websocket"
  }
}
```

The endpoint binds the opt-in state to the Candidate Client's existing Center Server connection and installation/device identity. It does not create an account, request a candidate identity, issue a readiness-specific login, or require a registration screen.

When `enabled` is true and the normal connection is live, the client appears in the Center Server Autoboot exam screen as **Ready for Autoboot** and increments the connected-client count by one. If the client disconnects, it remains in the client list but is removed from the live connected count and marked disconnected. When false, it is excluded from all snapshots and receives no readiness commands. The normal connection supplies the heartbeat and Socket.IO transport; readiness requests are authorized against that existing connection rather than a separate readiness session token.

### 7.2 Manifest and attempt preparation

```text
GET  /api/readiness/drills/{drillId}/manifest
POST /api/readiness/drills/{drillId}/attempts/prepare
GET  /api/readiness/drills/{drillId}/attempts/current
GET  /api/readiness/drills/{drillId}/attempts/{attemptId}/visualization
```

The manifest contains 100 safe synthetic questions arranged as four subjects of 25 questions. It must omit correct answers. The prepare response returns a synthetic attempt ID, the immutable question/option order, server `starts_at`, and `deadline_at`.

The visualization endpoint is for authenticated Center Server operators only. It returns the immutable synthetic paper, option order, acknowledged answer state, attempt timeline, current-question cursor, and connection state for one client. It must never return answer-key or correctness fields, even though the paper is synthetic.

### 7.3 Answer acknowledgement

```text
POST /api/readiness/drills/{drillId}/attempts/current/answers
```

Request fields:

- `question_id`
- `question_order`
- `option_label`
- `sequence_number`
- `client_sent_at`

The server validates that the question belongs to the synthetic attempt and returns the same acknowledgement for a repeated sequence number. This makes network retries safe.

### 7.4 Heartbeat and completion

```text
POST /api/readiness/drills/{drillId}/heartbeat
POST /api/readiness/drills/{drillId}/attempts/current/submit
```

Heartbeat response includes the authoritative deadline, attempt status, last acknowledged sequence, and optional server backoff instruction. Submission is idempotent and returns the final synthetic state without an exam score.

### 7.5 Socket events

| Event | Direction | Purpose |
| --- | --- | --- |
| `readiness_autoboot_enabled` | Server to client | Autoboot toggle accepted and client is eligible for a snapshot |
| `readiness_snapshot_locked` | Server to client | Opted-in client included or excluded from run |
| `readiness_drill_started` | Server to client | Includes drill ID, starts-at, deadline-at |
| `readiness_server_backpressure` | Server to client | Temporarily slow automated answer cadence |
| `readiness_drill_closing` | Server to client | Stop new answers and reconcile queue |
| `readiness_attempt_closed` | Server to client | Terminal state |

Socket events are a prompt, not the source of truth. Every command must be recoverable through the HTTP state endpoint after reconnect.

## 8. Synthetic Exam Behavior

### 8.1 Content pack and capacity package model

The standard pack is no longer a single hardcoded artifact. The server loads a capacity-specific package from the catalog under Super Admin control. Each approved package must declare:

```text
package_code
package_version
capacity_profile
subject_count
question_count
options_per_question
checksum
manifest_version
```

Example package values:

```text
alignex.readiness-pack.v1-25
4 subjects
25 questions per subject
100 questions total
4 options per question

alignex.readiness-pack.v1-250
4 subjects
62 questions per subject
250 questions total
4 options per question
```

The actual package selected for a drill depends on the center's capacity target and the approved profile. A server-side validation step rejects a package if the declared question count or subject count does not match the selected profile.

Questions should represent realistic payload sizes: short and medium stems, varied option lengths, and no external media in the first release. A later pack may add controlled image payloads only after the baseline is certified.

### 8.2 Automated answering

The first implementation uses a deterministic seeded option pattern. The seed is derived from the drill ID and client key so retries choose the same option.

For every question, the client must:

1. Render the current question, timer, connection state, and `n / 100` progress.
2. Wait the configured cadence plus a small deterministic offset.
3. Select the seeded option.
4. Add the request to its durable local queue.
5. Send the answer with an idempotency sequence number.
6. Mark the answer acknowledged only after the server response.
7. Advance the visible progress after acknowledgement.

Default cadence for capacity testing should be configurable. Use a short cadence to create a meaningful write rate without causing all clients to save on the same millisecond. The scheduler must spread client actions across an interval using deterministic jitter.

### 8.3 Full exam features to exercise

The readiness flow must include:

- Full-paper loading and local cache validation.
- Question navigation in forward and backward directions.
- Autosave acknowledgement and recovery after duplicate submission.
- Timer display driven by the server deadline.
- Connectivity indicator, reconnect count, and queued-answer count.
- Automatic submission and server deadline handling.
- Supervisor monitoring of progress and failures.
- Audit event generation for connect, start, answer acknowledgement, retry, reconnect, submit, timeout, and failure.

Fullscreen, webcam, and anti-cheating enforcement are excluded from the first capacity certification because they test workstation policy rather than core server throughput. They may be added as explicit subsequent readiness scenarios.

## 9. User Interface Requirements

### 9.1 Candidate Client readiness screen

The first visible state must clearly say `READINESS TEST`. The client must display:

- Center name and drill name.
- Autoboot enabled and connected state while waiting for the server to start the test.
- Connection state: connecting, waiting, active, reconnecting, submitted, failed.
- Server-authoritative countdown.
- Current question and subject.
- `Answered / 100` progress.
- Save state: saving, saved, retrying, queued.
- Reconnect count and last successful server contact.
- Final summary with submitted, timed out, or failed state.

The client must not display a score, correct answer, official candidate name, exam code, or production result language.

### 9.2 Center Server readiness dashboard

The readiness monitor must mirror the operational structure of the actual exam monitor. It is a drill-specific monitor with synthetic identities, not a simplified status page.

It must provide these views:

| View | Required content |
| --- | --- |
| Overview | Drill phase, server deadline, connected/expected clients, active/submitted/failed counts, answer throughput, acknowledgement latency, and readiness decision |
| Client monitor | Searchable and filterable client grid comparable to the actual candidate monitor, including connection, attempt, progress, timer, retries, and incident state |
| Client detail | Synthetic identity, event timeline, answers acknowledged, queue depth, latency series, device profile, network profile, and actionable error history |
| Attempt visualization | Read-only live view and replay of one synthetic client paper, with question navigator, visible selected options, acknowledgement state, attempt timer, and event timeline |
| Server health | CPU, memory, event-loop delay, database/WAL size, disk free space, request rate, SQLite busy/lock errors, and Socket.IO connection count |
| Network health | Client connection-type distribution, RTT percentiles, reconnect rate, missed-heartbeat rate, request-failure rate, and clients grouped by affected subnet or switch segment when supplied by the center configuration |
| Incidents | Stream of disconnects, slow clients, retry exhaustion, protocol mismatch, failed saves, and server backpressure actions |
| Report | Live pass/fail threshold evaluation, report preview, unresolved clients, and upload status |

The overview must support rapid operational decisions:

- Connected / expected clients.
- Prepared, active, reconnecting, submitted, timed-out, and failed counts.
- Current answer throughput and acknowledgement latency: average, p95, p99.
- Server CPU, memory, event-loop delay, database/WAL size, and lock-error count.
- Per-client rows with version, OS, connection type, server-observed IP, heartbeat age, current question, remaining time, queue depth, latency, retries, and error state.
- Snapshot lock and start controls with an explicit confirmation dialog.
- Stop and close controls with a recorded operational reason.
- Report readiness decision and upload state.

The client monitor must provide filters for status, connection type, operating system, Client version, reconnect count, heartbeat age, latency band, queue depth, and error state. It must also allow an operator to export the synthetic-client diagnostic list into the readiness report. It must not permit changing a client answer, score, or official-exam state.

### 9.3 Attempt visualization and replay

Selecting a row in the client monitor opens a read-only attempt visualization. It must resemble the actual candidate exam screen closely enough for an operator to see exactly what the synthetic client is doing, while remaining clearly labelled `READINESS TEST`.

The visualization must show:

- Synthetic client ID, attempt status, current question, server deadline, and remaining time.
- A 100-question navigator with distinct states for unvisited, queued, saving, acknowledged, retrying, failed, and current.
- Current subject and question text with the immutable option order.
- Selected option after the server acknowledges it; unacknowledged local intent must be displayed separately as queued, not as saved.
- Answered count, total count, current queue depth, retry count, reconnect count, and latest acknowledgement latency.
- A chronological timeline of Autoboot enablement, paper load, start, answer acknowledgement, retry, reconnect, submission, timeout, and error events.
- A live-update indicator and the timestamp of the last server observation.

After completion, the same screen becomes a replay. An operator can select any question in the navigator and inspect its submitted option, acknowledgement timestamp, sequence number, request latency, and related retry events. The replay must be read-only and must not calculate or display a score, correct answer, or correctness state.

Visualization data is derived from the server's persisted readiness attempt, answer, and event records. It must not depend on a connected client remaining online. Live updates may use Socket.IO, but the endpoint in Section 7.2 remains the recovery source after a reconnect or monitor refresh.

### 9.4 Configurable readiness settings

The Center Server administrator configures the following before the snapshot is locked:

| Setting | Default | Purpose |
| --- | --- | --- |
| Expected clients | Required | Number of clients required before start |
| Capacity profile / package | Required | Selected Super Admin-approved readiness package for the center load profile |
| Duration | Required | Server-authoritative drill duration |
| Questions | Package-defined | Question count is derived from the selected readiness package, not a fixed constant |
| Backup percentage | 15% | Synthetic Autoboot clients above the selected live capacity |
| Autoboot target | Calculated | `ceil(live capacity * 1.15)` clients |
| Answer cadence | Configured | Mean interval between automated answers |
| Cadence jitter | Enabled | Spreads writes across clients |
| Heartbeat interval | 5 seconds | Client liveness and RTT sample interval |
| Disconnect threshold | 3 missed heartbeats | Time before a client is marked disconnected |
| Retry limit | 5 | Bounded retry count for one unacknowledged answer |
| Backoff ceiling | 30 seconds | Maximum wait before a queued answer retry |
| Backpressure threshold | Configured | p95 latency or event-loop delay that slows clients |
| Network scenario | Normal | Optional controlled reconnect or packet-loss simulation |
| Client compatibility policy | Strict | Reject or warn on unsupported Client/OS versions |
| Maximum request concurrency | Configured | Bounds simultaneous candidate HTTP work during a drill |
| Maximum pending requests | Configured | Rejects or delays overload before Node.js becomes unresponsive |
| Maximum answer queue depth | Configured | Detects a client that cannot keep up with acknowledgements |
| Network segment label | Optional | Associates a client with a lab, switch, VLAN, or row for fault isolation |

The drill configuration must be frozen into the report at start. Changes require a new drill; they must not change the behavior of a running drill.

### 9.5 Client preflight and compatibility gate

Enabling Autoboot begins a preflight check before the client can contribute to the ready count. The client remains visible as connected, but is marked `warning` or `blocked` until the check completes.

The preflight checks:

- Candidate Client version, build, and supported protocol version.
- Operating-system and runtime compatibility.
- Local storage availability and required durable-queue capacity.
- Display size and fullscreen capability when the selected drill requires them.
- Connectivity to the Center Server through HTTP and Socket.IO.
- Clock drift between client and Center Server.
- Available local disk-space bucket and safe device/network telemetry collection.

The client receives the Center Server timestamp with each preflight result. The server remains authoritative for the drill deadline, but the monitor must show clock drift so an operator can investigate a misleading client-side countdown. A client outside the configured drift threshold is blocked or flagged according to the compatibility policy.

Only clients with a passing preflight and a live Autoboot toggle count as **ready**. The exam screen shows both `connected` and `ready` counts so an operator can distinguish devices that are online from devices that are eligible to start.

### 9.6 Resilience test profiles

Autoboot must support named profiles so a center can prove more than the happy path:

| Profile | Purpose |
| --- | --- |
| `normal` | Staggered answer cadence with no injected failure |
| `login_storm` | All opted-in clients load the synthetic paper at the same scheduled start time |
| `answer_burst` | A controlled subset saves an answer in the same short interval to test SQLite and request pressure |
| `reconnect_wave` | A selected subset temporarily disconnects and resumes with queued answers |
| `server_restart_recovery` | The Center Server is deliberately restarted during a non-production drill; clients must reconnect and reconcile state |
| `network_segment_probe` | Exercises clients grouped by lab, switch, or VLAN label to reveal a localized network fault |

The selected profile, injected-failure parameters, affected client set, and operator approval must be frozen into the report. No profile may be run against an official exam.

## 10. Reliability and Backpressure

1. The server must set a finite SQLite busy timeout and report every busy/lock failure.
2. Each request must use a bounded transaction. Do not create all client attempts or answers inside one unbounded transaction.
3. Answer endpoints must be idempotent through `(attempt_id, sequence_number)`.
4. Clients retry only failed, unacknowledged answers. Use exponential backoff with jitter and a bounded retry count.
5. The server may issue `readiness_server_backpressure` when p95 latency or event-loop delay exceeds configured thresholds. Clients then increase their cadence instead of retrying simultaneously.
6. The server must preserve queued local answers through a Candidate Client restart and reconcile them after the normal connection is restored and Autoboot is re-enabled.
7. Report all unresolved queues and failed acknowledgements. A drill must fail rather than silently claim completion when evidence is missing.
8. Apply admission control before processing work: cap concurrent request handlers, pending requests, body size, Socket.IO connections, and per-client answer queue depth. Return an explicit bounded retry-after/backpressure instruction rather than allowing an unbounded in-memory queue.
9. Preserve SQLite recovery safety. A controlled restart drill must prove that the database, WAL, and queued client answers recover without duplicate or missing acknowledgement evidence.
10. Capture the network segment label for every opted-in client when configured. The monitor must identify whether latency, disconnects, or errors are concentrated on one segment rather than presenting the incident as a global server fault.

## 11. Observability and Report Decision

### 11.1 Required instrumentation

Record a correlation ID for each client request and event. Capture:

- Request route, status code, elapsed time, and response size.
- Drill ID, synthetic client ID, and synthetic attempt ID.
- SQLite busy/lock errors and transaction durations.
- Process CPU, memory, event-loop delay, database size, WAL size, and free disk space.
- Connection, reconnect, answer, acknowledgement, submission, timeout, and failure counts.
- Per-client operating-system, runtime, display, local-storage, capability, and safe configuration summaries.
- Per-client connection type, Socket.IO transport, RTT percentiles, heartbeat misses, reconnects, request failures, and queued-answer maximum.
- Aggregated client-version and operating-system distribution, plus identification of unsupported or outlier configurations.
- Clock-drift distribution and the count of clients blocked or warned by preflight.
- Network-segment distribution and segment-level RTT, reconnect, request-failure, and completion metrics.
- Admission-control actions, pending-request high-water mark, rejected requests, and server-issued backpressure instructions.

Do not log connection credentials, administrator credentials, or answer-key metadata.

### 11.2 Decision thresholds for the 250-candidate target

The default load target for a production-capacity certification is the approved 250-candidate package plus 15% backup. The exact threshold is profile-driven: the server calculates the synthetic work based on the selected package and `ceil(capacity * 1.15)` expected clients. A 250-candidate capacity package is therefore validated as a 288-client readiness drill, not a 250-client or fixed 300-client drill.

The maximum capacity certification drill uses 288 synthetic clients. If the selected package contains 100 questions, it produces up to:

$$
288 \times 100 = 28{,}800 \text{ acknowledged synthetic answers}
$$

Initial pass criteria:

| Measure | Required result |
| --- | --- |
| Snapshot | The calculated capacity-plus-15% target, or an approved documented exception |
| Paper loading | All included clients load the selected package |
| Completion | At least 98% of snapshot clients submit successfully |
| Answer acknowledgement | At least 99.5% succeed without permanent failure |
| Latency | Answer acknowledgement $p95 < 500$ ms and $p99 < 1$ second |
| Recovery | Reconnected clients resume with no duplicate or missing answer evidence |
| SQLite health | Zero unexplained busy, lock, corruption, or disk-full errors |
| Server health | CPU below 70%, stable memory, and no sustained event-loop stall |
| Device compatibility | No unsupported Client, OS, runtime, local-storage, or display configuration among included clients |
| Network reliability | No unexplained network partition; p95 heartbeat RTT and reconnect rate remain within the approved center threshold |
| Evidence | Report includes all failures and unresolved queues |

The first successful drill establishes a baseline only. Every Center Server or Candidate Client release, hardware change, database change, or network redesign requires a new capacity drill.

### 11.3 Center baseline comparison

The portal must retain an approved readiness baseline per center, hardware profile, Center Server version, Candidate Client version, content-pack version, and network design. Each new report compares its key metrics with the selected baseline:

- p50, p95, and p99 paper-load, answer-acknowledgement, and submission latency.
- Completion rate, acknowledgement success rate, reconnect rate, and heartbeat-miss rate.
- CPU, memory, event-loop delay, SQLite/WAL growth, disk headroom, and busy/lock errors.
- Client compatibility distribution, clock drift, and network-segment results.

The report must flag a material regression even when it technically meets the absolute threshold. A center with unexplained degradation requires manual review or a new capacity certification before an official sitting.

## 12. Implementation Sequence

### Milestone 1: Isolation, package catalog, and persistence

1. Add `readiness_packages`, `readiness_capacity_profiles`, `readiness_clients`, `readiness_attempts`, `readiness_answers`, and `readiness_metrics` migrations to the Center Server SQLite initializer.
2. Extend the readiness service with repository functions and strict state validation.
3. Add Super Admin package import, validation, checksum verification, and lifecycle management for readiness content packages.
4. Bind a readiness drill to a selected package and capacity profile, and reject mismatched or missing packages before start.
3. Enforce mutual exclusion between live official exams and running readiness drills.
4. Add a start preflight for activation, WAL, disk space, expected clients, supported client versions, local storage, clock drift, HTTP/Socket.IO connectivity, and network segment labels.

### Milestone 2: Client protocol

1. Add Autoboot toggle, heartbeat, manifest, prepare, current-state, answer, and submit endpoints using the existing Candidate Client connection identity.
2. Add Socket.IO Autoboot events in Section 7.5 without introducing a second client login or registration flow.
3. Build idempotency and reconciliation before adding automated answers.
4. Add API, schema, and state-machine tests for duplicate requests, delayed requests, client restarts, and deadline races.

### Milestone 3: Candidate Client readiness mode

1. Add a dedicated readiness route and screen to the Candidate Client.
2. Implement waiting, active, reconnecting, completion, and failure states.
3. Implement local durable queue, deterministic answer scheduler, and server-deadline timer.
4. Reuse the visual layout and save/retry controls of the real candidate experience where safe, while retaining the `READINESS TEST` boundary.
5. Add the read-only attempt visualization contract and candidate-paper replay state.

### Milestone 4: Administration and monitoring

1. Add the actual-exam-style Center Server readiness monitor, including overview, client monitor, client detail, server health, network health, incidents, and report views.
2. Add validated client device/network telemetry collection and safe configuration compatibility checks.
3. Add per-client operational rows and aggregated OS, client-version, device, and network distributions.
4. Add network-segment views, client clock-drift visibility, and preflight warning/block actions.
5. Add server metric sampling, admission-control telemetry, network reliability calculations, and report calculations.
6. Add baseline comparison and clear pass, warning, fail, and manual-review outcomes.

### Milestone 5: Certification and portal evidence

1. Extend the readiness report contract with latency, health, client, and failure evidence.
2. Validate report idempotency and activation binding in the portal.
3. Run staged `normal` drills for each supported capacity profile using its calculated Autoboot target: 18, 29, 58, 115, 173, 230, then 288 synthetic clients.
4. Run `login_storm`, `answer_burst`, `reconnect_wave`, and `server_restart_recovery` profiles at an approved scale before the final capacity run.
5. Run the final 288-client drill using the actual center hardware and LAN intended for the 250-candidate maximum.
6. Publish the pass/fail and baseline-comparison report and block production approval when criteria are not met.

## 13. Test Plan

Automated tests must cover:

- Isolation from all official exam, candidate, answer, and result tables.
- Autoboot enable/disable, duplicate toggle requests, snapshot inclusion, and opted-out client exclusion.
- Snapshot inclusion and late-client exclusion.
- Capacity-profile selection, 15% backup calculation, ceiling rounding, and package/target immutability.
- Start, deadline, stop, and interrupted-run state transitions.
- Manifest safety: no answer keys or correctness fields in client payloads.
- Answer idempotency and sequence reconciliation.
- Client restart with queued answers.
- Retry backoff and server-directed backpressure.
- Deadline race between an answer and automatic submission.
- Correct report counts, latency percentiles, checksum, and upload idempotency.
- Device-profile validation, privacy filtering, configuration compatibility warnings, and telemetry refresh behavior.
- Preflight gate outcomes for unsupported versions, low local storage, clock drift, HTTP/Socket.IO failure, and disabled Autoboot.
- Network reliability calculations for RTT, heartbeat misses, reconnects, request failure, and server-observed/client-reported disagreement.
- Network-segment aggregation and diagnosis of a segment-localized failure.
- Request admission limits, pending-queue bounds, response-size limits, overload rejection, and server-directed backpressure.
- Readiness-monitor filters, client detail timeline, incident states, and report export of synthetic diagnostic data.
- Live attempt visualization, question navigator states, acknowledgement/retry display, persisted replay after disconnect, and exclusion of correctness data.
- All named resilience profiles, including the Center Server restart/recovery drill and queued-answer reconciliation after restart.
- Baseline selection, regression detection, and manual-review outcome when a metric degrades despite passing its absolute threshold.
- Prevention of starting an official exam while a drill runs, and vice versa.

Manual certification must validate wired LAN behavior, firewall rules, client discovery, server restart recovery, and the full 300-client capacity run.

## 14. Definition of Done

Autoboot is ready for production capacity certification only when:

1. A drill executes a full 100-question synthetic attempt for every included client.
2. The server, not the client, controls start, deadline, answer acknowledgement, and terminal state.
3. The client visibly shows progress, timer, save state, connection state, and test-only identity.
4. Answers and submission are retry-safe and durable across client reconnects.
5. All records remain isolated from official examination data.
6. The report includes measurable server, SQLite, network, latency, client, and error evidence.
7. Client preflight, admission control, network-segment visibility, restart recovery, and baseline comparison are available in the monitor and report.
8. The 300-client capacity drill passes the criteria in Section 11.2 on the intended deployment configuration.