import React, { useMemo, useState } from 'react';
import { useElementSize } from './hooks.js';

const shortDay = (iso) => {
  const date = new Date(`${iso}T00:00:00`);
  return Number.isNaN(date.getTime())
    ? iso
    : date.toLocaleDateString('en-IN', { day: '2-digit', month: 'short' });
};

/** Smooth area chart of daily order intake with a hover readout. */
export function TrendChart({ points }) {
  const [ref, { width, height }] = useElementSize(640, 220);
  const [active, setActive] = useState(null);
  const pad = { top: 18, right: 12, bottom: 28, left: 34 };

  const geometry = useMemo(() => {
    const max = Math.max(4, ...points.map((p) => p.total));
    const niceMax = Math.ceil(max / 4) * 4;
    const innerW = Math.max(10, width - pad.left - pad.right);
    const innerH = height - pad.top - pad.bottom;
    const step = points.length > 1 ? innerW / (points.length - 1) : innerW;
    const coords = points.map((p, i) => ({
      ...p,
      x: pad.left + i * step,
      y: pad.top + innerH - (p.total / niceMax) * innerH,
    }));
    // Catmull-Rom → cubic Bézier for a soft, honest curve.
    let line = '';
    coords.forEach((c, i) => {
      if (i === 0) {
        line = `M${c.x},${c.y}`;
        return;
      }
      const p0 = coords[i - 2] || coords[i - 1];
      const p1 = coords[i - 1];
      const p2 = c;
      const p3 = coords[i + 1] || c;
      const t = 0.18;
      const cp1x = p1.x + (p2.x - p0.x) * t;
      const cp1y = Math.min(pad.top + innerH, p1.y + (p2.y - p0.y) * t);
      const cp2x = p2.x - (p3.x - p1.x) * t;
      const cp2y = Math.min(pad.top + innerH, p2.y - (p3.y - p1.y) * t);
      line += ` C${cp1x},${cp1y} ${cp2x},${cp2y} ${p2.x},${p2.y}`;
    });
    const base = pad.top + innerH;
    const area = coords.length
      ? `${line} L${coords[coords.length - 1].x},${base} L${coords[0].x},${base} Z`
      : '';
    const ticks = [0, 0.25, 0.5, 0.75, 1].map((f) => ({
      value: Math.round(niceMax * f),
      y: pad.top + innerH - f * innerH,
    }));
    return { coords, line, area, ticks, step };
  }, [points, width, height]);

  const total = points.reduce((sum, p) => sum + p.total, 0);
  const hovered = active !== null ? geometry.coords[active] : null;

  const handleMove = (event) => {
    const rect = event.currentTarget.getBoundingClientRect();
    const x = event.clientX - rect.left - pad.left;
    const index = Math.round(x / (geometry.step || 1));
    setActive(Math.max(0, Math.min(points.length - 1, index)));
  };

  return (
    <div className="au-chart" ref={ref}>
      <svg
        width={width}
        height={height}
        role="img"
        aria-label={`Orders created over the last ${points.length} days: ${total} in total`}
        onMouseMove={handleMove}
        onMouseLeave={() => setActive(null)}
      >
        <defs>
          <linearGradient id="auTrendFill" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stopColor="var(--au-gold)" stopOpacity="0.32" />
            <stop offset="100%" stopColor="var(--au-gold)" stopOpacity="0" />
          </linearGradient>
          <linearGradient id="auTrendStroke" x1="0" y1="0" x2="1" y2="0">
            <stop offset="0%" stopColor="var(--au-gold-lo)" />
            <stop offset="60%" stopColor="var(--au-gold)" />
            <stop offset="100%" stopColor="var(--au-gold-hi)" />
          </linearGradient>
        </defs>
        {geometry.ticks.map((tick) => (
          <g key={tick.y}>
            <line x1={pad.left} x2={width - pad.right} y1={tick.y} y2={tick.y} className="au-chart__grid" />
            <text x={pad.left - 10} y={tick.y + 4} textAnchor="end" className="au-chart__axis">
              {tick.value}
            </text>
          </g>
        ))}
        <path d={geometry.area} fill="url(#auTrendFill)" className="au-chart__area" />
        <path d={geometry.line} fill="none" stroke="url(#auTrendStroke)" strokeWidth="2.4" className="au-chart__line" />
        {geometry.coords.map((c, i) => {
          const labelStep = Math.ceil(points.length / (width < 480 ? 4 : 7));
          const last = points.length - 1;
          const show = i === last || (i % labelStep === 0 && last - i >= labelStep / 2);
          return show ? (
            <text key={c.day} x={c.x} y={height - 8} textAnchor="middle" className="au-chart__axis">
              {shortDay(c.day)}
            </text>
          ) : null;
        })}
        {hovered && (
          <g>
            <line x1={hovered.x} x2={hovered.x} y1={pad.top} y2={height - pad.bottom} className="au-chart__cursor" />
            <circle cx={hovered.x} cy={hovered.y} r="9" className="au-chart__halo" />
            <circle cx={hovered.x} cy={hovered.y} r="4.5" className="au-chart__dot" />
          </g>
        )}
      </svg>
      {hovered && (
        <div
          className="au-chart__tip"
          style={{ left: Math.min(Math.max(hovered.x, 60), width - 60), top: Math.max(hovered.y - 54, 0) }}
        >
          <strong>{hovered.total}</strong> {hovered.total === 1 ? 'order' : 'orders'}
          <span>{shortDay(hovered.day)}</span>
        </div>
      )}
    </div>
  );
}

const STATUS_COLORS = {
  Confirmed: 'var(--au-sapphire)',
  'In Production': 'var(--au-amber)',
  QC: 'var(--au-violet)',
  Ready: 'var(--au-emerald)',
  Packed: 'var(--au-teal, #38c6d9)',
  Dispatched: 'var(--au-gold)',
  Completed: 'var(--au-lime, #8fd16a)',
  Cancelled: 'var(--au-ruby)',
};
const FALLBACK_COLORS = ['#c08fd8', '#7fb6c9', '#d79a6a', '#9aa3b5', '#e0c36e'];

export const statusColor = (status, index = 0) =>
  STATUS_COLORS[status] || FALLBACK_COLORS[index % FALLBACK_COLORS.length];

/** Ring chart of the order pipeline by status. */
export function StatusDonut({ items }) {
  const [active, setActive] = useState(null);
  const sorted = [...items].filter((i) => i.total > 0).sort((a, b) => b.total - a.total);
  const total = sorted.reduce((sum, i) => sum + i.total, 0);
  const size = 184;
  const stroke = 18;
  const radius = (size - stroke) / 2;
  const circumference = 2 * Math.PI * radius;
  const gap = sorted.length > 1 ? 3 : 0;

  let offset = 0;
  const arcs = sorted.map((item, index) => {
    const length = total ? (item.total / total) * circumference : 0;
    const arc = {
      ...item,
      color: statusColor(item.status, index),
      dash: `${Math.max(0, length - gap)} ${circumference}`,
      offset: -offset,
    };
    offset += length;
    return arc;
  });

  const focus = active !== null ? arcs[active] : null;

  if (!total) {
    return <div className="au-empty au-empty--compact">No orders recorded yet.</div>;
  }

  return (
    <div className="au-donut">
      <div className="au-donut__ring">
        <svg width={size} height={size} viewBox={`0 0 ${size} ${size}`} role="img" aria-label="Orders by status">
          <circle cx={size / 2} cy={size / 2} r={radius} className="au-donut__track" strokeWidth={stroke} fill="none" />
          {arcs.map((arc, index) => (
            <circle
              key={arc.status}
              cx={size / 2}
              cy={size / 2}
              r={radius}
              fill="none"
              stroke={arc.color}
              strokeWidth={active === index ? stroke + 4 : stroke}
              strokeDasharray={arc.dash}
              strokeDashoffset={arc.offset}
              strokeLinecap="round"
              transform={`rotate(-90 ${size / 2} ${size / 2})`}
              className="au-donut__arc"
              style={{ opacity: active === null || active === index ? 1 : 0.28 }}
              onMouseEnter={() => setActive(index)}
              onMouseLeave={() => setActive(null)}
            />
          ))}
        </svg>
        <div className="au-donut__center">
          <strong>{focus ? focus.total : total}</strong>
          <span>{focus ? focus.status : 'Total orders'}</span>
        </div>
      </div>
      <ul className="au-legend">
        {arcs.map((arc, index) => (
          <li
            key={arc.status}
            className={active === index ? 'is-active' : ''}
            onMouseEnter={() => setActive(index)}
            onMouseLeave={() => setActive(null)}
          >
            <i style={{ background: arc.color }} />
            <span>{arc.status}</span>
            <b>{arc.total}</b>
            <em>{Math.round((arc.total / total) * 100)}%</em>
          </li>
        ))}
      </ul>
    </div>
  );
}
