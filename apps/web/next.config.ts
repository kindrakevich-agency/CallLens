import type { NextConfig } from "next";

// Server-side proxy target (inside the web container the API is reachable as the
// `nginx` service). Browser requests stay same-origin so the auth cookie is
// first-party — this avoids cross-origin cookie blocking (Safari ITP, Chrome
// third-party-cookie blocking) entirely.
const apiProxyTarget = process.env.API_PROXY_TARGET ?? "http://nginx";

// Pragmatic CSP that works in dev (HMR needs ws/eval) and prod. A nonce-based
// strict CSP (dropping unsafe-inline/eval) is a planned refinement.
const csp = [
  "default-src 'self'",
  "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
  "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
  "font-src 'self' https://fonts.gstatic.com",
  "img-src 'self' data: blob:",
  "connect-src 'self' ws: wss:",
  "frame-ancestors 'none'",
  "base-uri 'self'",
].join("; ");

const securityHeaders = [
  { key: "X-Content-Type-Options", value: "nosniff" },
  { key: "X-Frame-Options", value: "DENY" },
  { key: "Referrer-Policy", value: "strict-origin-when-cross-origin" },
  { key: "Permissions-Policy", value: "geolocation=(), microphone=(), camera=()" },
  { key: "Content-Security-Policy", value: csp },
];

const nextConfig: NextConfig = {
  async headers() {
    return [{ source: "/:path*", headers: securityHeaders }];
  },
  async rewrites() {
    return [
      { source: "/api/:path*", destination: `${apiProxyTarget}/api/:path*` },
      { source: "/auth/:path*", destination: `${apiProxyTarget}/auth/:path*` },
    ];
  },
};

export default nextConfig;
