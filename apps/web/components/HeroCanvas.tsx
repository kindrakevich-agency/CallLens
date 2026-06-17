"use client";

import { useEffect, useRef } from "react";

/**
 * Animated waveform that morphs toward a bar chart — the "call becomes data"
 * motif from the brand guide. Respects prefers-reduced-motion.
 */
export function HeroCanvas() {
  const ref = useRef<HTMLCanvasElement>(null);

  useEffect(() => {
    const canvas = ref.current;
    if (!canvas) return;
    const ctx = canvas.getContext("2d");
    if (!ctx) return;

    const reduce = window.matchMedia?.("(prefers-reduced-motion: reduce)").matches;
    let raf = 0;
    let t = 0;
    let W = 0;
    let H = 0;
    let cols = 0;
    let slot = 0;
    let targets: number[] = [];

    const layout = () => {
      const r = canvas.getBoundingClientRect();
      W = r.width;
      H = r.height;
      const dpr = Math.min(window.devicePixelRatio || 1, 2);
      canvas.width = Math.floor(W * dpr);
      canvas.height = Math.floor(H * dpr);
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
      cols = W < 640 ? 24 : 44;
      slot = W / cols;
      targets = Array.from({ length: cols }, (_, i) => {
        const p = i / (cols - 1);
        return Math.max(0.12, Math.min(0.96, 0.2 + 0.66 * p + 0.13 * Math.sin(i * 0.8 + 1.2)));
      });
    };

    const smooth = (x: number) => x * x * (3 - 2 * x);

    const frame = () => {
      t += 0.022;
      ctx.clearRect(0, 0, W, H);
      const baseline = H * 0.82;
      const cy = H * 0.46;
      for (let i = 0; i < cols; i++) {
        const p = i / (cols - 1);
        const b = smooth(p);
        const x = i * slot + slot * 0.16;
        const w = slot * 0.68;
        let aMod = 0.35 + 0.65 * Math.abs(Math.sin(t * 1.7 + i * 0.6) * Math.cos(t * 0.8 + i * 0.27) + 0.25 * Math.sin(t * 3.1 + i));
        if (aMod > 1) aMod = 1;
        const ha = aMod * H * 0.42;
        const topA = cy - ha / 2;
        const botA = cy + ha / 2;
        const hc = targets[i] * H * 0.58 * (0.95 + 0.05 * Math.sin(t * 1.1 + i * 0.5));
        const top = topA * (1 - b) + (baseline - hc) * b;
        const bot = botA * (1 - b) + baseline * b;
        const cr = Math.round(94 + (251 - 94) * b);
        const cg = Math.round(234 + (191 - 234) * b);
        const cb = Math.round(212 + (36 - 212) * b);
        ctx.fillStyle = `rgba(${cr},${cg},${cb},${0.22 + 0.5 * b})`;
        const radius = Math.min(w / 2, 4);
        rounded(ctx, x, top, w, bot - top, radius);
        ctx.fill();
      }
      if (!reduce) raf = requestAnimationFrame(frame);
    };

    const start = () => {
      layout();
      if (W) frame();
    };
    const onResize = () => {
      cancelAnimationFrame(raf);
      start();
    };

    start();
    window.addEventListener("resize", onResize);
    return () => {
      cancelAnimationFrame(raf);
      window.removeEventListener("resize", onResize);
    };
  }, []);

  return <canvas ref={ref} aria-hidden className="pointer-events-none absolute inset-0 h-full w-full" />;
}

function rounded(ctx: CanvasRenderingContext2D, x: number, y: number, w: number, h: number, r: number) {
  if (h < 0) {
    y += h;
    h = -h;
  }
  r = Math.min(r, w / 2, h / 2);
  ctx.beginPath();
  ctx.moveTo(x + r, y);
  ctx.arcTo(x + w, y, x + w, y + h, r);
  ctx.arcTo(x + w, y + h, x, y + h, r);
  ctx.arcTo(x, y + h, x, y, r);
  ctx.arcTo(x, y, x + w, y, r);
  ctx.closePath();
}
