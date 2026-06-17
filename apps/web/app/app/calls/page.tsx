"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { api, type CallSummary } from "@/lib/api";
import { PageHeader } from "@/components/PageHeader";
import { ScoreBadge, StatusBadge } from "@/components/Brand";

const STATUSES = ["", "completed", "scoring", "transcribing", "received", "failed"];

export default function CallsPage() {
  const [data, setData] = useState<{ items: CallSummary[]; total: number } | null>(null);
  const [status, setStatus] = useState("");
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let active = true;
    api
      .calls({ status: status || undefined })
      .then((d) => active && setData(d))
      .catch((e) => active && setError(String(e.message ?? e)));
    return () => {
      active = false;
    };
  }, [status]);

  return (
    <>
      <PageHeader title="Calls" subtitle={data ? `${data.total} total` : "Loading…"}>
        <select
          value={status}
          onChange={(e) => setStatus(e.target.value)}
          className="rounded-lg border border-slate-300 px-3 py-1.5 text-sm focus:border-brand-500 focus:outline-none"
        >
          {STATUSES.map((s) => (
            <option key={s} value={s}>
              {s === "" ? "All statuses" : s}
            </option>
          ))}
        </select>
      </PageHeader>

      <div className="p-8">
        {error && <p className="text-rose-600">{error}</p>}
        <div className="overflow-hidden rounded-xl border border-slate-200 bg-white">
          <table className="w-full text-sm">
            <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
              <tr>
                <th className="px-4 py-3 font-medium">Call</th>
                <th className="px-4 py-3 font-medium">Agent</th>
                <th className="px-4 py-3 font-medium">Status</th>
                <th className="px-4 py-3 font-medium">Score</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {data?.items.map((c) => (
                <tr key={c.id} className="hover:bg-slate-50">
                  <td className="px-4 py-3">
                    <Link href={`/app/calls/${c.id}`} className="font-mono text-brand-700 hover:underline">
                      {c.external_id}
                    </Link>
                    <span className="ml-2 text-xs uppercase text-slate-400">{c.channels}</span>
                  </td>
                  <td className="px-4 py-3 text-slate-600">{c.agent?.name ?? "—"}</td>
                  <td className="px-4 py-3">
                    <StatusBadge status={c.status} />
                  </td>
                  <td className="px-4 py-3">
                    <ScoreBadge score={c.overall_score} />
                  </td>
                </tr>
              ))}
              {data && data.items.length === 0 && (
                <tr>
                  <td colSpan={4} className="px-4 py-10 text-center text-slate-400">
                    No calls yet. Ingest one via the webhook or upload.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
    </>
  );
}
