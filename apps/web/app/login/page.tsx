"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { api, ApiError } from "@/lib/api";
import { Logo } from "@/components/Brand";

export default function LoginPage() {
  const router = useRouter();
  const [mode, setMode] = useState<"login" | "register">("login");
  const [form, setForm] = useState({ email: "", password: "", name: "", workspace: "" });
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const set = (k: string) => (e: React.ChangeEvent<HTMLInputElement>) =>
    setForm({ ...form, [k]: e.target.value });

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError(null);
    try {
      if (mode === "register") {
        await api.register({ email: form.email, password: form.password, name: form.name, workspace: form.workspace });
      }
      await api.login(form.email, form.password);
      router.push("/app/calls");
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Something went wrong.");
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="grid min-h-screen lg:grid-cols-2">
      {/* Brand panel */}
      <div className="relative hidden flex-col justify-between bg-ink p-12 text-white lg:flex">
        <Logo />
        <div>
          <h1 className="font-display text-4xl font-bold leading-tight">
            Every sales call — transcribed and scored automatically.
          </h1>
          <p className="mt-4 max-w-md text-slate-300">
            Sign in to review calls, per-criterion scores with evidence, and search
            conversations semantically.
          </p>
        </div>
        <p className="font-mono text-xs text-slate-500">
          call → webhook → transcription → scoring → dashboard
        </p>
      </div>

      {/* Form */}
      <div className="flex items-center justify-center bg-white p-8">
        <form onSubmit={submit} className="w-full max-w-sm">
          <h2 className="font-display text-2xl font-bold text-ink">
            {mode === "login" ? "Sign in" : "Create your workspace"}
          </h2>
          <p className="mt-1 text-sm text-slate-500">
            {mode === "login" ? "Welcome back." : "You'll be the workspace owner."}
          </p>

          {error && (
            <div className="mt-4 rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700">{error}</div>
          )}

          {mode === "register" && (
            <Field label="Name">
              <input className={inputCls} value={form.name} onChange={set("name")} required />
            </Field>
          )}
          <Field label="Email">
            <input type="email" className={inputCls} value={form.email} onChange={set("email")} required />
          </Field>
          <Field label="Password">
            <input
              type="password"
              className={inputCls}
              value={form.password}
              onChange={set("password")}
              minLength={8}
              required
            />
          </Field>
          {mode === "register" && (
            <Field label="Workspace name (optional)">
              <input className={inputCls} value={form.workspace} onChange={set("workspace")} />
            </Field>
          )}

          <button
            type="submit"
            disabled={busy}
            className="mt-6 w-full rounded-lg bg-brand-600 px-4 py-2.5 font-medium text-white transition hover:bg-brand-700 disabled:opacity-60"
          >
            {busy ? "…" : mode === "login" ? "Sign in" : "Create workspace"}
          </button>

          <button
            type="button"
            onClick={() => {
              setMode(mode === "login" ? "register" : "login");
              setError(null);
            }}
            className="mt-4 w-full text-center text-sm text-brand-600 hover:text-brand-700"
          >
            {mode === "login" ? "Create a workspace" : "Already have an account? Sign in"}
          </button>
        </form>
      </div>
    </div>
  );
}

const inputCls =
  "mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30";

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <label className="mt-4 block text-sm font-medium text-slate-700">
      {label}
      {children}
    </label>
  );
}
