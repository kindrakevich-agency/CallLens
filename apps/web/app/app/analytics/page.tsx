"use client";

import { useEffect, useState } from "react";
import { api } from "@/lib/api";
import { PageHeader } from "@/components/PageHeader";

type Reports = {
  avg_score_per_agent: { agent: string; avg: number | null; calls: number }[];
  calls_per_week: { week: string; count: number }[];
  avg_score_per_week: { week: string; avg: number | null }[];
  status_breakdown: { status: string; count: number }[];
};

export default function AnalyticsPage() {
  const [data, setData] = useState<Reports | null>(null);

  useEffect(() => {
    api.reports().then(setData);
  }, []);

  if (!data) return <div className="p-8 text-slate-400">Loading…</div>;

  const totalCalls = data.calls_per_week.reduce((s, w) => s + w.count, 0);
  const scored = data.avg_score_per_agent.reduce((s, a) => s + a.calls, 0);
  const avgScore =
    scored > 0
      ? Math.round(data.avg_score_per_agent.reduce((s, a) => s + (a.avg ?? 0) * a.calls, 0) / scored)
      : null;

  return (
    <>
      <PageHeader title="Analytics" subtitle="Measures from the Cube semantic layer over PostgreSQL." />
      <div className="space-y-6 p-8">
        {/* Stat cards */}
        <div className="grid gap-4 sm:grid-cols-3">
          <Stat label="Total calls" value={totalCalls} />
          <Stat label="Scored calls" value={scored} />
          <Stat label="Avg score" value={avgScore ?? "—"} />
        </div>

        <div className="grid gap-6 lg:grid-cols-2">
          <Card title="Average score per agent">
            <div className="space-y-3">
              {data.avg_score_per_agent.map((a) => (
                <Bar key={a.agent} label={a.agent} value={a.avg ?? 0} max={100} suffix={`${a.avg ?? 0}`} />
              ))}
              {data.avg_score_per_agent.length === 0 && <Empty />}
            </div>
          </Card>

          <Card title="Calls by status">
            <div className="space-y-3">
              {data.status_breakdown.map((s) => (
                <Bar
                  key={s.status}
                  label={s.status}
                  value={s.count}
                  max={Math.max(...data.status_breakdown.map((x) => x.count), 1)}
                  suffix={`${s.count}`}
                />
              ))}
              {data.status_breakdown.length === 0 && <Empty />}
            </div>
          </Card>

          <Card title="Calls per week">
            <Columns points={data.calls_per_week.map((w) => ({ label: w.week, value: w.count }))} />
          </Card>

          <Card title="Average score per week">
            <Columns
              points={data.avg_score_per_week.map((w) => ({ label: w.week, value: w.avg ?? 0 }))}
              max={100}
            />
          </Card>
        </div>
      </div>
    </>
  );
}

function Stat({ label, value }: { label: string; value: number | string }) {
  return (
    <div className="rounded-xl border border-slate-200 bg-white p-5">
      <div className="text-xs uppercase tracking-wide text-slate-500">{label}</div>
      <div className="mt-1 font-display text-3xl font-bold text-ink">{value}</div>
    </div>
  );
}

function Card({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <div className="rounded-xl border border-slate-200 bg-white p-5">
      <h2 className="mb-4 font-display text-sm font-semibold uppercase tracking-wide text-slate-500">{title}</h2>
      {children}
    </div>
  );
}

function Bar({ label, value, max, suffix }: { label: string; value: number; max: number; suffix: string }) {
  const pct = Math.max(2, Math.round((value / max) * 100));
  return (
    <div>
      <div className="mb-1 flex items-center justify-between text-sm">
        <span className="capitalize text-ink">{label}</span>
        <span className="font-mono text-xs text-slate-500">{suffix}</span>
      </div>
      <div className="h-2 rounded-full bg-slate-100">
        <div className="h-2 rounded-full bg-brand-500" style={{ width: `${pct}%` }} />
      </div>
    </div>
  );
}

function Columns({ points, max }: { points: { label: string; value: number }[]; max?: number }) {
  const top = max ?? Math.max(...points.map((p) => p.value), 1);
  if (points.length === 0) return <Empty />;
  return (
    <div className="flex items-end gap-2" style={{ height: 140 }}>
      {points.map((p) => (
        <div key={p.label} className="flex flex-1 flex-col items-center justify-end gap-1">
          <span className="font-mono text-[10px] text-slate-500">{p.value}</span>
          <div
            className="w-full rounded-t bg-brand-300"
            style={{ height: `${Math.max(4, (p.value / top) * 110)}px` }}
          />
          <span className="font-mono text-[10px] text-slate-400">{p.label.slice(5)}</span>
        </div>
      ))}
    </div>
  );
}

function Empty() {
  return <p className="text-sm text-slate-400">No data yet.</p>;
}
