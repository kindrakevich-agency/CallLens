"use client";

import { Suspense, useState } from "react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { api, ApiError } from "@/lib/api";
import { Logo } from "@/components/Brand";

function ResetForm() {
  const router = useRouter();
  const token = useSearchParams().get("token") ?? "";
  const [password, setPassword] = useState("");
  const [confirm, setConfirm] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [done, setDone] = useState(false);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    if (password !== confirm) {
      setError("Passwords don't match.");
      return;
    }
    setBusy(true);
    setError(null);
    try {
      await api.resetPassword(token, password);
      setDone(true);
      setTimeout(() => router.push("/login"), 1800);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Could not reset your password.");
    } finally {
      setBusy(false);
    }
  }

  if (!token) {
    return <Notice title="Invalid link" body="This reset link is missing its token. Request a new one from the sign-in page." />;
  }
  if (done) {
    return <Notice title="Password updated" body="You can now sign in with your new password. Redirecting…" tone="ok" />;
  }

  return (
    <form onSubmit={submit} className="w-full max-w-sm">
      <h2 className="font-display text-2xl font-bold text-ink">Set a new password</h2>
      <p className="mt-1 text-sm text-slate-500">Choose a password with at least 8 characters.</p>
      {error && <div className="mt-4 rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700">{error}</div>}
      <label className="mt-4 block text-sm font-medium text-slate-700">
        New password
        <input type="password" value={password} onChange={(e) => setPassword(e.target.value)} minLength={8} required className={inputCls} />
      </label>
      <label className="mt-4 block text-sm font-medium text-slate-700">
        Confirm password
        <input type="password" value={confirm} onChange={(e) => setConfirm(e.target.value)} minLength={8} required className={inputCls} />
      </label>
      <button
        type="submit"
        disabled={busy}
        className="mt-6 w-full rounded-lg bg-brand-600 px-4 py-2.5 font-medium text-white transition hover:bg-brand-700 disabled:opacity-60"
      >
        {busy ? "…" : "Update password"}
      </button>
    </form>
  );
}

export default function ResetPasswordPage() {
  return (
    <Shell>
      <Suspense fallback={null}>
        <ResetForm />
      </Suspense>
    </Shell>
  );
}

function Notice({ title, body, tone = "info" }: { title: string; body: string; tone?: "info" | "ok" }) {
  return (
    <div className="w-full max-w-sm">
      <h2 className="font-display text-2xl font-bold text-ink">{title}</h2>
      <div className={`mt-4 rounded-lg px-3 py-2 text-sm ${tone === "ok" ? "bg-emerald-50 text-emerald-700" : "bg-slate-100 text-slate-600"}`}>
        {body}
      </div>
      <Link href="/login" className="mt-4 inline-block text-sm text-brand-600 hover:text-brand-700">
        Back to sign in
      </Link>
    </div>
  );
}

export function Shell({ children }: { children: React.ReactNode }) {
  return (
    <div className="grid min-h-screen lg:grid-cols-2">
      <div className="relative hidden flex-col justify-between bg-ink p-12 text-white lg:flex">
        <Logo />
        <p className="font-mono text-xs text-slate-500">call → webhook → transcription → scoring → dashboard</p>
      </div>
      <div className="flex items-center justify-center bg-white p-8">{children}</div>
    </div>
  );
}

const inputCls =
  "mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30";
