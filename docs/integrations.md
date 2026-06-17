# External AI & Storage Integrations

All external capabilities sit behind narrow **ports** in
[`src/Application/Provider/`](../apps/api/src/Application/Provider). The active
implementation is chosen by env; a deterministic **`fake`** implementation runs
the whole pipeline with no paid calls in dev/tests (spec §6, §10).

> Status legend: shipped today vs. **Planned (Mx)**. Real providers shipped:
> **STT = Deepgram (M3)** via `SttClientFactory`, **LLM scoring = OpenAI (M4)** via
> `ScoringClientFactory` (wrapped by `EvidenceValidatingScoringClient`). Embeddings
> are still a fake (real provider planned M5). The default for every provider
> remains `fake`, so the pipeline runs with no paid calls.

---

## 1. Ports

| Port | Method(s) | Returns |
|---|---|---|
| [`SpeechToTextClient`](../apps/api/src/Application/Provider/SpeechToTextClient.php) | `transcribe(AudioRef): TranscriptionResult` | full text + diarized segments |
| [`ScoringClient`](../apps/api/src/Application/Provider/ScoringClient.php) | `score(Transcript, ?Scorecard): ScoringResult` | overall score + per-criterion results |
| [`EmbeddingClient`](../apps/api/src/Application/Provider/EmbeddingClient.php) | `embed(string[]): float[][]`, `dimension(): int` | one vector per input text |
| [`ObjectStorage`](../apps/api/src/Application/Provider/ObjectStorage.php) | `put`, `get`, `exists`, `delete`, `presignedUrl` | audio blob storage |

`ObjectStorage::delete()` is idempotent (tolerant of an already-missing object);
`presignedUrl(key, ttl=600s)` produces a time-limited download URL for the
cabinet audio player.

---

## 2. DTOs

All in `src/Application/Provider/`, all `final readonly`.

### Input

- **`AudioRef`** — `objectKey: string`, `channels: Channels`,
  `language: string = 'auto'`. Everything the STT provider needs to fetch and
  transcribe the audio. Dual-channel audio is transcribed per channel; mono
  requests provider diarization.

### Transcription output

- **`TranscriptionResult`** — `language`, `fullText`, `segments:
  TranscriptSegment[]`, `provider`, `model`.
- **`TranscriptSegment`** — `speaker: Speaker`, `startMs: int`, `endMs: int`,
  `text: string`. One diarized segment.

### Scoring output

- **`ScoringResult`** — `overallScore: float`, `criteria:
  CriterionScoreResult[]`, `model: string`.
- **`CriterionScoreResult`** — `criterionKey: string`, `score: float`,
  `maxScore: int`, `evidenceQuote: ?string`, `rationale: ?string`.

### Embeddings output

- `embed()` returns `array<int, float[]>` — one embedding vector per input, in
  order. `dimension()` must match the `Utterance.embedding` pgvector column.

---

## 3. Fake implementations (deterministic)

In [`src/Infrastructure/Provider/Fake/`](../apps/api/src/Infrastructure/Provider/Fake).
All are stable across reprocessing so the pipeline yields identical results.

### `FakeSpeechToText`

Returns a fixed 6-turn Agent/Customer sales dialogue. Each segment's duration is
`2000 + len(text)*25` ms, laid end to end from a moving cursor. `fullText` is
the turns joined with `\n`, each prefixed `Agent: ` / `Customer: `. Language is
the request language, or `en` when `auto`. Emits `provider: 'fake'`,
`model: 'fake-stt-v1'`.

### `FakeScoring`

Default criteria when no scorecard: `greeting`, `needs_discovery`,
`objection_handling`, `next_step` (each `maxScore = 5`, `weight = 1.0`). When a
scorecard is provided and has criteria, it uses each criterion's key, maxScore,
and weight instead.

- Per-criterion score = `1 + (crc32(key) % maxScore)` — stable, in
  `1..maxScore`.
- `evidenceQuote` = the **first agent line** of the transcript, taken verbatim,
  so it always satisfies the "evidence must appear in transcript" rule.
- `overall` = weighted mean of `score/maxScore` × 100, rounded to 1 decimal.
- `rationale` = `"Deterministic fake score for development."`,
  `model: 'fake-llm-v1'`.

### `FakeEmbedding`

Produces a stable pseudo-vector per text, seeded from `crc32(text)`. Each
component is `((seed + i*2654435761) % 2000) / 1000 - 1.0`, i.e. deterministic
in `[-1, 1]`. Dimension comes from `EMBEDDING_DIM` (default `1024`); `dimension()`
returns it.

---

## 4. Provider selection (env)

Selectors and their default value `fake`:

| Capability | Env selector | Default | Values |
|---|---|---|---|
| STT | `AI_STT_PROVIDER` | `fake` | `deepgram` \| `assemblyai` \| `gladia` \| `fake` |
| LLM scoring | `AI_LLM_PROVIDER` | `fake` | `openai` \| `gemini` \| `anthropic` \| `fake` |
| Embeddings | `AI_EMBEDDINGS_PROVIDER` | `fake` | `openai` \| `voyage` \| `fake` |

Defined in [`.env.example`](../.env.example) (with `EMBEDDING_DIM=1024`).

> **Today:** [`config/services.yaml`](../apps/api/config/services.yaml) selects
> `SpeechToTextClient` via `SttClientFactory` from `AI_STT_PROVIDER`
> (`fake` | `deepgram`) and `ScoringClient` via `ScoringClientFactory` from
> `AI_LLM_PROVIDER` (`fake` | `openai`), then wraps scoring in
> `EvidenceValidatingScoringClient`. `EmbeddingClient` is still bound to its fake
> (selection **Planned M5**). Keys: Deepgram `DEEPGRAM_API_KEY`/`DEEPGRAM_MODEL`
> (`nova-3`); OpenAI `OPENAI_API_KEY`/`OPENAI_LLM_MODEL` (`gpt-4o-mini`).
>
> **Scoring (M4):** `OpenAiScoring` calls Chat Completions at **temperature 0** with
> a **strict JSON schema** ([`ScoringPromptBuilder`](../apps/api/src/Infrastructure/Provider/OpenAi/ScoringPromptBuilder.php)),
> scores **only the agent's turns**, iterates the scorecard criteria (clamping each
> score, computing a weighted overall %), and **validates every evidence quote
> against the transcript** — fabricated quotes are dropped (nulled), score/rationale kept.

---

## 5. Object storage (Flysystem + AsyncAws → MinIO / Hetzner)

[`FlysystemObjectStorage`](../apps/api/src/Infrastructure/Storage/FlysystemObjectStorage.php)
implements `ObjectStorage` over a Flysystem `FilesystemOperator`. The
`audio.storage` filesystem uses the **AsyncAws S3 adapter**
([`flysystem.yaml`](../apps/api/config/packages/flysystem.yaml)), with the
`AsyncAws\S3\S3Client` configured from `S3_*` env in
[`services.yaml`](../apps/api/config/services.yaml) (`S3_ENDPOINT`, `S3_KEY`,
`S3_SECRET`, `S3_REGION`, `S3_USE_PATH_STYLE`, bucket `S3_BUCKET`).

- **Dev:** MinIO (`S3_ENDPOINT=http://minio:9000`, path-style).
- **Prod:** Hetzner Object Storage (both S3-compatible).
- Keys are tenant-namespaced: `tenants/{tenantId}/calls/{callId}/audio.{ext}`
  (spec §7.2).
- `delete()` swallows `FilesystemException` (idempotent).
- `presignedUrl()` uses Flysystem `temporaryUrl`; if the adapter lacks signed-URL
  support it returns `''` — cabinet audio streaming is **Planned (M6)**.

---

## 6. Real providers, defaults & alternatives

| Capability | Default provider | Alternatives | Env selector | Status |
|---|---|---|---|---|
| STT | Deepgram | AssemblyAI, Gladia | `AI_STT_PROVIDER` | ✅ Deepgram shipped (M3); AssemblyAI/Gladia planned |
| LLM scoring | OpenAI (gpt-4o-mini default) | Google Gemini, Anthropic | `AI_LLM_PROVIDER` | ✅ OpenAI shipped (M4) + evidence validation; Gemini/Anthropic planned |
| Embeddings | OpenAI embeddings | Voyage | `AI_EMBEDDINGS_PROVIDER` | Planned (M5) — fake today |
| Object storage | Hetzner Object Storage (S3) | MinIO (dev) | `S3_*` | ✅ shipped (storage port + Flysystem) |

---

## 7. Scoring quality contract (M4 — implemented)

The `ScoringClient` honors (spec §10):

- ✅ Call the LLM at **temperature 0** (`OpenAiScoring`).
- ✅ Use **strict JSON** (structured output / JSON schema) for the result.
- ✅ Require an **evidence quote per criterion** and **validate it against the
  transcript** — enforced by `EvidenceValidatingScoringClient`, which nulls any
  quote that doesn't appear (case/whitespace-insensitive) so fabricated quotes
  are never persisted.
- ✅ Ground scoring only in the **agent's turns** (system prompt scores only
  `Agent:` lines).
- ⏳ A golden set + offline LLM-as-judge eval harness is **Planned** (regression
  guard for prompt changes).

`FakeScoring` honors the same evidence rule (it quotes the first agent line
verbatim), so it stays a faithful stand-in when `AI_LLM_PROVIDER=fake`.
