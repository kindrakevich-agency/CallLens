"use client";

import { Suspense, useEffect, useState } from "react";
import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { api, ApiError } from "@/lib/api";
import { Logo } from "@/components/Brand";

function Verifier() {
  const token = useSearchParams().get("token") ?? "";
  const [state, setState] = useState<"working" | "ok" | "error">(token ? "working" : "error");
  const [message, setMessage] = useState(
    token ? "Confirming your email…" : "This verification link is missing its token.",
  );

  useEffect(() => {
    if (!token) return;
    let active = true;
    api
      .verifyEmail(token)
      .then(() => {
        if (!active) return;
        setState("ok");
        setMessage("Your email is verified. You're all set.");
      })
      .catch((e) => {
        if (!active) return;
        setState("error");
        setMessage(e instanceof ApiError ? e.message : "This verification link is invalid or has expired.");
      });
    return () => {
      active = false;
    };
  }, [token]);

  return (
    <div className="w-full max-w-sm">
      <h2 className="font-display text-2xl font-bold text-ink">
        {state === "ok" ? "Email verified" : state === "error" ? "Verification failed" : "Verifying…"}
      </h2>
      <div
        className={`mt-4 rounded-lg px-3 py-2 text-sm ${
          state === "ok"
            ? "bg-emerald-50 text-emerald-700"
            : state === "error"
              ? "bg-rose-50 text-rose-700"
              : "bg-slate-100 text-slate-600"
        }`}
      >
        {message}
      </div>
      <Link href="/app/calls" className="mt-4 inline-block text-sm text-brand-600 hover:text-brand-700">
        Go to your workspace
      </Link>
    </div>
  );
}

export default function VerifyEmailPage() {
  return (
    <div className="grid min-h-screen lg:grid-cols-2">
      <div className="relative hidden flex-col justify-between bg-ink p-12 text-white lg:flex">
        <Logo />
        <p className="font-mono text-xs text-slate-500">call → webhook → transcription → scoring → dashboard</p>
      </div>
      <div className="flex items-center justify-center bg-white p-8">
        <Suspense fallback={null}>
          <Verifier />
        </Suspense>
      </div>
    </div>
  );
}
