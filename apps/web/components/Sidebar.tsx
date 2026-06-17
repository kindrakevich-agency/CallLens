"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { Logo } from "./Brand";
import { useAuth } from "@/lib/auth";

const NAV = [
  { href: "/app/calls", label: "Calls" },
  { href: "/app/search", label: "Search" },
  { href: "/app/agents", label: "Agents" },
  { href: "/app/scorecards", label: "Scorecards" },
  { href: "/app/settings", label: "Settings" },
];

export function Sidebar() {
  const pathname = usePathname();
  const { user, logout } = useAuth();

  return (
    <aside className="flex w-60 shrink-0 flex-col bg-ink text-slate-200">
      <div className="px-5 py-5">
        <Link href="/app/calls">
          <Logo />
        </Link>
      </div>

      <nav className="flex-1 px-3">
        {NAV.map((item) => {
          const active = pathname === item.href || pathname.startsWith(item.href + "/");
          return (
            <Link
              key={item.href}
              href={item.href}
              className={`mb-1 block rounded-lg px-3 py-2 text-sm font-medium transition ${
                active ? "bg-white/10 text-white" : "text-slate-300 hover:bg-white/5 hover:text-white"
              }`}
            >
              {item.label}
            </Link>
          );
        })}
      </nav>

      <div className="border-t border-white/10 px-5 py-4 text-sm">
        <div className="truncate font-medium text-white">{user?.name}</div>
        <div className="truncate text-xs text-slate-400">{user?.tenant.name}</div>
        <button
          onClick={() => logout()}
          className="mt-3 text-xs font-medium text-brand-300 hover:text-brand-100"
        >
          Sign out
        </button>
      </div>
    </aside>
  );
}
