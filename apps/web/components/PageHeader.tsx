export function PageHeader({
  title,
  subtitle,
  children,
}: {
  title: string;
  subtitle?: string;
  children?: React.ReactNode;
}) {
  return (
    <header className="flex items-start justify-between gap-4 border-b border-slate-200 bg-white px-8 py-5">
      <div>
        <h1 className="font-display text-2xl font-bold tracking-tight text-ink">{title}</h1>
        {subtitle && <p className="mt-0.5 text-sm text-slate-500">{subtitle}</p>}
      </div>
      {children}
    </header>
  );
}
