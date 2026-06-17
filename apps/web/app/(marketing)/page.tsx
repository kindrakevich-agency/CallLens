import Link from "next/link";
import { Wave } from "@/components/Brand";
import { HeroCanvas } from "@/components/HeroCanvas";
import { DemoTabs } from "@/components/DemoTabs";

const gridBg = {
  backgroundImage: "radial-gradient(rgba(255,255,255,.06) 1px, transparent 1px)",
  backgroundSize: "22px 22px",
};

const INTEGRATIONS = ["Twilio", "Binotel", "Ringostat", "Bitrix24", "REST API"];

const FEATURES = [
  {
    title: "Transcription & speaker separation",
    body: "Every call is transcribed with word-level timestamps and split into rep vs. customer — automatically, in any language.",
    icon: IconWaveform,
  },
  {
    title: "AI scoring with evidence",
    body: "Each rep is graded against your scorecard, and every score quotes the exact line from the transcript. Fabricated quotes are stripped out.",
    icon: IconTarget,
  },
  {
    title: "Semantic search",
    body: "Search by meaning across every conversation — “price objection”, “asked for a discount” — not just keywords.",
    icon: IconSearch,
  },
  {
    title: "Reports & trends",
    body: "Average score per rep, quality trend by week, top customer objections — on a fast analytics layer over PostgreSQL.",
    icon: IconChart,
  },
  {
    title: "Custom scorecards",
    body: "Define your own criteria, weights and guidance for the AI. Versioned and editable any time, no engineer required.",
    icon: IconList,
  },
  {
    title: "Team & roles",
    body: "Invite managers, assign owner / admin / manager / viewer roles, and keep every workspace fully isolated.",
    icon: IconUsers,
  },
];

const STEPS = [
  ["01", "Sign up", "Create a workspace for your team and invite your sales managers."],
  ["02", "Connect a source", "Drop your CallLens webhook URL into your phone system or CRM — recordings start flowing in on their own."],
  ["03", "Set your checklist", "Describe what a great call looks like: greeting, needs discovery, objection handling, next step."],
  ["04", "Review the scores", "Every new call is transcribed, scored and waiting in your dashboard with per-rep reports."],
];

const TRUST = [
  ["Signed webhooks", "Every incoming call is verified with an HMAC-SHA256 signature — forged requests are rejected."],
  ["Tenant isolation", "Each workspace's data is partitioned by tenant at the database level. Nobody sees another team's calls."],
  ["EU / GDPR storage", "Audio lives in S3-compatible object storage in EU data centres, with retention rules you control."],
  ["Evidence-checked scoring", "Scores must cite a real quote from the transcript — anything the model invents is discarded."],
];

export default function Landing() {
  return (
    <div className="bg-white text-ink">
      {/* NAV */}
      <header className="sticky top-0 z-40 border-b border-slate-200 bg-white/85 backdrop-blur">
        <div className="mx-auto flex h-16 max-w-6xl items-center justify-between px-5">
          <Link href="/" className="flex items-center gap-2.5">
            <span className="text-brand-600">
              <Wave animate />
            </span>
            <span className="font-display text-lg font-bold tracking-tight text-ink">CallLens</span>
          </Link>
          <nav className="hidden items-center gap-7 text-sm text-slate-600 lg:flex">
            <a href="#features" className="hover:text-ink">Features</a>
            <a href="#flow" className="hover:text-ink">How it works</a>
            <a href="#demo" className="hover:text-ink">Demo</a>
            <a href="#pricing" className="hover:text-ink">Why APIs</a>
            <Link href="/docs" className="hover:text-ink">Docs</Link>
          </nav>
          <div className="flex items-center gap-3">
            <Link href="/login" className="hidden text-sm font-medium text-slate-600 hover:text-ink sm:block">
              Sign in
            </Link>
            <Link
              href="/login"
              className="rounded-lg bg-ink px-4 py-2 text-sm font-medium text-white transition hover:bg-ink-700"
            >
              Connect your team
            </Link>
          </div>
        </div>
      </header>

      <main>
        {/* HERO */}
        <section className="relative overflow-hidden bg-ink text-white">
          <div className="absolute inset-0 opacity-70" style={gridBg} aria-hidden />
          <div className="absolute -right-24 -top-24 h-[480px] w-[480px] rounded-full bg-brand-600/20 blur-3xl" aria-hidden />
          <HeroCanvas />
          <div className="absolute inset-0 bg-gradient-to-r from-ink via-ink/75 to-transparent" aria-hidden />
          <div className="absolute inset-x-0 bottom-0 h-24 bg-gradient-to-t from-ink to-transparent" aria-hidden />
          <div className="relative mx-auto max-w-6xl px-5 pb-28 pt-24">
            <p className="mb-5 font-mono text-xs font-semibold uppercase tracking-[0.18em] text-brand-100/80">
              Call analytics for sales teams
            </p>
            <h1 className="max-w-3xl font-display text-4xl font-bold leading-[1.05] sm:text-5xl lg:text-6xl">
              Hear every sales call. Score every rep. Automatically.
            </h1>
            <p className="mt-6 max-w-2xl text-lg leading-relaxed text-slate-300">
              CallLens turns your team’s phone calls into searchable transcripts and objective,
              evidence-backed scores — so you can coach faster, close more deals, and never sit
              through a full recording again.
            </p>
            <div className="mt-9 flex flex-wrap items-center gap-3">
              <Link
                href="/login"
                className="inline-flex items-center rounded-lg bg-brand-600 px-5 py-3 font-medium transition hover:bg-brand-700"
              >
                Start free →
              </Link>
              <a
                href="#demo"
                className="inline-flex items-center gap-2 rounded-lg border border-white/25 px-5 py-3 font-medium text-slate-200 transition hover:bg-white/5"
              >
                <IconPlay className="h-4 w-4" /> Watch the demo
              </a>
            </div>
            <div className="mt-14 flex flex-wrap items-center gap-3 text-sm text-slate-300">
              <span className="text-brand-300">
                <Wave animate className="[&_span]:w-[3px]" />
              </span>
              <span className="font-mono text-xs text-slate-400">
                call → webhook → transcription → scoring → dashboard
              </span>
            </div>
          </div>
        </section>

        {/* INTEGRATIONS BAR */}
        <section className="border-b border-slate-200 bg-slate-50">
          <div className="mx-auto flex max-w-6xl flex-col items-center gap-4 px-5 py-6 sm:flex-row sm:justify-between">
            <p className="font-mono text-xs uppercase tracking-[0.18em] text-slate-500">
              Connects to the tools you already use
            </p>
            <div className="flex flex-wrap items-center justify-center gap-2.5">
              {INTEGRATIONS.map((name) => (
                <span
                  key={name}
                  className="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-600"
                >
                  {name}
                </span>
              ))}
            </div>
          </div>
        </section>

        {/* FEATURES */}
        <section id="features" className="mx-auto max-w-6xl px-5 py-24">
          <div className="max-w-2xl">
            <p className="mb-3 font-mono text-xs font-semibold uppercase tracking-[0.18em] text-brand-600">
              What you get
            </p>
            <h2 className="font-display text-3xl font-bold tracking-tight sm:text-4xl">
              Everything said on a call, turned into something you can act on
            </h2>
            <p className="mt-4 leading-relaxed text-slate-600">
              No more spot-checking a handful of recordings. CallLens reviews 100% of your calls the
              same way, every time — and shows its work.
            </p>
          </div>
          <div className="mt-12 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
            {FEATURES.map(({ title, body, icon: Icon }) => (
              <div
                key={title}
                className="group rounded-2xl border border-slate-200 bg-white p-6 transition duration-200 hover:-translate-y-1 hover:shadow-[0_18px_40px_-24px_rgba(12,27,42,0.35)]"
              >
                <div className="grid h-11 w-11 place-items-center rounded-xl bg-brand-50 text-brand-600">
                  <Icon className="h-5 w-5" />
                </div>
                <h3 className="mt-4 font-display text-lg font-semibold">{title}</h3>
                <p className="mt-2 text-sm leading-relaxed text-slate-600">{body}</p>
              </div>
            ))}
          </div>
        </section>

        {/* SOUND → SCORE (signature dark section) */}
        <section className="relative overflow-hidden bg-ink text-white">
          <div className="absolute inset-0 opacity-60" style={gridBg} aria-hidden />
          <div className="absolute -bottom-32 -left-24 h-[460px] w-[460px] rounded-full bg-brand-600/20 blur-3xl" aria-hidden />
          <div className="absolute -top-32 right-0 h-[380px] w-[380px] rounded-full bg-api-600/10 blur-3xl" aria-hidden />
          <div className="relative mx-auto max-w-6xl px-5 py-24">
            <div className="max-w-2xl">
              <p className="mb-3 font-mono text-xs font-semibold uppercase tracking-[0.18em] text-brand-300">
                From sound to insight
              </p>
              <h2 className="font-display text-3xl font-bold tracking-tight sm:text-4xl">
                How a conversation becomes a number
              </h2>
              <p className="mt-4 leading-relaxed text-slate-300">
                Four transformations: raw call audio turns, step by step, into a structured rep score
                and team-wide analytics.
              </p>
            </div>

            <div className="mt-12 flex flex-col lg:flex-row lg:items-stretch">
              <PipelineCard step="01 · Sound" caption="Raw call audio recording">
                <div className="grid flex-1 place-items-center rounded-xl border border-white/5 bg-ink-800/60 py-8">
                  <Wave animate className="scale-[1.6] text-brand-300" />
                </div>
              </PipelineCard>

              <Arrow />

              <PipelineCard step="02 · Text + roles" caption="Transcript with diarization">
                <div className="flex-1 space-y-2.5 rounded-xl border border-white/5 bg-ink-800/60 p-3">
                  <div>
                    <div className="mb-1 font-mono text-[10px] uppercase tracking-wider text-brand-300">Rep</div>
                    <div className="rounded-lg rounded-tl-none border border-brand-500/20 bg-brand-500/15 px-3 py-2 text-xs leading-relaxed text-slate-200">
                      Hi there! What’s most important to you right now?
                    </div>
                  </div>
                  <div>
                    <div className="mb-1 text-right font-mono text-[10px] uppercase tracking-wider text-slate-400">Customer</div>
                    <div className="rounded-lg rounded-tr-none border border-white/10 bg-white/5 px-3 py-2 text-xs leading-relaxed text-slate-200">
                      We want to cut our logistics costs.
                    </div>
                  </div>
                </div>
              </PipelineCard>

              <Arrow />

              <PipelineCard step="03 · Score" caption="Scored against your checklist">
                <div className="flex-1 space-y-3 rounded-xl border border-white/5 bg-ink-800/60 p-4">
                  <ScoreBar label="Greeting" value={90} tone="emerald" text="9/10" />
                  <ScoreBar label="Needs discovery" value={80} tone="emerald" text="8/10" />
                  <ScoreBar label="Objection handling" value={50} tone="api" text="5/10" />
                  <p className="border-t border-white/5 pt-1 text-[11px] italic leading-relaxed text-slate-400">
                    “…I’ll send over the proposal today” — supporting quote
                  </p>
                </div>
              </PipelineCard>

              <Arrow />

              <PipelineCard step="04 · Analytics" caption="Average scores across the team">
                <div className="grid flex-1 place-items-center rounded-xl border border-white/5 bg-ink-800/60 p-3">
                  <svg viewBox="0 0 180 110" className="w-full" role="img" aria-label="Bar chart of average scores by rep">
                    <line x1="14" y1="92" x2="172" y2="92" stroke="rgba(255,255,255,.18)" strokeWidth="1" />
                    <rect x="22" y="52" width="20" height="40" rx="3" fill="#5EEAD4" />
                    <rect x="54" y="34" width="20" height="58" rx="3" fill="#5EEAD4" />
                    <rect x="86" y="62" width="20" height="30" rx="3" fill="#FBBF24" />
                    <rect x="118" y="44" width="20" height="48" rx="3" fill="#5EEAD4" />
                    <rect x="150" y="26" width="20" height="66" rx="3" fill="#5EEAD4" />
                    <g fontFamily="var(--font-mono)" fontSize="8" fill="rgba(255,255,255,.45)" textAnchor="middle">
                      <text x="32" y="104">R1</text>
                      <text x="64" y="104">R2</text>
                      <text x="96" y="104">R3</text>
                      <text x="128" y="104">R4</text>
                      <text x="160" y="104">R5</text>
                    </g>
                  </svg>
                </div>
              </PipelineCard>
            </div>
          </div>
        </section>

        {/* HOW IT WORKS */}
        <section id="flow" className="mx-auto max-w-6xl px-5 py-24">
          <div className="max-w-2xl">
            <p className="mb-3 font-mono text-xs font-semibold uppercase tracking-[0.18em] text-brand-600">
              Getting started
            </p>
            <h2 className="font-display text-3xl font-bold tracking-tight sm:text-4xl">
              Up and running in four steps
            </h2>
            <p className="mt-4 leading-relaxed text-slate-600">
              Connect your source once — everything after that runs without you.
            </p>
          </div>
          <ol className="mt-12 grid gap-5 md:grid-cols-2 lg:grid-cols-4">
            {STEPS.map(([n, title, body], i) => (
              <li
                key={n}
                className={`rounded-2xl border bg-white p-6 transition duration-200 hover:-translate-y-1 hover:shadow-[0_18px_40px_-24px_rgba(12,27,42,0.35)] ${
                  i === 1 ? "border-slate-200 ring-1 ring-brand-100" : "border-slate-200"
                }`}
              >
                <div className="font-mono text-sm font-medium text-brand-600">{n}</div>
                <h3 className="mt-3 font-display text-lg font-semibold">{title}</h3>
                <p className="mt-2 text-sm leading-relaxed text-slate-600">{body}</p>
              </li>
            ))}
          </ol>
        </section>

        {/* DEMO */}
        <section id="demo" className="border-y border-slate-200 bg-slate-50">
          <div className="mx-auto max-w-6xl px-5 py-24">
            <div className="grid items-start gap-12 lg:grid-cols-2">
              <div className="lg:pt-6">
                <p className="mb-3 font-mono text-xs font-semibold uppercase tracking-[0.18em] text-brand-600">
                  See it on a real call
                </p>
                <h2 className="font-display text-3xl font-bold tracking-tight sm:text-4xl">
                  Watch CallLens score an actual 2-minute sales call
                </h2>
                <p className="mt-4 leading-relaxed text-slate-600">
                  We took a genuine two-person sales call, uploaded it, and let the pipeline run end to
                  end â transcription, speaker separation, and scoring. Here’s the demo, plus the raw
                  call it ran on.
                </p>
                <dl className="mt-8 grid grid-cols-2 gap-4">
                  <Stat value="2 speakers" label="separated automatically (rep / customer)" />
                  <Stat value="75 / 100" label="overall score, with quoted evidence" />
                  <Stat value="22 turns" label="aligned transcript, no diarization drift" />
                  <Stat value="~2 min" label="processed in under a minute" />
                </dl>
              </div>
              <DemoTabs />
            </div>
          </div>
        </section>

        {/* WHY APIs / PRICING ANGLE */}
        <section id="pricing" className="mx-auto max-w-6xl px-5 py-24">
          <div className="grid items-start gap-10 lg:grid-cols-2">
            <div>
              <p className="mb-3 font-mono text-xs font-semibold uppercase tracking-[0.18em] text-api-600">
                Enterprise AI, startup economics
              </p>
              <h2 className="font-display text-3xl font-bold tracking-tight sm:text-4xl">
                State-of-the-art models — without a GPU bill
              </h2>
              <p className="mt-4 leading-relaxed text-slate-600">
                The heavy AI — speech-to-text, scoring, embeddings — runs on best-in-class paid APIs
                behind swappable interfaces. There’s no GPU to operate, so the whole product runs on a
                single dedicated server and infrastructure stays cheap. At launch volumes, APIs cost
                <span className="font-semibold text-ink"> 3–4× less</span> than self-hosting.
              </p>
              <ul className="mt-6 space-y-3 text-sm">
                {[
                  "Zero DevOps for inference — pay only for what you use",
                  "Scales instantly under load, no capacity planning",
                  "EU / GDPR-friendly storage and processing",
                  "Swap providers (or move to your own GPU) with no rewrite",
                ].map((t) => (
                  <li key={t} className="flex gap-3">
                    <IconCheck className="mt-0.5 h-4 w-4 flex-none text-brand-600" />
                    <span className="text-slate-600">{t}</span>
                  </li>
                ))}
              </ul>
            </div>

            <div className="rounded-2xl border border-slate-200 bg-slate-50 p-6">
              <div className="mb-4 font-mono text-[11px] uppercase tracking-wider text-slate-400">
                Monthly cost at launch (≈ 10,000 calls)
              </div>
              <div className="space-y-4">
                <CostBar label="Paid APIs" amount="≈ $415" pct={25} tone="brand" />
                <CostBar label="Your own GPU" amount="≈ $1,670" pct={100} tone="slate" />
              </div>
              <div className="mt-5 grid grid-cols-2 gap-4 border-t border-slate-200 pt-5">
                <Stat value="≈ €50–65" label="total server + storage per month" small />
                <Stat value="No GPU" label="just web, workers, database, queue" small />
              </div>
            </div>
          </div>
        </section>

        {/* TRUST / SECURITY */}
        <section className="border-y border-slate-200 bg-slate-50">
          <div className="mx-auto max-w-6xl px-5 py-24">
            <div className="max-w-2xl">
              <p className="mb-3 font-mono text-xs font-semibold uppercase tracking-[0.18em] text-brand-600">
                Built to be trusted
              </p>
              <h2 className="font-display text-3xl font-bold tracking-tight sm:text-4xl">
                Your calls, handled with care
              </h2>
            </div>
            <div className="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
              {TRUST.map(([title, body]) => (
                <div key={title} className="rounded-2xl border border-slate-200 bg-white p-6">
                  <div className="grid h-10 w-10 place-items-center rounded-lg bg-brand-50 text-brand-600">
                    <IconShield className="h-5 w-5" />
                  </div>
                  <h3 className="mt-4 font-display font-semibold">{title}</h3>
                  <p className="mt-2 text-sm leading-relaxed text-slate-600">{body}</p>
                </div>
              ))}
            </div>
          </div>
        </section>

        {/* CTA */}
        <section className="relative overflow-hidden bg-ink text-white">
          <div className="absolute inset-0 opacity-60" style={gridBg} aria-hidden />
          <div className="absolute -right-20 -top-24 h-[360px] w-[360px] rounded-full bg-brand-600/20 blur-3xl" aria-hidden />
          <div className="relative mx-auto flex max-w-6xl flex-col items-start justify-between gap-6 px-5 py-20 md:flex-row md:items-center">
            <div>
              <h2 className="font-display text-2xl font-bold sm:text-3xl">Ready to connect your sales team?</h2>
              <p className="mt-2 text-slate-300">Sign up, add a webhook, set your checklist — and your first scores land today.</p>
            </div>
            <Link
              href="/login"
              className="inline-flex items-center rounded-lg bg-brand-600 px-6 py-3 font-medium transition hover:bg-brand-700"
            >
              Get started →
            </Link>
          </div>
        </section>
      </main>

      {/* FOOTER */}
      <footer className="border-t border-slate-200">
        <div className="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 px-5 py-10 text-sm text-slate-500 sm:flex-row">
          <div className="flex items-center gap-2.5">
            <span className="text-brand-600">
              <Wave />
            </span>
            <span className="font-display font-semibold text-ink">CallLens</span>
          </div>
          <p>
            Call analytics for sales teams · <Link href="/docs" className="hover:text-ink">docs</Link> ·{" "}
            <Link href="/login" className="hover:text-ink">sign in</Link>
          </p>
        </div>
      </footer>
    </div>
  );
}

/* ---------- small presentational helpers ---------- */

function PipelineCard({ step, caption, children }: { step: string; caption: string; children: React.ReactNode }) {
  return (
    <div className="flex flex-col rounded-2xl border border-white/10 bg-white/[0.04] p-5 lg:min-w-0 lg:flex-1">
      <div className="font-mono text-xs text-brand-300">{step}</div>
      <div className="mt-4 flex flex-1 flex-col">{children}</div>
      <p className="mt-3 text-sm text-slate-400">{caption}</p>
    </div>
  );
}

function Arrow() {
  return (
    <div className="flex items-center justify-center py-3 text-white/25 lg:px-1 lg:py-0" aria-hidden>
      <svg viewBox="0 0 24 24" className="h-5 w-5 rotate-90 lg:rotate-0" fill="none" stroke="currentColor" strokeWidth={2}>
        <path d="M5 12h14M13 6l6 6-6 6" />
      </svg>
    </div>
  );
}

function ScoreBar({ label, value, tone, text }: { label: string; value: number; tone: "emerald" | "api"; text: string }) {
  const bar = tone === "emerald" ? "bg-emerald-400" : "bg-api-300";
  const txt = tone === "emerald" ? "text-emerald-300" : "text-api-300";
  return (
    <div>
      <div className="mb-1 flex items-center justify-between text-xs">
        <span className="text-slate-300">{label}</span>
        <span className={`font-mono ${txt}`}>{text}</span>
      </div>
      <div className="h-1.5 overflow-hidden rounded-full bg-white/10">
        <div className={`h-full rounded-full ${bar}`} style={{ width: `${value}%` }} />
      </div>
    </div>
  );
}

function Stat({ value, label, small = false }: { value: string; label: string; small?: boolean }) {
  return (
    <div>
      <div className={`font-display font-bold ${small ? "text-xl" : "text-2xl"}`}>{value}</div>
      <div className="mt-1 text-xs leading-relaxed text-slate-500">{label}</div>
    </div>
  );
}

function CostBar({ label, amount, pct, tone }: { label: string; amount: string; pct: number; tone: "brand" | "slate" }) {
  const bar = tone === "brand" ? "bg-brand-500" : "bg-slate-400";
  const txt = tone === "brand" ? "text-brand-700" : "text-slate-600";
  return (
    <div>
      <div className="mb-1 flex justify-between text-sm">
        <span className="font-medium">{label}</span>
        <span className={`font-mono ${txt}`}>{amount}</span>
      </div>
      <div className="h-3 overflow-hidden rounded-full bg-slate-200">
        <div className={`h-full rounded-full ${bar}`} style={{ width: `${pct}%` }} />
      </div>
    </div>
  );
}

/* ---------- icons (inline, stroke = currentColor) ---------- */

type IconProps = { className?: string };

function IconWaveform({ className }: IconProps) {
  return (
    <svg viewBox="0 0 24 24" className={className} fill="none" stroke="currentColor" strokeWidth={2} strokeLinecap="round">
      <path d="M4 12v0M8 8v8M12 4v16M16 8v8M20 12v0" />
    </svg>
  );
}
function IconTarget({ className }: IconProps) {
  return (
    <svg viewBox="0 0 24 24" className={className} fill="none" stroke="currentColor" strokeWidth={2}>
      <circle cx="12" cy="12" r="9" />
      <circle cx="12" cy="12" r="4.5" />
      <circle cx="12" cy="12" r="1" fill="currentColor" />
    </svg>
  );
}
function IconSearch({ className }: IconProps) {
  return (
    <svg viewBox="0 0 24 24" className={className} fill="none" stroke="currentColor" strokeWidth={2} strokeLinecap="round">
      <circle cx="11" cy="11" r="7" />
      <path d="m20 20-3.5-3.5" />
    </svg>
  );
}
function IconChart({ className }: IconProps) {
  return (
    <svg viewBox="0 0 24 24" className={className} fill="none" stroke="currentColor" strokeWidth={2} strokeLinecap="round">
      <path d="M5 21V10M12 21V4M19 21v-7" />
    </svg>
  );
}
function IconList({ className }: IconProps) {
  return (
    <svg viewBox="0 0 24 24" className={className} fill="none" stroke="currentColor" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round">
      <path d="m3 6 1.5 1.5L7 5M3 12l1.5 1.5L7 11M3 18l1.5 1.5L7 17M11 6h10M11 12h10M11 18h10" />
    </svg>
  );
}
function IconUsers({ className }: IconProps) {
  return (
    <svg viewBox="0 0 24 24" className={className} fill="none" stroke="currentColor" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round">
      <circle cx="9" cy="8" r="3.2" />
      <path d="M3.5 19a5.5 5.5 0 0 1 11 0M16 5.5a3.2 3.2 0 0 1 0 6M17.5 19a5.5 5.5 0 0 0-3-4.9" />
    </svg>
  );
}
function IconShield({ className }: IconProps) {
  return (
    <svg viewBox="0 0 24 24" className={className} fill="none" stroke="currentColor" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round">
      <path d="M12 3l7 3v5c0 4.4-3 7.8-7 9-4-1.2-7-4.6-7-9V6l7-3Z" />
      <path d="m9 12 2 2 4-4" />
    </svg>
  );
}
function IconCheck({ className }: IconProps) {
  return (
    <svg viewBox="0 0 24 24" className={className} fill="none" stroke="currentColor" strokeWidth={2.5} strokeLinecap="round" strokeLinejoin="round">
      <path d="m5 12 4.5 4.5L19 7" />
    </svg>
  );
}
function IconPlay({ className }: IconProps) {
  return (
    <svg viewBox="0 0 24 24" className={className} fill="currentColor" aria-hidden>
      <path d="M8 5v14l11-7z" />
    </svg>
  );
}
