"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import Link from "next/link";
import { api, ApiError, type CallSummary } from "@/lib/api";
import { PageHeader } from "@/components/PageHeader";
import { ScoreBadge, StatusBadge } from "@/components/Brand";

const STATUSES = ["", "completed", "scoring", "transcribing", "received", "failed"];

export default function CallsPage() {
  const [data, setData] = useState<{ items: CallSummary[]; total: number } | null>(null);
  const [status, setStatus] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [showUpload, setShowUpload] = useState(false);

  const load = useCallback(() => {
    api
      .calls({ status: status || undefined })
      .then(setData)
      .catch((e) => setError(String(e.message ?? e)));
  }, [status]);

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
        <span className="flex items-center gap-2">
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
          <button
            onClick={() => setShowUpload(true)}
            className="rounded-lg bg-brand-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-brand-700"
          >
            Upload call
          </button>
        </span>
      </PageHeader>

      {showUpload && (
        <UploadDialog
          onClose={() => setShowUpload(false)}
          onDone={() => {
            setShowUpload(false);
            load();
          }}
        />
      )}

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
                    No calls yet. Upload one or connect a webhook.
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

function UploadDialog({ onClose, onDone }: { onClose: () => void; onDone: () => void }) {
  const fileRef = useRef<HTMLInputElement>(null);
  const [agent, setAgent] = useState("");
  const [channels, setChannels] = useState("mono");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    const file = fileRef.current?.files?.[0];
    if (!file) {
      setError("Choose an audio file.");
      return;
    }
    setBusy(true);
    setError(null);
    try {
      await api.upload(file, { agent_external_id: agent || undefined, channels });
      onDone();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Upload failed.");
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-ink/40 p-4" onClick={onClose}>
      <form
        onClick={(e) => e.stopPropagation()}
        onSubmit={submit}
        className="w-full max-w-md rounded-xl bg-white p-6 shadow-xl"
      >
        <h2 className="font-display text-lg font-semibold text-ink">Upload a call</h2>
        <p className="mt-1 text-sm text-slate-500">
          Drop in a recording — it&apos;s transcribed, scored and indexed automatically.
        </p>
        {error && <div className="mt-3 rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700">{error}</div>}

        <label className="mt-4 block text-sm font-medium text-slate-700">
          Audio file
          <input ref={fileRef} type="file" accept="audio/*" className="mt-1 w-full text-sm" required />
        </label>
        <label className="mt-4 block text-sm font-medium text-slate-700">
          Agent (optional)
          <input
            value={agent}
            onChange={(e) => setAgent(e.target.value)}
            placeholder="e.g. Sam"
            className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none"
          />
        </label>
        <label className="mt-4 block text-sm font-medium text-slate-700">
          Channels
          <select
            value={channels}
            onChange={(e) => setChannels(e.target.value)}
            className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none"
          >
            <option value="mono">Mono (single track — diarized)</option>
            <option value="dual">Dual (rep & customer on separate channels)</option>
          </select>
        </label>

        <div className="mt-6 flex justify-end gap-2">
          <button type="button" onClick={onClose} className="rounded-lg border border-slate-300 px-4 py-2 text-sm">
            Cancel
          </button>
          <button
            type="submit"
            disabled={busy}
            className="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-60"
          >
            {busy ? "Uploading…" : "Upload"}
          </button>
        </div>
      </form>
    </div>
  );
}
