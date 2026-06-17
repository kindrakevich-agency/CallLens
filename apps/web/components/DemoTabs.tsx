"use client";

import { useState } from "react";

const TABS = [
  {
    id: "ours",
    label: "Our demo",
    embed: "https://www.youtube-nocookie.com/embed/n4D3Tk9GQ6Y",
    title: "CallLens recognising a real call",
    caption: "Screen recording: CallLens transcribes and scores a real call inside the cabinet.",
  },
  {
    id: "example",
    label: "Example call",
    embed: "https://www.youtube-nocookie.com/embed/4ostqJD3Psc",
    title: "Example sales call",
    caption: "The real two-person sales call we ran through the pipeline — ~2 min, scored 75/100.",
  },
] as const;

export function DemoTabs() {
  const [active, setActive] = useState<(typeof TABS)[number]["id"]>("ours");
  const tab = TABS.find((t) => t.id === active)!;

  return (
    <div>
      <div className="inline-flex rounded-xl border border-slate-200 bg-slate-50 p-1" role="tablist">
        {TABS.map((t) => {
          const on = t.id === active;
          return (
            <button
              key={t.id}
              role="tab"
              aria-selected={on}
              onClick={() => setActive(t.id)}
              className={`rounded-lg px-4 py-2 text-sm font-medium transition ${
                on ? "bg-white text-ink shadow-sm" : "text-slate-500 hover:text-ink"
              }`}
            >
              {t.label}
            </button>
          );
        })}
      </div>

      <div className="mt-6">
        <div
          className="relative w-full overflow-hidden rounded-2xl border border-slate-200 bg-ink shadow-sm"
          style={{ aspectRatio: "16 / 9" }}
        >
          <iframe
            key={tab.id}
            className="absolute inset-0 h-full w-full"
            src={tab.embed}
            title={tab.title}
            loading="lazy"
            frameBorder={0}
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
            allowFullScreen
          />
        </div>
        <p className="mt-3 text-sm text-slate-500">{tab.caption}</p>
      </div>
    </div>
  );
}
