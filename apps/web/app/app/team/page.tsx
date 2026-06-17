"use client";

import { useCallback, useEffect, useState } from "react";
import { api, ApiError } from "@/lib/api";
import { useAuth } from "@/lib/auth";
import { PageHeader } from "@/components/PageHeader";

type Member = {
  id: string;
  email: string;
  name: string;
  role: string;
  email_verified: boolean;
  is_self: boolean;
};

const ROLES = ["owner", "admin", "manager", "viewer"];
const ROLE_RANK: Record<string, number> = { owner: 3, admin: 2, manager: 1, viewer: 0 };

export default function TeamPage() {
  const { user } = useAuth();
  const [members, setMembers] = useState<Member[] | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [inviting, setInviting] = useState(false);

  const canManage = (ROLE_RANK[user?.role ?? "viewer"] ?? 0) >= ROLE_RANK.admin;
  const canGrantOwner = user?.role === "owner";

  const load = useCallback(() => {
    api
      .team()
      .then((d: { items: Member[] }) => setMembers(d.items))
      .catch((e) => setError(String(e.message ?? e)));
  }, []);
  useEffect(() => {
    load();
  }, [load]);

  async function setRole(m: Member, role: string) {
    setError(null);
    try {
      await api.changeMemberRole(m.id, role);
      load();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : "Could not change role.");
    }
  }

  async function remove(m: Member) {
    if (!confirm(`Remove ${m.name} from the workspace?`)) return;
    setError(null);
    try {
      await api.removeMember(m.id);
      load();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : "Could not remove member.");
    }
  }

  return (
    <>
      <PageHeader title="Team" subtitle="Members of your workspace and their roles.">
        {canManage && (
          <button
            onClick={() => setInviting(true)}
            className="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"
          >
            Invite member
          </button>
        )}
      </PageHeader>

      {inviting && (
        <InviteDialog
          canGrantOwner={canGrantOwner}
          onClose={() => setInviting(false)}
          onDone={() => {
            setInviting(false);
            load();
          }}
        />
      )}

      <div className="p-8">
        {error && <div className="mb-4 rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700">{error}</div>}
        <div className="overflow-hidden rounded-xl border border-slate-200 bg-white">
          <table className="w-full text-sm">
            <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
              <tr>
                <th className="px-4 py-3 font-medium">Member</th>
                <th className="px-4 py-3 font-medium">Role</th>
                <th className="px-4 py-3 font-medium">Email</th>
                {canManage && <th className="px-4 py-3" />}
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {members?.map((m) => {
                const editable = canManage && !m.is_self;
                return (
                  <tr key={m.id} className="hover:bg-slate-50">
                    <td className="px-4 py-3">
                      <div className="font-medium text-ink">
                        {m.name}
                        {m.is_self && <span className="ml-2 text-xs text-slate-400">you</span>}
                      </div>
                      <div className="text-xs text-slate-500">{m.email}</div>
                    </td>
                    <td className="px-4 py-3">
                      {editable ? (
                        <select
                          value={m.role}
                          onChange={(e) => setRole(m, e.target.value)}
                          className="rounded-lg border border-slate-300 px-2 py-1 text-sm focus:border-brand-500 focus:outline-none"
                        >
                          {ROLES.filter((r) => r !== "owner" || canGrantOwner).map((r) => (
                            <option key={r} value={r}>
                              {r}
                            </option>
                          ))}
                        </select>
                      ) : (
                        <span className="capitalize text-slate-700">{m.role}</span>
                      )}
                    </td>
                    <td className="px-4 py-3">
                      {m.email_verified ? (
                        <span className="text-xs text-emerald-600">verified</span>
                      ) : (
                        <span className="text-xs text-amber-600">pending</span>
                      )}
                    </td>
                    {canManage && (
                      <td className="px-4 py-3 text-right">
                        {editable && (
                          <button onClick={() => remove(m)} className="text-xs text-rose-600 hover:underline">
                            Remove
                          </button>
                        )}
                      </td>
                    )}
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
        {!canManage && (
          <p className="mt-4 text-xs text-slate-400">Only owners and admins can invite members or change roles.</p>
        )}
      </div>
    </>
  );
}

function InviteDialog({
  canGrantOwner,
  onClose,
  onDone,
}: {
  canGrantOwner: boolean;
  onClose: () => void;
  onDone: () => void;
}) {
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [role, setRole] = useState("viewer");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [tempPassword, setTempPassword] = useState<string | null>(null);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError(null);
    try {
      const res = await api.inviteMember({ name, email, role });
      setTempPassword(res.temporary_password);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Could not invite.");
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-ink/40 p-4" onClick={onClose}>
      <div onClick={(e) => e.stopPropagation()} className="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
        {tempPassword ? (
          <>
            <h2 className="font-display text-lg font-semibold text-ink">Member invited</h2>
            <p className="mt-2 text-sm text-slate-600">
              Share this one-time password with <span className="font-medium">{email}</span>. They can sign in and
              then set their own password from the reset flow.
            </p>
            <div className="mt-3 rounded-lg bg-slate-100 px-3 py-2 font-mono text-sm text-ink">{tempPassword}</div>
            <div className="mt-6 flex justify-end">
              <button
                onClick={onDone}
                className="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"
              >
                Done
              </button>
            </div>
          </>
        ) : (
          <form onSubmit={submit}>
            <h2 className="font-display text-lg font-semibold text-ink">Invite a member</h2>
            {error && <div className="mt-3 rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700">{error}</div>}
            <label className="mt-4 block text-sm font-medium text-slate-700">
              Name
              <input
                value={name}
                onChange={(e) => setName(e.target.value)}
                required
                className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none"
              />
            </label>
            <label className="mt-4 block text-sm font-medium text-slate-700">
              Email
              <input
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
                className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none"
              />
            </label>
            <label className="mt-4 block text-sm font-medium text-slate-700">
              Role
              <select
                value={role}
                onChange={(e) => setRole(e.target.value)}
                className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none"
              >
                {ROLES.filter((r) => r !== "owner" || canGrantOwner).map((r) => (
                  <option key={r} value={r}>
                    {r}
                  </option>
                ))}
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
                {busy ? "Inviting…" : "Invite"}
              </button>
            </div>
          </form>
        )}
      </div>
    </div>
  );
}
