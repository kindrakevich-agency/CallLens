import Link from "next/link";

// Marketing landing (route "/"). The full design — deep-navy + teal, amber for
// "external API", waveform motif — is ported from doc/html/landing.html in M9.
export default function MarketingHome() {
  return (
    <main className="min-h-screen bg-[#0C1B2A] text-white flex flex-col items-center justify-center gap-8 px-6 text-center">
      <span className="text-xs font-mono uppercase tracking-[0.18em] text-[#5EEAD4]/80">
        Call analytics platform · powered by APIs
      </span>
      <h1 className="max-w-3xl text-4xl font-bold leading-tight sm:text-6xl">
        Every sales call — transcribed and scored automatically.
      </h1>
      <p className="max-w-2xl text-lg text-slate-300">
        Connect your call source once. CallLens transcribes, separates the rep
        from the customer, scores against your scorecard, and rolls it into reports.
      </p>
      <div className="flex flex-wrap items-center justify-center gap-3">
        <Link
          href="/app"
          className="rounded-lg bg-[#0E6A63] px-5 py-3 font-medium hover:bg-[#0B5A54] transition"
        >
          Open the cabinet →
        </Link>
        <Link
          href="/docs"
          className="rounded-lg border border-white/25 px-5 py-3 font-medium text-slate-200 hover:bg-white/5 transition"
        >
          Read the docs
        </Link>
      </div>
    </main>
  );
}
