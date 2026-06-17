"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { AuthProvider, useAuth } from "@/lib/auth";
import { Sidebar } from "@/components/Sidebar";

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
      <main className="flex-1 overflow-auto">{children}</main>
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
