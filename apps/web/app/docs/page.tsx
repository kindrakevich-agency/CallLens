import Link from "next/link";
import { Wave } from "@/components/Brand";

const TOPICS = [
  { title: "How it works", body: "From a phone call to a scored, searchable record — the pipeline end to end.", href: "/#how" },
  { title: "Connect a webhook", body: "Sign and send call payloads: headers, HMAC signature, payload schema, examples.", href: "/docs/webhooks" },
  { title: "Scoring & evidence", body: "How rep performance is scored against a scorecard, with verbatim evidence quotes.", href: "/#how" },
  { title: "Semantic search", body: "Find moments across every call by meaning, powered by utterance embeddings.", href: "/#how" },
  { title: "Security", body: "JWT-in-cookie auth, strict tenant isolation, HMAC-signed ingestion, EU/GDPR.", href: "/#why" },
  { title: "API reference", body: "The full OpenAPI reference is served internally (ReDoc) at /internal/docs.", href: "/docs/webhooks" },
];

export default function DocsIndex() {
  return (
    <div className="min-h-screen bg-white text-ink">
      <header className="border-b border-slate-200">
        <div className="mx-auto flex h-16 max-w-5xl items-center justify-between px-5">
          <Link href="/" className="flex items-center gap-2.5">
            <span className="text-brand-600">
              <Wave />
            </span>
            <span className="font-display text-lg font-bold tracking-tight">CallLens</span>
          </Link>
          <Link href="/login" className="text-sm font-medium text-brand-600 hover:text-brand-700">
            Sign in
          </Link>
        </div>
      </header>

      <main className="mx-auto max-w-5xl px-5 py-16">
        <p className="font-mono text-xs font-semibold uppercase tracking-[0.18em] text-brand-600">Documentation</p>
        <h1 className="mt-2 font-display text-4xl font-bold tracking-tight">Connect, understand, and use CallLens</h1>
        <p className="mt-4 max-w-2xl text-slate-600">
          Guides for connecting your telephony, understanding scores and reports, and using the cabinet.
        </p>

        <div className="mt-10 grid gap-4 sm:grid-cols-2">
          {TOPICS.map((t) => (
            <Link
              key={t.title}
              href={t.href}
              className="rounded-xl border border-slate-200 p-5 transition hover:-translate-y-0.5 hover:border-brand-300 hover:shadow-lg"
            >
              <h2 className="font-display font-semibold">{t.title}</h2>
              <p className="mt-2 text-sm leading-relaxed text-slate-600">{t.body}</p>
            </Link>
          ))}
        </div>
      </main>
    </div>
  );
}
