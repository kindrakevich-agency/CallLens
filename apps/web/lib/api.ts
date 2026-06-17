// Browser → CallLens API client. Requests are same-origin (the Next app proxies
// /api and /auth to the backend — see next.config.ts), so the auth cookie is
// first-party and works in every browser. `credentials: "same-origin"` suffices.
async function req(path: string, opts: RequestInit = {}): Promise<Response> {
  return fetch(path, {
    credentials: "same-origin",
    ...opts,
  });
}

async function json(path: string, opts: RequestInit = {}) {
  const res = await req(path, {
    headers: { "Content-Type": "application/json", ...(opts.headers ?? {}) },
    ...opts,
  });
  if (!res.ok) throw new ApiError(res.status, await safeText(res));
  return res.status === 204 ? null : res.json();
}

async function safeText(res: Response): Promise<string> {
  try {
    return (await res.json())?.error ?? res.statusText;
  } catch {
    return res.statusText;
  }
}

export class ApiError extends Error {
  constructor(public status: number, message: string) {
    super(message);
  }
}

export type CallSummary = {
  id: string;
  external_id: string;
  status: string;
  overall_score: number | null;
  agent: { id: string; name: string } | null;
  channels: string;
  language: string;
};

export type CallDetail = CallSummary & {
  audio_available: boolean;
  transcript: string | null;
  utterances: { speaker: string; start_ms: number; text: string }[];
  criterion_scores: {
    key: string;
    score: number;
    max_score: number;
    evidence_quote: string | null;
    rationale: string | null;
  }[];
};

export const api = {
  // --- auth ---
  async me() {
    const res = await req("/auth/me");
    return res.ok ? res.json() : null;
  },
  login(email: string, password: string) {
    return json("/auth/login", { method: "POST", body: JSON.stringify({ email, password }) });
  },
  register(payload: { email: string; password: string; name: string; workspace?: string }) {
    return json("/auth/register", { method: "POST", body: JSON.stringify(payload) });
  },
  logout() {
    return json("/auth/logout", { method: "POST" });
  },

  // --- calls ---
  calls(params: { status?: string; agent_id?: string; page?: number } = {}) {
    const qs = new URLSearchParams(
      Object.entries(params).filter(([, v]) => v != null && v !== "") as [string, string][],
    ).toString();
    return json(`/api/v1/calls${qs ? `?${qs}` : ""}`);
  },
  call(id: string): Promise<CallDetail> {
    return json(`/api/v1/calls/${id}`);
  },
  upload(file: File, externalId?: string) {
    const fd = new FormData();
    fd.append("audio", file);
    if (externalId) fd.append("external_id", externalId);
    return json("/api/v1/calls/upload", { method: "POST", body: fd, headers: {} });
  },

  // --- search ---
  search(query: string, limit = 15) {
    return json("/api/v1/search", { method: "POST", body: JSON.stringify({ query, limit }) });
  },

  // --- agents ---
  agents() {
    return json("/api/v1/agents");
  },

  // --- scorecards ---
  scorecards() {
    return json("/api/v1/scorecards");
  },

  // --- reports / analytics ---
  reports() {
    return json("/api/v1/reports");
  },

  // --- settings ---
  webhooks() {
    return json("/api/v1/settings/webhooks");
  },
  rotateWebhook(id: string) {
    return json(`/api/v1/settings/webhooks/${id}/rotate`, { method: "POST" });
  },
  createWebhook(sourceType: string) {
    return json("/api/v1/settings/webhooks", { method: "POST", body: JSON.stringify({ source_type: sourceType }) });
  },
  retention() {
    return json("/api/v1/settings/retention");
  },
  setRetention(mode: string, days: number) {
    return json("/api/v1/settings/retention", { method: "PUT", body: JSON.stringify({ mode, days }) });
  },
};
