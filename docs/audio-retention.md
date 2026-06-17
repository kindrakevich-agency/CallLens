# Audio Retention & Deletion

Audio files are the largest artifact and are not needed once the transcript,
scores, and embeddings are persisted. Retention is **configuration-driven**,
with a global env default and a per-tenant override (README §9).

> **Status: the deletion behavior is Planned (M8).** Today only the `Call`
> entity supports the deletion mechanics (`markAudioDeleted()`,
> `audioObjectKey()` / `setAudioObjectKey()`, `audio_deleted_at` column) and the
> env defaults exist. There is **no** retention evaluation, no
> `DeleteAudioMessage` / `FinalizeCall`, and no scheduler sweep in the codebase
> yet. This document describes the designed contract.

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
  removed immediately (via `DeleteAudioMessage`, dispatched from the finalize
  step). **Planned (M8).**
- **`delete_after_days`** — a daily **Symfony Scheduler** task
  (`AudioRetentionSweep`) finds `completed` calls older than `days` (by
  `created_at`/`completed_at`) that still have an `audio_object_key`, and deletes
  them in batches. **Planned (M8).**

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

## 4. What exists today vs. Planned (M8)

### Exists today

[`Call`](../apps/api/src/Domain/Call/Call.php) supports the deletion mechanics:

- `audio_object_key` column with `audioObjectKey()` / `setAudioObjectKey()` and
  `isAudioAvailable()`.
- `audio_deleted_at` column (`DATETIME_IMMUTABLE`, nullable).
- `markAudioDeleted()` — stamps `audio_deleted_at = now()` **and** nulls
  `audio_object_key` in one call, exactly matching the deletion invariant.

Env defaults `AUDIO_RETENTION_MODE` / `AUDIO_RETENTION_DAYS` are present in
`.env.example`.

The pipeline's final stage
([`EmbedCallHandler`](../apps/api/src/Application/Pipeline/EmbedCallHandler.php))
notes in its docblock that "Audio-retention deletion is wired in M8".

### Planned (M8) — not yet in code

- Reading/resolving `settings.audio_retention` (tenant) over the env default.
- A `FinalizeCall` / `CompleteCallMessage` retention evaluation after
  `completed`, dispatching `DeleteAudioMessage`.
- `DeleteAudioMessage` + handler: delete object, `markAudioDeleted()`, write
  audit/`ProcessingEvent`.
- The `AudioRetentionSweep` Symfony Scheduler task for `delete_after_days`.
- The "audio deleted" UI state.

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
