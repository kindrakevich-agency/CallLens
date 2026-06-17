# ADR-0001 — API-first AI, no GPU

## Status

Accepted.

## Context

CallLens performs three kinds of heavy AI work: speech-to-text with diarization,
LLM scoring against a scorecard, and embedding generation for semantic search.
Running these in-house would require GPU hardware, which is expensive to rent or
own, complicates the deployment, and forces capacity planning before there is
volume to justify it.

The product's economics (README §1, §17) assume a single Hetzner dedicated server
(AMD CPU, no GPU, ~€46/mo). At expected MVP volumes, paid per-use AI APIs are
cheaper than reserving GPU capacity; the break-even for self-hosting is estimated
at ~215k audio-minutes/month (README §2.2).

## Decision

All heavy AI work runs through external HTTP APIs. **No GPU is required.** Each
capability sits behind a narrow interface in the application layer
(`Application/Provider`: a speech-to-text client, a scoring client, an embedding
client), with infrastructure adapters selected by env (`AI_STT_PROVIDER`,
`AI_LLM_PROVIDER`, `AI_EMBEDDINGS_PROVIDER`). A deterministic `fake` adapter
(`Infrastructure/Provider/Fake`) is the default, so the whole pipeline runs
end-to-end in dev/CI with no paid calls.

Default providers are Deepgram (STT), OpenAI (LLM scoring + embeddings), with
documented alternatives. These real clients are **Planned (M3–M5)**; only the
interfaces and `fake` adapters exist today.

## Consequences

- A single CPU-only dedicated server is sufficient; infra stays cheap and simple.
- Provider independence: any provider can be swapped, or later moved to self-hosted
  GPU, without touching business logic — the interface is the seam.
- Tests and local dev are deterministic and free via the `fake` adapters.
- Per-call latency and cost depend on third parties; each provider call must have
  timeouts, retries with backoff, and a circuit breaker (README §8, §10).
- Audio leaves the server to reach providers — handled within EU/GDPR constraints
  (README §16).
