"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { useParams } from "next/navigation";
import { api, type CallDetail } from "@/lib/api";
import { ScoreBadge, StatusBadge } from "@/components/Brand";

export default function CallDetailPage() {
  const { id } = useParams<{ id: string }>();
  const [call, setCall] = useState<CallDetail | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    api.call(id).then(setCall).catch((e) => setError(String(e.message ?? e)));
  }, [id]);

  if (error) return <div className="p-8 text-rose-600">{error}</div>;
  if (!call) return <div className="p-8 text-slate-400">Loading…</div>;

  return (
    <div>
      <header className="border-b border-slate-200 bg-white px-8 py-5">
        <Link href="/app/calls" className="text-sm text-brand-600 hover:underline">
          ← Calls
        </Link>
        <div className="mt-2 flex items-center gap-3">
          <h1 className="font-mono text-xl font-semibold text-ink">{call.external_id}</h1>
          <StatusBadge status={call.status} />
          <span className="text-xs uppercase text-slate-400">
            {call.channels} · {call.language}
          </span>
          <div className="ml-auto flex items-center gap-2">
            <span className="text-sm text-slate-500">Overall</span>
            <ScoreBadge score={call.overall_score} />
          </div>
        </div>
        {call.audio_available ? (
          <audio controls preload="none" src={`/api/v1/calls/${id}/audio`} className="mt-3 h-9 w-full max-w-md">
            Your browser does not support audio playback.
          </audio>
        ) : (
          <p className="mt-2 text-xs text-slate-400">Audio deleted — transcript &amp; scores retained.</p>
        )}
      </header>

      <div className="grid gap-6 p-8 lg:grid-cols-[1fr_22rem]">
        {/* Transcript */}
        <section>
          <h2 className="mb-3 font-display text-sm font-semibold uppercase tracking-wide text-slate-500">
            Transcript
          </h2>
          <div className="space-y-3 rounded-xl border border-slate-200 bg-white p-5">
            {call.utterances.map((u, i) => (
              <div key={i} className="flex gap-3">
                <span
                  className={`mt-0.5 shrink-0 rounded px-2 py-0.5 text-xs font-medium ${
                    u.speaker === "agent" ? "bg-brand-50 text-brand-700" : "bg-slate-100 text-slate-600"
                  }`}
                >
                  {u.speaker}
                </span>
                <p className="text-sm leading-relaxed text-ink">{u.text}</p>
              </div>
            ))}
            {call.utterances.length === 0 && <p className="text-sm text-slate-400">No transcript yet.</p>}
          </div>
        </section>

        {/* Scores */}
        <section>
          <h2 className="mb-3 font-display text-sm font-semibold uppercase tracking-wide text-slate-500">
            Scorecard
          </h2>
          <div className="space-y-3">
            {call.criterion_scores.map((c) => (
              <div key={c.key} className="rounded-xl border border-slate-200 bg-white p-4">
                <div className="flex items-center justify-between">
                  <span className="font-medium capitalize text-ink">{c.key.replace(/_/g, " ")}</span>
                  <span className="font-mono text-sm text-slate-600">
                    {c.score}/{c.max_score}
                  </span>
                </div>
                {c.evidence_quote && (
                  <blockquote className="mt-2 border-l-2 border-brand-300 pl-3 text-sm italic text-slate-600">
                    “{c.evidence_quote}”
                  </blockquote>
                )}
                {c.rationale && <p className="mt-2 text-xs text-slate-500">{c.rationale}</p>}
              </div>
            ))}
            {call.criterion_scores.length === 0 && (
              <p className="text-sm text-slate-400">Not scored yet.</p>
            )}
          </div>
        </section>
      </div>
    </div>
  );
}
