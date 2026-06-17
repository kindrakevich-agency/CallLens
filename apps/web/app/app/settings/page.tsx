"use client";

import { useEffect, useState } from "react";
import { api } from "@/lib/api";
import { PageHeader } from "@/components/PageHeader";

type Webhook = { id: string; source_type: string; is_active: boolean; signing_secret: string };
type Retention = { mode: string; days: number; modes: string[] };

export default function SettingsPage() {
  const [url, setUrl] = useState("");
  const [webhooks, setWebhooks] = useState<Webhook[]>([]);
  const [retention, setRetention] = useState<Retention | null>(null);

  useEffect(() => {
    api.webhooks().then((d) => {
      setUrl(d.url);
      setWebhooks(d.items);
    });
    api.retention().then(setRetention);
  }, []);

  async function rotate(id: string) {
    const updated = await api.rotateWebhook(id);
    setWebhooks((ws) => ws.map((w) => (w.id === id ? updated : w)));
  }

  async function addWebhook() {
    const created = await api.createWebhook("generic");
    setWebhooks((ws) => [...ws, created]);
  }

  async function saveRetention(mode: string, days: number) {
    setRetention(await api.setRetention(mode, days));
  }

  return (
    <>
      <PageHeader title="Settings" subtitle="Ingestion endpoints and audio retention." />
      <div className="max-w-3xl space-y-8 p-8">
        {/* Webhooks */}
        <section className="rounded-xl border border-slate-200 bg-white p-6">
          <h2 className="font-display font-semibold text-ink">Webhook ingestion</h2>
          <p className="mt-1 text-sm text-slate-500">
            POST signed call payloads to this URL with the endpoint id and HMAC signature.
          </p>
          <code className="mt-3 block break-all rounded-lg bg-slate-50 px-3 py-2 font-mono text-xs text-ink">
            {url}
          </code>

          <div className="mt-4 space-y-3">
            {webhooks.map((w) => (
              <div key={w.id} className="rounded-lg border border-slate-200 p-3">
                <div className="flex items-center justify-between text-sm">
                  <span className="font-medium capitalize text-ink">{w.source_type}</span>
                  <button onClick={() => rotate(w.id)} className="text-xs text-brand-600 hover:underline">
                    Regenerate secret
                  </button>
                </div>
                <div className="mt-2 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 font-mono text-xs text-slate-500">
                  <span>endpoint</span>
                  <span className="break-all text-ink">{w.id}</span>
                  <span>secret</span>
                  <span className="break-all text-ink">{w.signing_secret}</span>
                </div>
              </div>
            ))}
          </div>
          <button
            onClick={addWebhook}
            className="mt-4 rounded-lg border border-brand-600 px-3 py-1.5 text-sm font-medium text-brand-700 hover:bg-brand-50"
          >
            + Add endpoint
          </button>
        </section>

        {/* Retention */}
        {retention && (
          <section className="rounded-xl border border-slate-200 bg-white p-6">
            <h2 className="font-display font-semibold text-ink">Audio retention</h2>
            <p className="mt-1 text-sm text-slate-500">
              Processed audio can be auto-deleted; transcripts and scores are always kept.
            </p>
            <div className="mt-4 flex flex-wrap items-center gap-3">
              <select
                value={retention.mode}
                onChange={(e) => saveRetention(e.target.value, retention.days)}
                className="rounded-lg border border-slate-300 px-3 py-1.5 text-sm focus:border-brand-500 focus:outline-none"
              >
                {retention.modes.map((m) => (
                  <option key={m} value={m}>
                    {m.replace(/_/g, " ")}
                  </option>
                ))}
              </select>
              {retention.mode === "delete_after_days" && (
                <input
                  type="number"
                  min={1}
                  value={retention.days}
                  onChange={(e) => saveRetention(retention.mode, Number(e.target.value))}
                  className="w-20 rounded-lg border border-slate-300 px-3 py-1.5 text-sm"
                />
              )}
              <span className="text-xs text-slate-400">(deletion sweep ships in a later milestone)</span>
            </div>
          </section>
        )}
      </div>
    </>
  );
}
