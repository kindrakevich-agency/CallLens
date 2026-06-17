"use client";

import { useEffect, useState } from "react";
import { api } from "@/lib/api";
import { PageHeader } from "@/components/PageHeader";

type Criterion = { key: string; title: string; weight: number; max_score: number; guidance: string | null };
type Scorecard = { id: string; name: string; version: number; is_default: boolean; criteria: Criterion[] };

export default function ScorecardsPage() {
  const [cards, setCards] = useState<Scorecard[] | null>(null);

  useEffect(() => {
    api.scorecards().then((d: { items: Scorecard[] }) => setCards(d.items));
  }, []);

  return (
    <>
      <PageHeader title="Scorecards" subtitle="The criteria each call is scored against." />
      <div className="space-y-6 p-8">
        {cards?.map((s) => (
          <div key={s.id} className="rounded-xl border border-slate-200 bg-white p-5">
            <div className="flex items-center gap-2">
              <h2 className="font-display font-semibold text-ink">{s.name}</h2>
              <span className="text-xs text-slate-400">v{s.version}</span>
              {s.is_default && (
                <span className="rounded bg-brand-50 px-2 py-0.5 text-xs text-brand-700">default</span>
              )}
            </div>
            <ul className="mt-3 divide-y divide-slate-100">
              {s.criteria.map((c) => (
                <li key={c.key} className="flex items-center justify-between py-2 text-sm">
                  <div>
                    <span className="font-medium text-ink">{c.title}</span>
                    {c.guidance && <p className="text-xs text-slate-500">{c.guidance}</p>}
                  </div>
                  <span className="font-mono text-xs text-slate-500">
                    max {c.max_score} · w{c.weight}
                  </span>
                </li>
              ))}
            </ul>
          </div>
        ))}
        {cards && cards.length === 0 && (
          <p className="text-slate-400">
            No scorecards yet — calls fall back to a built-in default set of criteria. A full
            editor is on the roadmap.
          </p>
        )}
      </div>
    </>
  );
}
