# Frontend (Next.js)

The web frontend lives in `apps/web` and is a single **Next.js 16 (App Router)**
application built with **React 19** and **Tailwind CSS v4**. TypeScript is in
strict mode.

> **Status:** the app is scaffolding today. All three route areas exist as
> placeholder pages; the full landing/branding port and MDX docs site are
> **Planned (M9)** and the cabinet feature set is **Planned (M6+)**. Implemented
> pieces are called out explicitly below.

```
apps/web/
├─ app/
│  ├─ layout.tsx          # root layout (fonts, <html>/<body>)
│  ├─ globals.css         # Tailwind v4 entry
│  ├─ (marketing)/page.tsx   # "/"      — marketing landing (placeholder)
│  ├─ docs/page.tsx          # "/docs"  — documentation site (placeholder)
│  └─ app/page.tsx           # "/app"   — authenticated cabinet (placeholder)
└─ package.json           # next 16.2.9, react 19.2.4, tailwindcss ^4
```

`next dev` / `next build` / `next start` via npm scripts. There are no frontend
tests yet — Vitest (component) and Playwright (e2e) arrive in M6 (`npm test` is
currently a no-op stub).

## Route areas

One app, three areas (spec §13):

1. **Landing (`/`)** — corporate marketing page. Explains the product and links
   to docs and the cabinet. *Implemented:* a single placeholder page that
   already uses the brand palette (Ink background `#0C1B2A`, Teal accents). The
   full landing — full waveform motif, sections, responsive layout — is **ported
   from `doc/html/landing.html` in M9.**
2. **Docs (`/docs`)** — MDX documentation site with the same corporate styling:
   *How it works*, *How to connect* (webhook setup, dual-channel recommendation,
   manual upload), *What reports are* (metrics, Cube), *Cabinet guide*,
   *Security*, *API reference* (public subset), searchable. *Implemented:* a
   placeholder page only. The MDX pipeline and content are **Planned (M9).**
3. **Cabinet (`/app`, authenticated)** — the product workspace. *Implemented:* a
   placeholder page only. Auth & tenancy land in M1; the feature set is
   **Planned (M6+):**
   - Calls list (filters: agent, date, score, status) with semantic search.
   - Call detail: speaker-separated transcript, per-criterion scores with
     evidence quotes, overall score, audio player (or an "audio deleted" state).
   - Agents management.
   - Scorecard editor (criteria, weights, guidance; versioned).
   - Analytics dashboards (Cube): avg score per agent, trend over time, top
     objections, score distribution.
   - Settings: integrations/webhook (URL + signing secret, regenerate), audio
     retention policy, team & roles, profile.

Accessibility (keyboard, focus states, reduced motion), mobile-flawless
responsiveness, and TypeScript strict mode are baseline requirements for all
three areas.

## Design system

The design language is defined in `doc/html/branding.html` (interactive brand
guide) and `doc/html/landing.html` (reference landing). These are the source of
truth until the tokens are ported into Tailwind theme config in M9.

> **Note:** the current `app/layout.tsx` still ships the default `create-next-app`
> Geist fonts and metadata. Swapping in the brand fonts and tokens below is part
> of the M9 port.

### Colors

Three values carry meaning: **Ink** (base/background/text), **Teal** (primary
brand and actions), **Amber** (the marker for *external paid APIs* — STT/LLM/
embeddings calls).

| Role | Token | Hex |
| --- | --- | --- |
| Ink · base | Ink | `#0C1B2A` |
| Ink 800 | | `#0A1622` |
| Ink 700 | | `#10314A` |
| Ink 600 | | `#1B4763` |
| Teal · primary (●) | Teal 500 | `#0F766E` |
| Teal 600 | | `#0E6A63` |
| Teal 300 (on dark) | | `#5EEAD4` |
| Teal 100 | | `#CDE6E2` |
| Teal 50 | | `#E6F2F0` |
| Amber · external API (●) | Amber 300 | `#FBBF24` |
| Amber 600 | | `#B45309` |

Teal `#0F766E` is the primary on light backgrounds; Teal `#5EEAD4` is the
primary on Ink (dark) backgrounds. Amber is reserved for indicating external
paid-API activity and must not be used as a generic accent.

### Typography

Loaded via Google Fonts:

- **Space Grotesk** (400–700) — display / headings / the `CallLens` wordmark (Bold).
- **Inter** (400–700) — body / UI text.
- **IBM Plex Mono** (400–500) — labels, eyebrows, code, hex values, metadata.

### Waveform motif

The brand mark is an animated **sound wave** — the canonical form is **five
bars** — paired with the `CallLens` wordmark in Space Grotesk Bold. It
represents a live call being turned into data and is the recurring visual motif
across landing, docs, and cabinet. Do not stretch/skew it, change the bar count,
recolor it off-brand, or place it on busy backgrounds.

## Milestone summary

| Area | Status |
| --- | --- |
| App scaffold, three placeholder routes, brand palette on landing | Implemented |
| Tailwind theme tokens, brand fonts in `layout.tsx` | Planned (M9) |
| Full landing/branding port from `doc/html/` | Planned (M9) |
| MDX docs site | Planned (M9) |
| Cabinet: calls list/detail, scorecard editor, agents, search, analytics | Planned (M6+) |
| Vitest + Playwright | Planned (M6) |
