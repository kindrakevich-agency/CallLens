"use client";

import { useEffect, useState } from "react";
import { api } from "@/lib/api";
import { PageHeader } from "@/components/PageHeader";

type Agent = { id: string; name: string; external_id: string | null; is_active: boolean };

export default function AgentsPage() {
  const [agents, setAgents] = useState<Agent[] | null>(null);

  useEffect(() => {
    api.agents().then((d) => setAgents(d.items));
  }, []);

  return (
    <>
      <PageHeader title="Agents" subtitle="Sales reps evaluated by CallLens." />
      <div className="p-8">
        <div className="overflow-hidden rounded-xl border border-slate-200 bg-white">
          <table className="w-full text-sm">
            <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
              <tr>
                <th className="px-4 py-3 font-medium">Name</th>
                <th className="px-4 py-3 font-medium">External ID</th>
                <th className="px-4 py-3 font-medium">Active</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {agents?.map((a) => (
                <tr key={a.id}>
                  <td className="px-4 py-3 font-medium text-ink">{a.name}</td>
                  <td className="px-4 py-3 font-mono text-slate-500">{a.external_id ?? "—"}</td>
                  <td className="px-4 py-3">{a.is_active ? "Yes" : "No"}</td>
                </tr>
              ))}
              {agents && agents.length === 0 && (
                <tr>
                  <td colSpan={3} className="px-4 py-10 text-center text-slate-400">
                    Agents are created automatically from incoming calls.
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
