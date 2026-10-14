import { useEffect, useRef, useState } from 'react';

const prefersReducedMotion = () =>
  typeof window !== 'undefined' &&
  window.matchMedia &&
  window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/** Animates a number from 0 to `target` once on mount. */
export function useCountUp(target, duration = 900) {
  const [value, setValue] = useState(prefersReducedMotion() ? target : 0);

  useEffect(() => {
    if (prefersReducedMotion() || !Number.isFinite(target)) {
      setValue(target);
      return undefined;
    }
    let frame;
    const start = performance.now();
    const tick = (now) => {
      const progress = Math.min(1, (now - start) / duration);
      const eased = 1 - Math.pow(1 - progress, 3);
      setValue(target * eased);
      if (progress < 1) frame = requestAnimationFrame(tick);
    };
    frame = requestAnimationFrame(tick);
    return () => cancelAnimationFrame(frame);
  }, [target, duration]);

  return value;
}

/** Tracks an element's rendered size so SVG charts stay crisp at any size. */
export function useElementSize(fallbackWidth = 600, fallbackHeight = 220) {
  const ref = useRef(null);
  const [size, setSize] = useState({ width: fallbackWidth, height: fallbackHeight });

  useEffect(() => {
    const node = ref.current;
    if (!node) return undefined;
    const read = () => ({
      width: node.clientWidth || fallbackWidth,
      height: node.clientHeight || fallbackHeight,
    });
    setSize(read());
    if (typeof ResizeObserver === 'undefined') return undefined;
    const observer = new ResizeObserver(() => {
      const next = read();
      setSize((prev) => (prev.width === next.width && prev.height === next.height ? prev : next));
    });
    observer.observe(node);
    return () => observer.disconnect();
  }, [fallbackWidth, fallbackHeight]);

  return [ref, size];
}
