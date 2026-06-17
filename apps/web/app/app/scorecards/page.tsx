"use client";

import { useCallback, useEffect, useState } from "react";
import { api, ApiError } from "@/lib/api";
import { PageHeader } from "@/components/PageHeader";

type Criterion = { key: string; title: string; weight: number; max_score: number; guidance: string | null };
type Scorecard = {
  id: string | null;
  name: string;
  version: number;
  is_default: boolean;
  is_builtin: boolean;
  criteria: Criterion[];
};

type Draft = { id: string | null; name: string; is_default: boolean; criteria: Criterion[] };

const blankCriterion = (): Criterion => ({ key: "", title: "", weight: 1, max_score: 5, guidance: "" });

export default function ScorecardsPage() {
  const [cards, setCards] = useState<Scorecard[] | null>(null);
  const [draft, setDraft] = useState<Draft | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const load = useCallback(() => {
    api.scorecards().then((d: { items: Scorecard[] }) => setCards(d.items));
  }, []);
  useEffect(() => {
    load();
  }, [load]);

  const startNew = () => {
    const template = cards?.[0]?.criteria ?? [];
    setError(null);
    setDraft({
      id: null,
      name: "",
      is_default: !cards?.some((c) => !c.is_builtin),
      criteria: template.length ? template.map((c) => ({ ...c })) : [blankCriterion()],
    });
  };
  const startEdit = (s: Scorecard) =>
    setDraft({ id: s.id, name: s.name, is_default: s.is_default, criteria: s.criteria.map((c) => ({ ...c })) });

  async function save() {
    if (!draft) return;
    setBusy(true);
    setError(null);
    try {
      const body = { name: draft.name, is_default: draft.is_default, criteria: draft.criteria };
      if (draft.id) await api.updateScorecard(draft.id, body);
      else await api.createScorecard(body);
      setDraft(null);
      load();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : "Could not save.");
    } finally {
      setBusy(false);
    }
  }

  async function remove(id: string) {
    if (!confirm("Delete this scorecard?")) return;
    await api.deleteScorecard(id);
    load();
  }

  if (draft) {
    return (
      <Editor
        draft={draft}
        setDraft={setDraft}
        onSave={save}
        onCancel={() => setDraft(null)}
        busy={busy}
        error={error}
      />
    );
  }

  return (
    <>
      <PageHeader title="Scorecards" subtitle="The criteria each call is scored against.">
        <button
          onClick={startNew}
          className="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"
        >
          + New scorecard
        </button>
      </PageHeader>
      <div className="space-y-6 p-8">
        {cards?.map((s) => (
          <div key={s.id ?? "builtin"} className="rounded-xl border border-slate-200 bg-white p-5">
            <div className="flex items-center gap-2">
              <h2 className="font-display font-semibold text-ink">{s.name}</h2>
              <span className="text-xs text-slate-400">v{s.version}</span>
              {s.is_default && <span className="rounded bg-brand-50 px-2 py-0.5 text-xs text-brand-700">default</span>}
              {s.is_builtin && <span className="rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-600">built-in</span>}
              {!s.is_builtin && s.id && (
                <span className="ml-auto flex gap-3 text-xs">
                  <button onClick={() => startEdit(s)} className="text-brand-600 hover:underline">Edit</button>
                  <button onClick={() => remove(s.id!)} className="text-rose-600 hover:underline">Delete</button>
                </span>
              )}
            </div>
            {s.is_builtin && (
              <p className="mt-2 text-xs text-slate-500">
                Built-in default used while your workspace has no custom scorecard. Create your own to override it.
              </p>
            )}
            <ul className="mt-3 divide-y divide-slate-100">
              {s.criteria.map((c) => (
                <li key={c.key} className="flex items-center justify-between py-2 text-sm">
                  <div>
                    <span className="font-medium text-ink">{c.title}</span>
                    {c.guidance && <p className="text-xs text-slate-500">{c.guidance}</p>}
                  </div>
                  <span className="font-mono text-xs text-slate-500">max {c.max_score} · w{c.weight}</span>
                </li>
              ))}
            </ul>
          </div>
        ))}
      </div>
    </>
  );
}

function Editor({
  draft,
  setDraft,
  onSave,
  onCancel,
  busy,
  error,
}: {
  draft: Draft;
  setDraft: (d: Draft) => void;
  onSave: () => void;
  onCancel: () => void;
  busy: boolean;
  error: string | null;
}) {
  const setCrit = (i: number, patch: Partial<Criterion>) =>
    setDraft({ ...draft, criteria: draft.criteria.map((c, j) => (j === i ? { ...c, ...patch } : c)) });

  return (
    <>
      <PageHeader title={draft.id ? "Edit scorecard" : "New scorecard"} subtitle="Criteria, weights, and guidance.">
        <span className="flex gap-2">
          <button onClick={onCancel} className="rounded-lg border border-slate-300 px-4 py-2 text-sm">Cancel</button>
          <button
            onClick={onSave}
            disabled={busy}
            className="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-60"
          >
            {busy ? "Saving…" : "Save"}
          </button>
        </span>
      </PageHeader>
      <div className="max-w-3xl space-y-5 p-8">
        {error && <div className="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700">{error}</div>}
        <label className="block text-sm font-medium text-slate-700">
          Name
          <input
            value={draft.name}
            onChange={(e) => setDraft({ ...draft, name: e.target.value })}
            className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none"
          />
        </label>
        <label className="flex items-center gap-2 text-sm text-slate-700">
          <input
            type="checkbox"
            checked={draft.is_default}
            onChange={(e) => setDraft({ ...draft, is_default: e.target.checked })}
          />
          Use as the default scorecard for new calls
        </label>

        <div className="space-y-3">
          {draft.criteria.map((c, i) => (
            <div key={i} className="rounded-xl border border-slate-200 bg-white p-4">
              <div className="grid grid-cols-2 gap-3">
                <Field label="Key (snake_case)">
                  <input value={c.key} onChange={(e) => setCrit(i, { key: e.target.value })} className={inputCls} />
                </Field>
                <Field label="Title">
                  <input value={c.title} onChange={(e) => setCrit(i, { title: e.target.value })} className={inputCls} />
                </Field>
                <Field label="Max score">
                  <input type="number" min={1} value={c.max_score} onChange={(e) => setCrit(i, { max_score: Number(e.target.value) })} className={inputCls} />
                </Field>
                <Field label="Weight">
                  <input type="number" min={0} step={0.5} value={c.weight} onChange={(e) => setCrit(i, { weight: Number(e.target.value) })} className={inputCls} />
                </Field>
              </div>
              <Field label="Guidance (shown to the LLM scorer)">
                <input value={c.guidance ?? ""} onChange={(e) => setCrit(i, { guidance: e.target.value })} className={inputCls} />
              </Field>
              {draft.criteria.length > 1 && (
                <button
                  onClick={() => setDraft({ ...draft, criteria: draft.criteria.filter((_, j) => j !== i) })}
                  className="mt-2 text-xs text-rose-600 hover:underline"
                >
                  Remove criterion
                </button>
              )}
            </div>
          ))}
          <button
            onClick={() => setDraft({ ...draft, criteria: [...draft.criteria, blankCriterion()] })}
            className="rounded-lg border border-brand-600 px-3 py-1.5 text-sm font-medium text-brand-700 hover:bg-brand-50"
          >
            + Add criterion
          </button>
        </div>
      </div>
    </>
  );
}

const inputCls =
  "mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none";

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <label className="mt-3 block text-xs font-medium text-slate-600">
      {label}
      {children}
    </label>
  );
}
