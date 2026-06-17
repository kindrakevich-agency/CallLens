"use client";

import { useState } from "react";
import Link from "next/link";
import { api } from "@/lib/api";
import { PageHeader } from "@/components/PageHeader";

type Hit = { call_id: string; speaker: string; text: string; score: number };

export default function SearchPage() {
  const [q, setQ] = useState("");
  const [hits, setHits] = useState<Hit[] | null>(null);
  const [busy, setBusy] = useState(false);

  async function run(e: React.FormEvent) {
    e.preventDefault();
    if (!q.trim()) return;
    setBusy(true);
    try {
      const res = await api.search(q);
      setHits(res.results);
    } finally {
      setBusy(false);
    }
  }

  return (
    <>
      <PageHeader title="Semantic search" subtitle="Find moments across every call by meaning." />
      <div className="p-8">
        <form onSubmit={run} className="flex gap-2">
          <input
            value={q}
            onChange={(e) => setQ(e.target.value)}
            placeholder="e.g. customer worried about pricing"
            className="flex-1 rounded-lg border border-slate-300 px-4 py-2.5 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30"
          />
          <button
            disabled={busy}
            className="rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-60"
          >
            {busy ? "…" : "Search"}
          </button>
        </form>

        <div className="mt-6 space-y-3">
          {hits?.map((h, i) => (
            <Link
              key={i}
              href={`/app/calls/${h.call_id}`}
              className="block rounded-xl border border-slate-200 bg-white p-4 hover:border-brand-300"
            >
              <div className="flex items-center justify-between">
                <span
                  className={`rounded px-2 py-0.5 text-xs font-medium ${
                    h.speaker === "agent" ? "bg-brand-50 text-brand-700" : "bg-slate-100 text-slate-600"
                  }`}
                >
                  {h.speaker}
                </span>
                <span className="font-mono text-xs text-slate-400">
                  {(h.score * 100).toFixed(0)}% match
                </span>
              </div>
              <p className="mt-2 text-sm text-ink">{h.text}</p>
            </Link>
          ))}
          {hits && hits.length === 0 && <p className="text-slate-400">No matches.</p>}
        </div>
      </div>
    </>
  );
}
