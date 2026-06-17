import Link from "next/link";
import { Wave } from "@/components/Brand";

function Code({ children }: { children: React.ReactNode }) {
  return (
    <pre className="mt-3 overflow-x-auto rounded-lg bg-ink p-4 font-mono text-xs leading-relaxed text-slate-200">
      {children}
    </pre>
  );
}

export default function WebhookDocs() {
  return (
    <div className="min-h-screen bg-white text-ink">
      <header className="border-b border-slate-200">
        <div className="mx-auto flex h-16 max-w-3xl items-center justify-between px-5">
          <Link href="/docs" className="flex items-center gap-2.5">
            <span className="text-brand-600">
              <Wave />
            </span>
            <span className="font-display text-lg font-bold tracking-tight">CallLens Docs</span>
          </Link>
          <Link href="/login" className="text-sm font-medium text-brand-600 hover:text-brand-700">
            Sign in
          </Link>
        </div>
      </header>

      <main className="mx-auto max-w-3xl px-5 py-14">
        <Link href="/docs" className="text-sm text-brand-600 hover:underline">← Docs</Link>
        <h1 className="mt-3 font-display text-3xl font-bold tracking-tight">Connect a webhook</h1>
        <p className="mt-3 text-slate-600">
          Send call payloads to CallLens with an HMAC-signed request. You get the endpoint id and
          signing secret in <span className="font-medium">Settings → Webhook ingestion</span>.
        </p>

        <h2 className="mt-10 font-display text-xl font-semibold">Endpoint</h2>
        <Code>POST https://api.calllens.app/v1/webhooks/calls</Code>

        <h2 className="mt-8 font-display text-xl font-semibold">Headers</h2>
        <ul className="mt-3 space-y-2 text-sm text-slate-700">
          <li><code className="font-mono text-brand-700">X-CallLens-Endpoint</code> — your endpoint UUID.</li>
          <li><code className="font-mono text-brand-700">X-CallLens-Timestamp</code> — ISO-8601 UTC; rejected outside a 300-second window.</li>
          <li><code className="font-mono text-brand-700">X-CallLens-Signature</code> — <code>sha256=&lt;hmac&gt;</code> (see below).</li>
        </ul>

        <h2 className="mt-8 font-display text-xl font-semibold">Signature</h2>
        <p className="mt-2 text-sm text-slate-600">
          HMAC-SHA256 over <code className="font-mono">timestamp + &quot;.&quot; + rawBody</code> using your signing
          secret — the timestamp is bound, so a replayed request can&apos;t reuse an old signature with a
          fresh timestamp.
        </p>
        <Code>{`TS=$(date -u +%Y-%m-%dT%H:%M:%SZ)
BODY='{"call_id":"c_8421","recording_url":"https://.../rec.mp3","channels":"dual"}'
SIG="sha256=$(printf '%s' "$TS.$BODY" | openssl dgst -sha256 -hmac "$SECRET" | awk '{print $NF}')"

curl -X POST https://api.calllens.app/v1/webhooks/calls \\
  -H "X-CallLens-Endpoint: $ENDPOINT_ID" \\
  -H "X-CallLens-Timestamp: $TS" \\
  -H "X-CallLens-Signature: $SIG" \\
  -H "Content-Type: application/json" \\
  -d "$BODY"`}</Code>

        <h2 className="mt-8 font-display text-xl font-semibold">Payload</h2>
        <Code>{`{
  "call_id": "c_8421",            // required — dedup key per workspace
  "recording_url": "https://.../rec.mp3",
  "agent_id": "mgr_17",           // your rep id (auto-creates an agent)
  "channels": "dual",             // dual (recommended) | mono
  "language": "auto",
  "started_at": "2026-06-17T09:14:02Z",
  "duration_sec": 312
}`}</Code>

        <h2 className="mt-8 font-display text-xl font-semibold">Response</h2>
        <p className="mt-2 text-sm text-slate-600">
          <code className="font-mono">202 Accepted</code> — processing is asynchronous. Re-delivering the
          same <code className="font-mono">call_id</code> is safe (idempotent). Errors:
          <code className="font-mono"> 401</code> (unknown endpoint / stale timestamp / bad signature),
          <code className="font-mono"> 422</code> (invalid JSON or missing <code className="font-mono">call_id</code>).
        </p>
        <Code>{`{ "status": "accepted", "call_id": "c_8421", "duplicate": false }`}</Code>

        <p className="mt-10 text-sm text-slate-500">
          Dual-channel audio (rep and customer on separate channels) is recommended — it gives the most
          accurate speaker separation.
        </p>
      </main>
    </div>
  );
}
