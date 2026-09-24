# Candidate Exam Client-to-Server Dependency Audit - 23 September 2026

**Status:** Dependency model established from source review; production outage and load measurements are still required.

## Decision

The purpose of this audit is to establish how much an active Candidate Client depends on the Center Server while writing an examination: which functions continue locally, which must reach the server, and how long an outage can be tolerated without losing work or preventing a valid completion.

Do not describe this dependency as a fixed CPU/RAM percentage. Resource use is supporting evidence for capacity planning; the dependency question is primarily about examination continuity, durable answer acknowledgement, recovery, and final submission.

The system can currently prove candidate progress, answer persistence after acknowledgement, attempt state, submission, socket presence, and server database/WAL configuration. It cannot yet prove outage tolerance, exact recovery time, request-latency percentiles, SQLite lock rate, or a capacity limit for an actual candidate exam.

## Dependency Conclusion

The current Candidate Client is **locally usable but server-authoritative** after the paper has loaded:

| Candidate activity | Can continue without Center Server? | Current behavior |
| --- | --- | --- |
| View already loaded questions/options | Yes | The complete paper is kept in the persisted browser session. |
| Move between questions / palette | Yes | Navigation is local React state. |
| Select or change an answer | Yes, temporarily | Selection is local and becomes a persisted pending-answer record before save. |
| Local visible countdown | Yes, temporarily | The renderer decrements a local value; it is refreshed from the server when the session is restored. |
| Save an answer as authoritative exam data | No | Every save requires `POST /api/candidate/answer`, server validation, SQLite transaction, and acknowledgement. |
| Recover after restart or reconnect | No | The client must call `GET /api/candidate/exam` to obtain server-authoritative answers, attempt state, and remaining time. |
| Receive supervisor close / force-submit / disqualification | No | These arrive through the Center Server Socket.IO connection. |
| Final submit / auto-submit | No | Submission requires the Center Server to score, close the attempt, and persist the result. |
| Enforce final time and attempt status | No | The server rejects late saves and owns terminal attempt status. |

Therefore, a brief Center Server or LAN outage should not immediately erase already loaded paper content or locally queued answer selections. It does prevent durable saves, authoritative recovery, supervisor controls, and valid final submission until the connection returns. The candidate must not be considered safely complete until pending answers are acknowledged and the server confirms submission.

## What The Current Client Persists Locally

The persisted examination session includes the downloaded paper, selected answers, server-acknowledged answers, pending answers, current question index, attempt token, and locally displayed remaining time. This supports short interruption recovery and prevents answer selections from disappearing when the renderer restarts.

It is not a self-contained offline examination engine. The local session is a recovery cache, not the source of truth. The Center Server remains responsible for paper authorization, answer validation, durable storage, timing, submission, scoring, and result status.

## Dependency Risks To Validate

1. **Server/LAN outage:** pending answers are retained, but the current client retries while an exam is open. Measure the maximum outage for which restoration and submission remain successful.
2. **Timer divergence:** the displayed timer continues locally during an outage, while the Center Server remains authoritative. Measure the difference between client display and server time after reconnect.
3. **Submission boundary:** a candidate can finish answering locally during an outage but cannot complete until every pending answer and the final submission receive acknowledgements.
4. **Server restart:** attempt-token validity, paper recovery, pending-answer replay, and server-side deadline behavior require a dedicated restart test.
5. **Socket interruption:** answer HTTP saves can recover independently, but supervisory controls and presence are unavailable until Socket.IO reconnects.

## Audit Scope

This review covered the actual offline candidate examination path, not the separate Autoboot readiness path:

- Candidate Client Electron main process and normal exam screen.
- Candidate Socket.IO heartbeat.
- Center Server candidate answer, submission, monitor, status, and database paths.

## Findings

### Client CPU: not measured

The Candidate Client uses Electron renderer timers, React rendering, Socket.IO, answer retry, and local state while an exam is active. The Electron main process has no IPC for CPU usage and the regular exam heartbeat sends only candidate identity and a timestamp. No CPU sample reaches the Center Server or a local support export.

### Client RAM: available locally, but not measured during actual exams

The Electron preload exposes `getMemoryInfo()`. The main process can obtain the renderer working set and private bytes through `app.getAppMetrics()`. The normal exam path does not call this API. It is currently used only by the Autoboot readiness heartbeat.

Therefore, no actual-exam report can currently state a candidate workstation's RAM use.

### Server CPU and RAM: not measured for live exams

The Center Server status service provides active/connected/submitted counts and database metadata. It does not sample Node process CPU, resident memory, heap, event-loop delay, SQLite busy/lock errors, request duration, or disk/WAL growth during ordinary candidate examinations.

The readiness service has limited process-memory/database-size sampling for readiness evidence, but this is not attached to the official candidate-exam lifecycle and does not measure CPU or attribute cost to a candidate.

### Per-candidate server resource use cannot be read directly

Electron processes can be measured per workstation. A single Node/SQLite Center Server process is shared by all candidates, so operating-system counters cannot truthfully label a CPU/RAM sample as belonging to one candidate. The defensible quantity is the incremental server cost under a controlled workload:

$$
\text{server CPU per active candidate} = \frac{\overline{\text{CPU}_{N}} - \overline{\text{CPU}_{0}}}{N}
$$

$$
\text{server RAM per active candidate} = \frac{\overline{\text{RSS}_{N}} - \overline{\text{RSS}_{0}}}{N}
$$

where $N$ is the stable active-candidate count, $\text{CPU}_{0}$ and $\text{RSS}_{0}$ are the idle baseline for the same server, and the overline denotes an interval average. Report the result in CPU milliseconds per second and MiB per candidate as well as percentages for the specified hardware.

## Why "10% Per Candidate" Is Usually Incorrect

If ten candidates each used 10% of one server CPU, the server would already be saturated before database spikes, submission, operating-system overhead, or headroom. On a multi-core machine, Windows CPU percent is typically normalized across all logical processors, so the meaning also changes with core count.

RAM behaves differently: Electron has a baseline renderer/main/GPU-process footprint even before a candidate starts an exam, and Center Server memory is shared across requests, Socket.IO connections, SQLite caches, and imported paper data. It should be reported as private/working set on the client and process RSS/heap on the server, not a guessed percentage.

## Existing Execution Cost Drivers

### Candidate workstation

- Electron main, renderer, GPU, and utility processes.
- React question rendering and question-palette size.
- Paper/question and option payload size.
- Local pending-answer queue and retry work.
- Ten-second Socket.IO heartbeats.
- Fullscreen/kiosk behavior and anti-cheat event listeners.

### Center Server

- Socket.IO connections and heartbeat handling.
- SQLite reads for candidate-paper validation.
- SQLite answer upsert and exam-event insert in one transaction per answer.
- Progress aggregation and monitor queries.
- Submission scoring, which loads paper/question/options and updates all answered rows.
- Concurrent autosaves, retries, report/export work, and WAL checkpoint behavior.

## Required Instrumentation

### Candidate Client

Add an opt-in, operationally approved `exam_telemetry` capability to the actual-exam heartbeat. It must send no candidate answer content, questions, scores, or secrets.

Sample every 10 seconds while an attempt is active and at submission:

- Renderer working set and private bytes from Electron `app.getAppMetrics()`.
- Electron process CPU time delta, collected in the main process and converted to CPU milliseconds per sampling interval.
- Process type/count where available: browser, renderer, GPU, utility.
- Pending-answer queue depth, last answer-save duration, and retry count.
- Client app version, OS family/version, architecture, logical CPU count, and RAM bucket.

Store a bounded local ring buffer and send aggregate samples with attempt/device identifiers over the authenticated candidate channel. The server must rate-limit, validate, and redact this operational telemetry from candidate-visible responses.

### Center Server

Sample every 5 seconds while an official exam is active and once before/after the exam:

- Node process CPU time delta and CPU percentage normalized by logical processors.
- Process RSS, heap used, heap total, external memory, and event-loop delay p50/p95/p99.
- Active sockets, active attempts, answer requests, answer failures, submission requests, and request latency p50/p95/p99.
- SQLite database and WAL size, busy/locked errors, transaction duration, checkpoint duration, and free disk.
- Operating-system CPU/RAM capacity, logical CPU count, and server hardware/OS version.

Use a dedicated `exam_resource_samples` table or append-only metrics file with `exam_id`, UTC timestamp, active-candidate count, and release versions. Do not put telemetry in result tables and do not sample per HTTP request synchronously.

## Measurement Protocol

1. Pin the Center Server build, Candidate Client build, exam package hash, Windows image, server hardware, client hardware class, wired switch, and LAN configuration.
2. Record an idle server baseline for at least 10 minutes with no active candidate attempts.
3. Record an idle Candidate Client baseline for at least 5 minutes after launch and before login.
4. Run one candidate through login, paper load, paced answering, submit, and exit. Record median, p95, and peak client CPU/RAM and server deltas.
5. During the same run, deliberately interrupt the LAN and Center Server for fixed intervals, then measure queued answers, recovery time, timer divergence, rejected/duplicated saves, and successful final submission.
6. Repeat at 5, 10, 15, 20, 25, and the target capacity. Use a realistic answer distribution rather than synchronized answer bursts, then run a separate worst-case burst test.
7. At each tier calculate server incremental cost, total CPU/RAM, p95 answer-save latency, error/retry rate, SQLite lock rate, client peaks, and outage-recovery success rate.
8. Stop escalation when any service-level objective fails; preserve the evidence bundle and investigate before increasing load.

## Reporting Format

For every accepted run report:

| Metric | Unit | Required view |
| --- | --- | --- |
| Client CPU | CPU ms/s and % of logical capacity | median, p95, peak per hardware class |
| Client RAM | MiB private bytes and working set | median, p95, peak per hardware class |
| Server CPU | CPU ms/s and % of logical capacity | baseline, total, incremental per active candidate |
| Server RAM | MiB RSS/heap | baseline, total, incremental per active candidate |
| Answer save | ms | p50, p95, p99, error rate |
| Submission | ms | p50, p95, p99, error rate |
| SQLite | count and ms | busy/lock errors, transaction/checkpoint duration, WAL peak |
| Server dependency | seconds / percentage | outage duration tested, queued-answer recovery, submission recovery, timer divergence |

Each percentage must name the host CPU logical-core count and the process scope. For example, “Candidate Client renderer p95 CPU 6.2% of an 8-logical-core workstation” is meaningful; “a candidate uses 10% CPU” is not.

## Acceptance Gate

Only publish a hardware-specific capacity statement after telemetry is implemented and the target candidate count completes on the intended production hardware/LAN with retained evidence. Publish the client-to-server dependency separately: what continues locally, the tested outage duration, recovery success rate, maximum queued-answer backlog, and conditions required for valid final submission. The statement must include headroom; it must not certify a tier that routinely drives sustained CPU, memory, storage, network, or p95 answer-save latency close to the operational limit.
