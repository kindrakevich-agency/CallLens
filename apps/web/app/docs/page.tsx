// Documentation site (route "/docs"). MDX content lands here in M9:
// how it works, how to connect, reports, cabinet guide, security, API reference.
export default function DocsHome() {
  return (
    <main className="mx-auto max-w-3xl px-6 py-20">
      <h1 className="text-3xl font-bold tracking-tight">CallLens documentation</h1>
      <p className="mt-4 text-slate-600">
        Guides for connecting your telephony, understanding reports, and using the
        cabinet. Full MDX docs are added in milestone M9.
      </p>
    </main>
  );
}
