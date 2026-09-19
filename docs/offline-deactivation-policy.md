# Offline Server Deactivation Policy

**Status:** Implementation specification
**Date:** 17 September 2026
**Scope:** AlignEx Center Server, offline activation, local SQLite data, cloud activation records, result upload, and tenant switching.

## 1. Purpose

This policy defines how an AlignEx Center Server is deactivated and later activated for the same or a different organization, institution, or center on the same laptop.

The policy must protect examination data while allowing a shared laptop to operate more than one authorized offline tenant. Deactivation is a license and workspace transition. It is not a destructive database reset.

The policy applies to:

- Online activation and reactivation of a Center Server.
- Device and license revocation.
- Local offline exam data and SQLite databases.
- Exam packages, candidates, attempts, answers, uploads, receipts, and audit records.
- Switching between organization, institution, and center accounts.
- Recovery after application failure, lost connectivity, or an abandoned deactivation.

## 2. Existing System Constraints

The portal currently has:

- `offline_activation_codes` for reusable account-scoped authorization codes.
- `offline_server_activations` for device-specific license activations.
- A unique device binding per activation code and device.
- Remote revocation through Manage Activation.
- An active-license guard for offline APIs.
- Encrypted or authenticated offline package and result-upload workflows.
- Durable local upload queues and portal-side upload receipts.

The existing `license_key` is a device-specific sync credential. It must not be treated as a permanent replacement for the activation code.

The activation code authorizes a new activation. The license key authorizes an already activated local installation to communicate with the portal.

## 3. Definitions

### 3.1 Device

A physical or virtual machine identified by a stable installation `device_id`. The device ID must remain stable across application restarts and normal upgrades, but must not be based only on a mutable network address.

### 3.2 Activation code

A portal-issued, account-scoped secret used to create or restore a server activation. It may be reused according to its status, expiry, and device limit. Generating a new code and reusing an existing code are different operations.

### 3.3 License key

A server activation credential created for one activation-code/device pair. It is used as a bearer or sync token by the local server. It must be revoked when the activation is deactivated or reset.

### 3.4 Workspace

A tenant-isolated local data area containing the SQLite database, encrypted secrets, package files, result files, upload queue, audit journal, and configuration for one organization, institution, or center.

### 3.5 Deactivation

A controlled transition that stops the current workspace from using its active license and prepares it for archive, reactivation, or tenant switching. Deactivation does not automatically delete examination data.

### 3.6 Tenant

The account scope represented by an organization, institution, or center. Tenant identity must be explicit in local and cloud records. A device is not a tenant.

## 4. Core Rules

1. Deactivation must be reversible without deleting historical exam data.
2. A deactivated workspace must not download new packages, upload results, or call protected portal APIs.
3. A new tenant must never see the previous tenant's candidates, exams, answers, attempts, files, or results.
4. Reactivation requires the activation code and administrator credentials again.
5. Reactivation of the same tenant may reuse the existing activation code. It must not require generating a new code merely because the device was deactivated.
6. Activation for a different tenant requires that tenant's activation code and administrator credentials.
7. The old license key must never reactivate a deactivated workspace by itself.
8. Pending results must be uploaded, exported, or explicitly acknowledged before deactivation completes.
9. The application must not silently discard pending uploads, unfinished exams, audit records, or receipts.
10. Remote server state is authoritative for activation status, code status, device limits, expiry, and accepted uploads.
11. Local state is authoritative only for preserving work that has not yet been accepted by the portal.
12. Deactivation and tenant switching must be append-only audited operations.
13. A failed deactivation must leave the current workspace usable or clearly recoverable; it must not leave an ambiguous half-active state.
14. Any destructive deletion requires a separate, explicit data-wipe action and confirmation.

## 5. Activation Lifecycle

The local server must expose these states:

- `unactivated`: no tenant is configured.
- `active`: the workspace has a valid activation and may perform allowed operations.
- `deactivation_pending`: preflight checks are running; normal exam operations are paused.
- `deactivated`: the workspace is retained locally but its license is inactive.
- `archived`: the workspace is read-only and stored as a protected archive.
- `activation_required`: a workspace or new workspace needs an activation code.
- `activation_failed`: the last activation attempt failed; existing archived data must remain intact.
- `blocked`: local security or integrity checks prevent use until an administrator resolves the issue.

A successful same-tenant reactivation follows:

```text
active
  -> deactivation_pending
  -> deactivated
  -> activation_required
  -> active
```

A tenant switch follows:

```text
active tenant A
  -> deactivation_pending
  -> archived tenant A
  -> activation_required
  -> active tenant B
```

A failed preflight must return to `active` if no irreversible operation occurred. A failed remote revocation must not claim that deactivation completed.

## 6. Deactivation Preconditions

The Deactivate action must display a preflight result before changing state.

The preflight must check:

- Current activation status and license expiry.
- Remote connectivity, unless an approved emergency/offline procedure applies.
- Unfinished candidate exams.
- Unsubmitted or not-finalized local attempts.
- Pending, retryable, blocked, or conflicting result uploads.
- Results exported locally but not uploaded.
- Packages imported but not yet used.
- Active supervisors or local administrator sessions.
- Local database integrity and workspace encryption status.
- Available disk space for an archive.
- Whether a backup/export has completed.
- Whether a remote activation record can be revoked or marked inactive.

The default policy is to block deactivation when any candidate is actively writing or when an upload is pending. An authorized super administrator may approve an emergency deactivation, but the application must record the reason and retain the unresolved data.

The preflight must classify work as:

- `complete`: safely archived or already accepted by the portal.
- `pending`: must be uploaded or resolved before normal deactivation.
- `blocked`: cannot be uploaded because of a validation or integrity problem.
- `in_progress`: an exam or local transaction is still active.

## 7. Normal Deactivation Workflow

1. The local administrator chooses **Deactivate Server**.
2. The server displays the tenant name, workspace name, device ID, active license expiry, and last successful sync.
3. The server runs the preflight checks.
4. The administrator reviews pending exams, uploads, exports, and warnings.
5. The administrator uploads pending results or exports a protected backup.
6. The server closes or pauses eligible local sessions according to the existing exam policy. It must not fabricate submissions.
7. The server creates a final encrypted workspace backup and records its checksum.
8. The server requests remote revocation or deactivation of the current activation.
9. The server invalidates the local license key and refreshes protected API credentials.
10. The workspace becomes read-only and enters `deactivated` or `archived`.
11. The server records a deactivation receipt containing the workspace ID, activation ID, device ID, actor, reason, preflight result, archive checksum, and remote response.
12. The server offers **Reactivate this workspace** or **Activate another account**.

If remote revocation succeeds but local archival fails, the server must keep the workspace locked and show a recovery path. It must not create a new active tenant workspace over the old data.

## 8. Same-Tenant Reactivation

Same-tenant reactivation must require:

- The existing activation code for that tenant.
- The administrator email and password.
- The stable device ID.
- A valid activation code status and license period.
- Remote authorization for the administrator and tenant.

The existing activation code may create a new `offline_server_activations` row for the same device after the old row is revoked, or the server may restore the old row only if the audit and security policy explicitly permits it. Creating a new activation row is preferred because it preserves a clear activation history.

The yearly code-generation lock must not block reactivation. Reactivation consumes an existing code; it does not generate a new code.

A reactivated workspace must retain its historical data and receive the same tenant scope. It must not merge with another workspace or change its tenant identifier.

## 9. Different-Tenant Activation

Before activating tenant B on a device previously used by tenant A:

- Tenant A must be deactivated and archived.
- All active local sessions for tenant A must be closed safely.
- Pending results must be uploaded, exported, or explicitly marked unresolved.
- Tenant A's workspace must be mounted read-only or moved to an encrypted archive.
- Tenant B must receive a new workspace ID and a new local database, unless a verified tenant-safe multi-workspace store is implemented.
- Tenant B must provide its own activation code and administrator credentials.
- The new activation response must be checked for tenant identity before the workspace is created.

The application must reject activation if the requested tenant identity conflicts with the selected workspace. It must not simply overwrite `organization_id`, `center_id`, or an account label in an existing database.

The preferred layout is:

```text
workspaces/
  workspace-a/
    database.sqlite
    secrets.enc
    packages/
    uploads/
    audit/
  workspace-b/
    database.sqlite
    secrets.enc
    packages/
    uploads/
    audit/
```

A single laptop may have multiple archived workspaces, but only one workspace should be mounted for normal operation at a time unless the client has a clearly implemented workspace switcher.

## 10. Local Database and Tenant Isolation

Every tenant-sensitive local table must carry a workspace or activation boundary. At minimum, the local schema must identify:

- `workspace_id`.
- `tenant_type`.
- `tenant_id`.
- `activation_id` where the record was produced by a specific activation.
- `created_at` and `updated_at`.

This applies to:

- Exams and exam packages.
- Candidates and candidate groups.
- Attempts, answers, papers, and local scores.
- Proctoring events and incidents.
- Upload queue records and receipts.
- Export records and package manifests.
- Local users and administrator sessions.
- Audit records.

The application must enforce the workspace boundary in repositories and services, not only in UI filters. A query without an active workspace must fail closed. A workspace switch must invalidate cached queries, sessions, tokens, and in-memory tenant objects.

Cloud identifiers must remain the source of truth. Local IDs must not be reused across workspaces when an upload contract requires a portal ID.

## 11. Data Retention

Normal deactivation retains:

- Completed exams.
- Candidate answers and papers.
- Submitted and accepted result evidence.
- Pending upload payloads.
- Upload receipts and conflicts.
- Audit logs.
- Package manifests and checksums.
- Deactivation and reactivation history.

Normal deactivation must not delete:

- The SQLite database.
- Candidate records.
- Answers or papers.
- Upload queue entries.
- Receipts.
- Audit logs.
- Encryption keys required to open the archive, unless an explicit secure-destruction operation is requested.

A separate **Erase Workspace** operation may delete local data only after:

- The administrator confirms the exact workspace and tenant.
- The application displays unresolved uploads and unfinished records.
- A final encrypted backup is created or the administrator explicitly declines it.
- The user enters a confirmation phrase.
- The operation is audited locally and, when possible, remotely.
- The application verifies that the current account is authorized to erase the workspace.

Erasure must not delete portal records. It only removes local copies.

## 12. Offline and Connectivity Rules

Normal deactivation requires connectivity because remote revocation must be confirmed. If connectivity is unavailable:

- The administrator may request **Prepare for deactivation**, which archives locally but does not claim remote revocation.
- The workspace enters `deactivation_pending` or `deactivated_local_only`.
- Protected sync and upload operations remain disabled or limited according to the configured emergency policy.
- The application must display that remote revocation is still pending.
- A later connection must reconcile the remote activation and produce a final receipt.

The server must not allow a locally deactivated workspace to continue producing new official exam packages indefinitely. Any emergency offline grace period must be short, explicit, configurable, and audited.

## 13. Remote Activation Records

The portal must preserve activation history rather than overwrite it.

Recommended fields for `offline_server_activations`:

- `offline_activation_code_id`.
- `workspace_id` or a stable local workspace reference.
- `organization_id`.
- `institution_id` when institution scope is supported.
- `cbt_center_id`.
- `device_id`.
- `admin_email`.
- `license_key_hash` or protected license identifier.
- `status`: `activated`, `deactivation_pending`, `deactivated`, `revoked`, or `expired`.
- `activated_at`.
- `deactivated_at`.
- `expires_at`.
- `deactivation_reason`.
- `last_seen_at`.
- `request_payload` with secrets excluded or redacted.
- `archive_checksum` when supplied.

The plaintext activation code must not be stored in request logs. Existing encrypted display storage must remain protected and must not be sent to the local client except through the intended activation workflow.

## 14. Authorization

The following actions require an authenticated local administrator:

- Deactivate the current workspace.
- Reactivate a workspace.
- Activate a different tenant.
- Restore an archive.
- Erase a workspace.
- Export or inspect unresolved sensitive data.

The portal must verify:

- The administrator account is active.
- The credentials are correct.
- The account may access the activation code's organization, institution, or center.
- The activation code is active and not expired or revoked.
- The code has available device capacity, excluding the same active device where appropriate.

A super administrator may revoke or remove a device remotely. Remote reset must not silently erase local data or mark an upload as accepted.

## 15. Security and Secrets

- Store the license key in the platform's protected local secret store where available.
- Do not place activation codes or passwords in ordinary SQLite tables, logs, crash reports, or exported CSV files.
- Redact secrets from request payloads and diagnostic bundles.
- Encrypt workspace archives and sensitive local packages.
- Bind protected API calls to both license key and device ID.
- Rotate the license key after reactivation unless a documented same-activation restore is explicitly chosen.
- Reject stale license keys after revocation.
- Use HTTPS for portal communication except approved loopback development.
- Keep archive checksums and audit entries tamper-evident where practical.
- Do not make the local package or ASAR the security boundary; activation and upload authorization remain server-controlled.

## 16. Audit Events

At minimum, record these events locally and remotely when connectivity permits:

- `deactivation_preflight_started`.
- `deactivation_preflight_blocked`.
- `deactivation_prepared`.
- `deactivation_requested`.
- `deactivation_confirmed`.
- `deactivation_remote_failed`.
- `deactivation_local_only`.
- `workspace_archived`.
- `workspace_restored`.
- `reactivation_requested`.
- `reactivation_succeeded`.
- `reactivation_failed`.
- `tenant_switch_requested`.
- `tenant_switch_succeeded`.
- `workspace_erase_requested`.
- `workspace_erased`.
- `remote_device_revoked`.
- `stale_license_rejected`.
- `pending_upload_overridden`.

Each event should include actor identity, role, workspace ID, tenant identity, activation ID, device ID, timestamp, result, reason, and a redacted payload summary.

## 17. User Experience Requirements

The client must clearly distinguish:

- Deactivate this workspace.
- Archive this workspace.
- Activate another account.
- Reactivate this workspace.
- Erase local workspace data.
- Remote device reset by an administrator.

The deactivation screen must show:

- Current tenant and workspace.
- Current license status and expiry.
- Last cloud sync.
- Pending uploads.
- Unfinished exams.
- Archive location and checksum after completion.
- Whether remote revocation succeeded.
- A clear next action.

The client must never use vague messages such as “database cleared” or “activation removed” when data is actually retained. The user must know whether they can restore the workspace and whether a new activation code is required.

## 18. Error and Recovery Behavior

### Upload failure

Keep the record pending or failed with its original immutable payload. Allow retry after connectivity or validation recovery. Do not delete it during deactivation.

### Remote revocation failure

Do not report complete deactivation. Keep the workspace in a controlled pending state and provide retry. If local data has already been archived, preserve the archive and prevent accidental activation over it.

### Activation code rejected

Do not alter the selected workspace. Do not overwrite tenant metadata. Return the workspace to `activation_required` or leave the previous workspace archived.

### Device limit reached

Explain that an administrator must remove or reset an old device, or use an activation code with an available slot. Do not automatically revoke another device.

### Corrupt archive or database

Block restore, preserve the original files, record the integrity failure, and offer a diagnostic export that excludes secrets. Do not attempt an unverified merge.

### Application crash during transition

On restart, inspect the durable transition record. Resume, roll back, or present a recovery state deterministically. Never infer success only from an in-memory flag.

## 19. API and Storage Contracts

The implementation should add explicit operations equivalent to:

- `POST /api/offline/deactivation-preflight`.
- `POST /api/offline/deactivate`.
- `POST /api/offline/reactivate`.
- `POST /api/offline/workspaces/switch`.
- `POST /api/offline/workspaces/archive`.
- `POST /api/offline/workspaces/restore`.
- `POST /api/offline/workspaces/erase`.

Names may follow the client application's existing local API conventions. Every write must have an idempotency key and durable transition record.

The deactivation response must distinguish:

- `remote_revoked`.
- `local_archived`.
- `pending_uploads`.
- `unresolved_records`.
- `workspace_id`.
- `activation_id`.
- `archive_checksum`.
- `next_action`.

Do not return plaintext activation codes, passwords, or license secrets in diagnostic responses.

## 20. Implementation Order

1. Add workspace identity and tenant-boundary tables/columns to the local server.
2. Add a local activation state machine and durable transition journal.
3. Add preflight checks for active exams, pending uploads, blocked records, and integrity.
4. Add remote deactivation/revocation endpoint and audit events.
5. Add encrypted archive creation and restore verification.
6. Add same-tenant reactivation using an existing activation code.
7. Add separate workspace creation for different-tenant activation.
8. Add local workspace list and safe workspace switching.
9. Add explicit erase flow with safeguards.
10. Add portal activation history, deactivation timestamps, workspace references, and institution scope where required.
11. Add UI, recovery states, operational support tools, and documentation.
12. Package and test the client only after the portal and local schemas are backward-compatible.

## 21. Acceptance Tests

### Authorization and identity

- An unauthorized admin cannot deactivate, reactivate, restore, switch, or erase a workspace.
- An admin cannot activate another tenant using a code outside their scope.
- A stale or revoked license key cannot access protected portal APIs.
- A device ID cannot be used to impersonate another workspace.

### Data safety

- Deactivation preserves the SQLite database, pending uploads, completed attempts, receipts, and audit log.
- Deactivation does not delete or modify accepted portal results.
- Erase requires explicit confirmation and never deletes cloud records.
- A corrupt archive is rejected without deleting the original workspace.

### Reactivation

- Same-tenant reactivation accepts the existing activation code and does not require generating a new code.
- Same-tenant reactivation creates a new activation history row or follows the approved restore policy.
- The old license key is rejected after deactivation.
- A different tenant requires its own activation code and creates a separate workspace.
- Tenant A records are not visible in tenant B's workspace.

### Pending operations

- Deactivation blocks while a candidate is actively writing under the normal policy.
- Deactivation reports pending and blocked uploads accurately.
- Retry after a failed upload preserves the original payload and idempotency identity.
- Remote revocation failure produces a recoverable pending state.
- Application restart during deactivation resumes deterministically.

### Offline behavior

- Local-only preparation does not falsely claim remote revocation.
- Offline server APIs stop according to the configured deactivation state.
- Reconnection reconciles the remote activation and records the result.

### Operational verification

- Portal Manage Activation shows activation history and current status.
- Device reset revokes only the selected activation and does not erase local workspace data.
- Logs redact passwords, activation codes, and license keys.
- Backup restore verifies checksum and workspace identity before mounting.

## 22. Non-Goals

This policy does not authorize:

- Automatic deletion of local examination data.
- Merging two tenants into one database.
- Reusing a license key across accounts or devices.
- Silent migration of historical results between tenants.
- Offline activation without a verifiable authorization code, except a separately approved recovery mechanism.
- Treating a deactivated local workspace as proof that portal records were deleted.

The implementation should preserve existing offline result-upload contracts and traditional fixed-paper behavior while introducing this lifecycle.
