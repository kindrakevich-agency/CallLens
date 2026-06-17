"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { AuthProvider, useAuth } from "@/lib/auth";
import { api } from "@/lib/api";
import { Sidebar } from "@/components/Sidebar";

function VerifyBanner() {
  const { user } = useAuth();
  const [sent, setSent] = useState(false);
  if (!user || user.emailVerified) return null;
  return (
    <div className="flex items-center justify-between gap-4 bg-amber-50 px-6 py-2.5 text-sm text-amber-800">
      <span>Confirm your email to secure your account.</span>
      <button
        onClick={() => api.resendVerification().then(() => setSent(true))}
        disabled={sent}
        className="font-medium text-amber-900 underline disabled:no-underline disabled:opacity-60"
      >
        {sent ? "Verification email sent" : "Resend verification email"}
      </button>
    </div>
  );
}

function Guard({ children }: { children: React.ReactNode }) {
  const { user, loading } = useAuth();
  const router = useRouter();

  useEffect(() => {
    if (!loading && !user) router.replace("/login");
  }, [loading, user, router]);

  if (loading)
    return <div className="grid min-h-screen place-items-center text-slate-400">Loading…</div>;
  if (!user) return null;

  return (
    <div className="flex min-h-screen">
      <Sidebar />
      <main className="flex-1 overflow-auto">
        <VerifyBanner />
        {children}
      </main>
    </div>
  );
}

export default function CabinetLayout({ children }: { children: React.ReactNode }) {
  return (
    <AuthProvider>
      <Guard>{children}</Guard>
    </AuthProvider>
  );
}
