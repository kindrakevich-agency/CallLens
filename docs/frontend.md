# Frontend (Next.js)

The web frontend lives in `apps/web` and is a single **Next.js 16 (App Router)**
application built with **React 19** and **Tailwind CSS v4**. TypeScript is in
strict mode.

> **Status:** the **cabinet is built (M6)** — login, calls list/detail, semantic
> search, agents, scorecards and settings, talking to the API with cookie auth
> over CORS. The marketing landing and MDX docs site are still placeholders
> (**Planned M9**); a full scorecard editor and richer settings are refinements.

```
apps/web/
├─ app/
│  ├─ layout.tsx              # root layout — brand fonts (Space Grotesk/Inter/IBM Plex Mono)
│  ├─ globals.css             # Tailwind v4 + @theme brand tokens (ink/brand/api)
│  ├─ (marketing)/page.tsx    # "/"       — marketing landing (placeholder)
│  ├─ docs/page.tsx           # "/docs"   — documentation site (placeholder)
│  ├─ login/page.tsx          # "/login"  — sign in / create workspace
│  └─ app/                    # "/app/*"  — authenticated cabinet (M6)
│     ├─ layout.tsx           #   AuthProvider + guard + sidebar shell
│     ├─ calls/page.tsx       #   calls list (status filter)
│     ├─ calls/[id]/page.tsx  #   call detail: transcript + per-criterion scores + evidence
│     ├─ search/page.tsx      #   semantic search
│     ├─ agents/page.tsx      #   agents
│     ├─ scorecards/page.tsx  #   scorecards (read)
│     └─ settings/page.tsx    #   webhook endpoints + retention
├─ lib/    api.ts (fetch client, credentials), auth.tsx (AuthProvider/useAuth)
├─ components/  Sidebar, Brand (Logo/Wave/ScoreBadge/StatusBadge), PageHeader
└─ package.json               # next 16.2.9, react 19.2.4, tailwindcss ^4
```

The browser calls the API at `NEXT_PUBLIC_API_URL` (`:8081`) with
`credentials: "include"`; the API enables CORS-with-credentials for the SPA
origin (`CORS_ALLOW_ORIGIN`). Auth uses the JWT cookie set by `/auth/login`.

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
3. **Cabinet (`/app`, authenticated)** — the product workspace. **Implemented (M6):**
   - Calls list (status filter) with links to detail.
   - Call detail: speaker-separated transcript, per-criterion scores with
     evidence quotes + rationale, overall score, "audio deleted" state.
   - Semantic search over utterances.
   - Agents list; scorecards (read).
   - Settings: webhook endpoint URL + signing secret (regenerate/add) and the
     audio-retention policy.
   - *Refinements (later):* analytics dashboards (Cube, M7), a full scorecard
     editor, team & roles management, audio playback.

Accessibility (keyboard, focus states, reduced motion), mobile-flawless
responsiveness, and TypeScript strict mode are baseline requirements for all
three areas.

## Design system

The design language is defined in `doc/html/branding.html` (interactive brand
guide) and `doc/html/landing.html` (reference landing).

> **Status:** the brand tokens are now in the app — `app/globals.css` defines the
> Ink/Teal/Amber palette via Tailwind v4 `@theme` (so `bg-ink`, `text-brand-600`,
> etc. work), and `app/layout.tsx` loads Space Grotesk / Inter / IBM Plex Mono.
> The full landing/branding port is still M9.

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
| App scaffold + Tailwind v4 `@theme` brand tokens + brand fonts | ✅ Implemented |
| Cabinet: login, calls list/detail, semantic search, agents, scorecards, settings | ✅ Implemented (M6) |
| Cabinet analytics dashboards (Cube) | Planned (M7) |
| Full scorecard editor, team & roles, audio playback | Planned (later) |
| Full landing/branding port from `doc/html/` + MDX docs site | Planned (M9) |
| Vitest + Playwright frontend tests | Planned |

## Cabinet screenshots (M6)

| Calls | Call detail | Semantic search |
|---|---|---|
| ![Calls](images/cabinet-calls.png) | ![Detail](images/cabinet-call-detail.png) | ![Search](images/cabinet-search.png) |
