// Authenticated cabinet (route "/app"). Calls list/detail, scorecard editor,
// agents, semantic search, analytics and settings are built from M6 onward.
export default function CabinetHome() {
  return (
    <main className="mx-auto max-w-5xl px-6 py-20">
      <h1 className="text-3xl font-bold tracking-tight">Cabinet</h1>
      <p className="mt-4 text-slate-600">
        The authenticated workspace. Auth &amp; tenancy arrive in M1; calls,
        scorecards, search and analytics from M6.
      </p>
    </main>
  );
}
