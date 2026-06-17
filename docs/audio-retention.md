# Audio Retention & Deletion

Audio files are the largest artifact and are not needed once the transcript,
scores, and embeddings are persisted. Retention is **configuration-driven**,
with a global env default and a per-tenant override (README §9).

> **Status: Implemented (M8).** Retention is config-driven and enforced:
> `RetentionPolicyResolver` resolves the effective policy (per-tenant
> `settings.audio_retention` over env `AUDIO_RETENTION_*`); `delete_after_processing`
> dispatches a `DeleteAudioMessage` from `EmbedCallHandler` once a call completes;
> `delete_after_days` runs a daily `AudioRetentionSweep` (Symfony Scheduler,
> 03:00) that queues deletions for calls past their window. `DeleteAudioHandler`
> removes the object (idempotent), nulls the key, sets `audio_deleted_at`, and
> writes a ProcessingEvent + `audio.deleted` AuditLog. The cabinet already shows
> the "audio deleted" state, and Settings edits the policy.

---

## 1. Configuration model

Resolution order: **per-tenant `settings.audio_retention`** overrides the
**global env default**.

| Key | Type | Values | Meaning |
|---|---|---|---|
| `mode` | enum | `keep` \| `delete_after_processing` \| `delete_after_days` | Retention strategy. |
| `days` | int | e.g. `30` | Used only when `mode = delete_after_days`. |

Global env defaults (from [`.env.example`](../.env.example)):

```
AUDIO_RETENTION_MODE=keep      # keep|delete_after_processing|delete_after_days
AUDIO_RETENTION_DAYS=30
```

A tenant whose `settings.audio_retention` is unset inherits these env values.

---

## 2. Modes

- **`keep`** — audio is never auto-deleted.
- **`delete_after_processing`** — once a call reaches `completed`, the object is
  removed immediately (a `DeleteAudioMessage` is dispatched from `EmbedCallHandler`).
- **`delete_after_days`** — a daily **Symfony Scheduler** task
  (`AudioRetentionSweep`, 03:00) finds `completed` calls older than `days` (by
  `created_at`) that still have an `audio_object_key`, and queues deletion in
  batches.

---

## 3. Invariants

- **Never delete audio before the call is `completed`** (transcript + scores +
  embeddings persisted) — unless the call permanently `failed` and a separate
  `failed_audio_retention` policy applies.
- **On deletion:** remove the storage object, set `audio_deleted_at = now()`,
  **null `audio_object_key`**, and write a `ProcessingEvent` / `AuditLog` entry.
- **Deletion is idempotent** and tolerant of an already-missing object (the
  `ObjectStorage::delete()` port already guarantees this — see
  [integrations.md](./integrations.md)).
- **The UI must clearly show when audio has been deleted** ("audio deleted");
  the transcript and scores remain available.

---

## 4. Implementation (M8)

- [`RetentionPolicyResolver`](../apps/api/src/Application/Retention/RetentionPolicyResolver.php)
  — resolves the effective `RetentionPolicy` (mode + days): per-tenant
  `settings.audio_retention` overrides env `AUDIO_RETENTION_MODE`/`_DAYS`; an
  invalid mode falls back to `keep`.
- [`EmbedCallHandler`](../apps/api/src/Application/Pipeline/EmbedCallHandler.php)
  — after a call reaches `completed`, dispatches `DeleteAudioMessage` when the
  policy is `delete_after_processing`.
- [`AudioRetentionSweepHandler`](../apps/api/src/Application/Pipeline/AudioRetentionSweepHandler.php)
  — handles the daily `AudioRetentionSweep` (cron `0 3 * * *` in `MainSchedule`):
  scans `CallRepository::completedWithAudio()` (unscoped — runs without a
  principal, spans all tenants) and queues `DeleteAudioMessage` for calls past
  their tenant's window.
- [`DeleteAudioHandler`](../apps/api/src/Application/Pipeline/DeleteAudioHandler.php)
  — deletes the object (idempotent), `markAudioDeleted()`, records a
  `ProcessingEvent` (`delete_audio`) and an `audio.deleted` `AuditLog`. Skips
  calls not yet `completed`.
- The cabinet call-detail page shows the **"audio deleted"** state; Settings
  edits the policy (`PUT /api/v1/settings/retention`).
- Tested by `RetentionPolicyResolverTest` and `AudioRetentionTest`.

---

## 5. Lifecycle (planned)

```
... → embedding → completed
                     │
                     ▼  resolve retention (tenant override → env default)
         ┌───────────┴───────────────────────────────┐
         │                                            │
   mode = keep                          mode = delete_after_processing
   (no-op)                              → DeleteAudioMessage (immediate)
                                                      │
   mode = delete_after_days                           ▼
   → AudioRetentionSweep (daily)            ObjectStorage.delete(key)
     picks completed calls older            Call.markAudioDeleted()
     than `days` with audio_object_key      (audio_deleted_at=now, key=null)
     → DeleteAudioMessage (batched)         ProcessingEvent / AuditLog
                                            UI: "audio deleted"
```

All paths converge on the same delete action, which is idempotent and never runs
before `completed` (per §3).
