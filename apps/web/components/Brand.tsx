// Waveform mark — the CallLens logomark. Animated equalizer when `animate`.
export function Wave({ className = "", animate = false }: { className?: string; animate?: boolean }) {
  const bars = [8, 18, 12, 20, 9];
  return (
    <span className={`inline-flex items-end gap-[3px] text-brand-300 ${className}`} aria-hidden>
      {bars.map((h, i) => (
        <span
          key={i}
          className={`w-[3px] rounded-full bg-current ${animate ? "wave-bar" : ""}`}
          style={{ height: `${h}px`, animationDelay: animate ? `${i * 0.1}s` : undefined }}
        />
      ))}
    </span>
  );
}

export function Logo() {
  return (
    <span className="flex items-center gap-2.5">
      <Wave />
      <span className="font-display text-lg font-bold tracking-tight text-white">CallLens</span>
    </span>
  );
}

export function ScoreBadge({ score }: { score: number | null }) {
  if (score == null) return <span className="text-slate-400 text-sm">—</span>;
  const tone =
    score >= 75 ? "bg-brand-50 text-brand-700" : score >= 50 ? "bg-api-50 text-api-700" : "bg-rose-50 text-rose-700";
  return (
    <span className={`inline-flex items-center rounded-md px-2 py-0.5 text-sm font-semibold ${tone}`}>
      {Math.round(score)}
    </span>
  );
}

const STATUS_TONE: Record<string, string> = {
  completed: "bg-brand-50 text-brand-700",
  failed: "bg-rose-50 text-rose-700",
};

export function StatusBadge({ status }: { status: string }) {
  const tone = STATUS_TONE[status] ?? "bg-slate-100 text-slate-600";
  return (
    <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize ${tone}`}>
      {status}
    </span>
  );
}
