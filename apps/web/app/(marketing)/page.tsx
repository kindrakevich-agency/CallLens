import Link from "next/link";
import { Wave } from "@/components/Brand";
import { HeroCanvas } from "@/components/HeroCanvas";

const STEPS = [
  ["01", "Connect a source", "Register a signed webhook for Twilio, Binotel, Ringostat or Bitrix24 — or upload recordings directly."],
  ["02", "We transcribe", "Each call is transcribed with speaker separation, so the rep and the customer are told apart."],
  ["03", "We score", "An LLM scores the rep against your scorecard and quotes the exact evidence from the transcript."],
  ["04", "You review", "Browse calls, read per-criterion scores, search conversations by meaning, and track trends."],
];

const PIPELINE = [
  ["Sound", "The recording lands in object storage."],
  ["Text", "Speech-to-text returns a diarized transcript."],
  ["Score", "The agent's turns are graded against the scorecard."],
  ["Search", "Every utterance is embedded for semantic recall."],
];

const STACK = ["PHP 8.5 · Symfony 7.4", "PostgreSQL 17 · pgvector", "Redis · Messenger", "Next.js 16 · React 19", "Cube analytics", "Deepgram · OpenAI"];

export default function Landing() {
  return (
    <div className="bg-white text-ink">
      {/* Nav */}
      <header className="sticky top-0 z-40 border-b border-slate-200 bg-white/85 backdrop-blur">
        <div className="mx-auto flex h-16 max-w-6xl items-center justify-between px-5">
          <Link href="/" className="flex items-center gap-2.5">
            <span className="text-brand-600">
              <Wave />
            </span>
            <span className="font-display text-lg font-bold tracking-tight text-ink">CallLens</span>
          </Link>
          <nav className="hidden items-center gap-7 text-sm text-slate-600 sm:flex">
            <a href="#how" className="hover:text-ink">How it works</a>
            <a href="#why" className="hover:text-ink">Why API</a>
            <Link href="/docs" className="hover:text-ink">Docs</Link>
          </nav>
          <Link href="/login" className="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
            Sign in
          </Link>
        </div>
      </header>

      {/* Hero */}
      <section className="relative overflow-hidden bg-ink text-white">
        <div className="absolute inset-0 opacity-70" style={{ backgroundImage: "radial-gradient(rgba(255,255,255,.06) 1px, transparent 1px)", backgroundSize: "22px 22px" }} aria-hidden />
        <HeroCanvas />
        <div className="absolute inset-0 bg-gradient-to-r from-ink via-ink/75 to-transparent" aria-hidden />
        <div className="relative mx-auto max-w-6xl px-5 pb-24 pt-20">
          <p className="mb-5 font-mono text-xs font-semibold uppercase tracking-[0.18em] text-brand-100/80">
            Call analytics platform · powered by APIs
          </p>
          <h1 className="max-w-3xl font-display text-4xl font-bold leading-[1.05] sm:text-5xl lg:text-6xl">
            Every sales call — transcribed and scored automatically.
          </h1>
          <p className="mt-6 max-w-2xl text-lg leading-relaxed text-slate-300">
            Connect your call source once. From then on, CallLens transcribes conversations, separates
            the rep from the customer, scores performance against your checklist, and rolls everything
            into reports and semantic search.
          </p>
          <div className="mt-9 flex flex-wrap items-center gap-3">
            <Link href="/login" className="rounded-lg bg-brand-600 px-5 py-3 font-medium hover:bg-brand-700">
              Get started →
            </Link>
            <a href="#how" className="rounded-lg border border-white/25 px-5 py-3 font-medium text-slate-200 hover:bg-white/5">
              How it works
            </a>
          </div>
          <p className="mt-14 font-mono text-xs text-slate-400">
            call → webhook → transcription → scoring → dashboard
          </p>
        </div>
      </section>

      {/* How it works */}
      <section id="how" className="mx-auto max-w-6xl px-5 py-20">
        <p className="mb-3 font-mono text-xs font-semibold uppercase tracking-[0.18em] text-brand-600">Getting started</p>
        <h2 className="font-display text-3xl font-bold tracking-tight sm:text-4xl">Up and running in four steps</h2>
        <p className="mt-4 max-w-2xl text-slate-600">Connect your source once — everything after that runs without you.</p>
        <ol className="mt-12 grid gap-5 md:grid-cols-2 lg:grid-cols-4">
          {STEPS.map(([n, title, body]) => (
            <li key={n} className="rounded-xl border border-slate-200 p-5 transition hover:-translate-y-0.5 hover:shadow-lg">
              <span className="font-mono text-sm text-brand-600">{n}</span>
              <h3 className="mt-2 font-display text-lg font-semibold">{title}</h3>
              <p className="mt-2 text-sm leading-relaxed text-slate-600">{body}</p>
            </li>
          ))}
        </ol>
      </section>

      {/* Sound → score */}
      <section className="bg-ink py-20 text-white">
        <div className="mx-auto max-w-6xl px-5">
          <h2 className="font-display text-3xl font-bold tracking-tight sm:text-4xl">How a conversation becomes a number</h2>
          <div className="mt-12 grid gap-5 md:grid-cols-4">
            {PIPELINE.map(([title, body], i) => (
              <div key={title} className="rounded-xl border border-white/10 bg-white/5 p-5">
                <div className="mb-3 flex items-center gap-2 text-brand-300">
                  <Wave />
                  <span className="font-mono text-xs text-slate-400">0{i + 1}</span>
                </div>
                <h3 className="font-display font-semibold">{title}</h3>
                <p className="mt-2 text-sm leading-relaxed text-slate-300">{body}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Why API */}
      <section id="why" className="mx-auto max-w-6xl px-5 py-20">
        <div className="grid gap-10 lg:grid-cols-2">
          <div>
            <p className="mb-3 font-mono text-xs font-semibold uppercase tracking-[0.18em] text-api-600">Architecture</p>
            <h2 className="font-display text-3xl font-bold tracking-tight sm:text-4xl">Why APIs, not your own GPU</h2>
            <p className="mt-4 leading-relaxed text-slate-600">
              All the heavy AI — speech-to-text, scoring, embeddings — runs through external paid APIs
              behind swappable interfaces. No GPU to operate, so a single dedicated server runs the
              whole product and infra stays cheap. When volume justifies it, providers can move to
              self-hosted GPU without touching business logic.
            </p>
          </div>
          <div className="rounded-xl border border-slate-200 bg-slate-50 p-6">
            <h3 className="font-display font-semibold">One dedicated server is enough</h3>
            <ul className="mt-4 space-y-2 text-sm text-slate-600">
              <li>· Hetzner AX dedicated (AMD 8c/16t, 64 GB ECC) from ~€46/mo.</li>
              <li>· Object storage at ~€4.99/mo per TB, EU/GDPR.</li>
              <li>· Total infra ≈ €50–65/mo — no GPU bills.</li>
            </ul>
            <div className="mt-5 flex flex-wrap gap-2">
              {STACK.map((s) => (
                <span key={s} className="rounded-md bg-white px-2.5 py-1 font-mono text-xs text-slate-600 ring-1 ring-slate-200">
                  {s}
                </span>
              ))}
            </div>
          </div>
        </div>
      </section>

      {/* CTA */}
      <section className="bg-ink text-white">
        <div className="mx-auto flex max-w-6xl flex-col items-start justify-between gap-6 px-5 py-16 md:flex-row md:items-center">
          <div>
            <h2 className="font-display text-2xl font-bold sm:text-3xl">Ready to connect your sales team?</h2>
            <p className="mt-2 text-slate-300">Sign up, add a webhook, and your first scores land today.</p>
          </div>
          <Link href="/login" className="rounded-lg bg-brand-600 px-6 py-3 font-medium hover:bg-brand-700">
            Get started →
          </Link>
        </div>
      </section>

      {/* Footer */}
      <footer className="border-t border-slate-200">
        <div className="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 px-5 py-10 text-sm text-slate-500 sm:flex-row">
          <div className="flex items-center gap-2.5">
            <span className="text-brand-600">
              <Wave />
            </span>
            <span className="font-display font-semibold text-ink">CallLens</span>
          </div>
          <p>Call analytics for sales teams · <Link href="/docs" className="hover:text-ink">docs</Link></p>
        </div>
      </footer>
    </div>
  );
}
