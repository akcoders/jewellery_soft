import React, { useMemo, useState } from 'react';
import Icon from './icons.jsx';
import { useCountUp } from './hooks.js';
import { StatusDonut, TrendChart, statusColor } from './charts.jsx';

const inr = (value, digits = 0) =>
  Number(value || 0).toLocaleString('en-IN', { minimumFractionDigits: digits, maximumFractionDigits: digits });

const greeting = (hour) => {
  if (hour < 12) return 'Good morning';
  if (hour < 17) return 'Good afternoon';
  return 'Good evening';
};

function Counter({ value, digits = 0 }) {
  const animated = useCountUp(Number(value) || 0);
  return <>{inr(animated, digits)}</>;
}

function StatusPill({ status }) {
  return (
    <span className="au-pill" style={{ '--pill': statusColor(status) }}>
      <i />
      {status || '—'}
    </span>
  );
}

function Thumb({ src, icon }) {
  const [failed, setFailed] = useState(false);
  return (
    <span className="au-thumb">
      {src && !failed ? (
        <img src={src} alt="" loading="lazy" onError={() => setFailed(true)} />
      ) : (
        <Icon name={icon} size={16} />
      )}
    </span>
  );
}

function Hero({ data }) {
  const { can, urls } = data;
  return (
    <section className="au-hero" data-app-tour="dashboard-overview">
      <svg className="au-hero__facets" viewBox="0 0 400 240" aria-hidden="true">
        <path d="M120 20h160l80 70-200 140L-40 90z" />
        <path d="M120 20l-30 70 110 140 110-140-30-70M-40 90h400" />
        <path d="M90 90l110-70 110 70" />
      </svg>
      <div className="au-hero__copy">
        <span className="au-eyebrow">
          <Icon name="diamond" size={13} /> Jewellery operations · {data.today}
        </span>
        <h1>
          {greeting(data.hour)}, <em>{data.adminName}</em>
        </h1>
        <p>Your production floor, gold vault and customer pipeline in one place.</p>
        <div className="au-hero__actions">
          {can.createOrder && (
            <a className="au-btn au-btn--gold" href={urls.newOrder}>
              <Icon name="plus" size={16} /> New Order
            </a>
          )}
          {can.createCustomer && (
            <a className="au-btn au-btn--ghost" href={urls.newCustomer}>
              <Icon name="userPlus" size={16} /> Add Customer
            </a>
          )}
        </div>
      </div>
      <div className="au-hero__pulse">
        <div>
          <span>Active orders</span>
          <strong>
            <Counter value={data.counts.activeOrders} />
          </strong>
        </div>
        <div>
          <span>Dispatched today</span>
          <strong>
            <Counter value={data.counts.dispatchedToday} />
          </strong>
        </div>
        <div>
          <span>Fine gold</span>
          <strong>
            <Counter value={data.goldCards.fine_gold_total} digits={1} />
            <small>gm</small>
          </strong>
        </div>
      </div>
    </section>
  );
}

function KpiCard({ href, icon, label, value, caption, tone, alert }) {
  return (
    <a className={`au-kpi au-kpi--${tone}${alert ? ' is-alert' : ''}`} href={href}>
      <div className="au-kpi__top">
        <span className="au-kpi__icon">
          <Icon name={icon} size={19} />
        </span>
        <Icon name="arrowUpRight" size={16} className="au-kpi__go" />
      </div>
      <strong className="au-kpi__value">
        <Counter value={value} />
      </strong>
      <span className="au-kpi__label">{label}</span>
      <span className="au-kpi__caption">{caption}</span>
    </a>
  );
}

function GoldVault({ gold, urls }) {
  const stock = Number(gold.fine_gold_total) || 0;
  const required = Number(gold.current_req_gold) || 0;
  const coverage = required > 0 ? Math.min(100, (stock / required) * 100) : 100;
  const covered = stock >= required;
  const minusCount = Number(gold.minus_karigar_count) || 0;

  return (
    <section className="au-vault" data-app-tour="inventory-summary">
      <div className="au-vault__metric au-vault__metric--lead">
        <span className="au-vault__icon">
          <Icon name="coins" size={20} />
        </span>
        <div>
          <span className="au-label">Fine gold stock</span>
          <strong className="au-metal">
            <Counter value={stock} digits={3} /> <small>gm</small>
          </strong>
          <p>Pure-gold equivalent across inventory</p>
        </div>
      </div>

      <div className="au-vault__metric">
        <span className="au-vault__icon">
          <Icon name="target" size={20} />
        </span>
        <div className="au-grow">
          <span className="au-label">Pending gold requirement</span>
          <strong className="au-metal">
            <Counter value={required} digits={3} /> <small>gm</small>
          </strong>
          <div className="au-coverage" title={`Stock covers ${Math.round(coverage)}% of pending requirement`}>
            <div className="au-coverage__bar">
              <span style={{ width: `${coverage}%` }} className={covered ? 'is-ok' : 'is-short'} />
            </div>
            <em className={covered ? 'is-ok' : 'is-short'}>
              {required > 0 ? `${Math.round(coverage)}% covered by stock` : 'No pending requirement'}
            </em>
          </div>
          {minusCount > 0 ? (
            <p className="au-warn">
              <Icon name="alert" size={13} /> {minusCount} karigar minus balance · {inr(gold.minus_karigar_gold, 3)} gm
            </p>
          ) : (
            <p className="au-ok">
              <Icon name="check" size={13} /> All karigar balances are clear
            </p>
          )}
        </div>
      </div>

      <div className="au-vault__metric">
        <span className="au-vault__icon">
          <Icon name="rupee" size={20} />
        </span>
        <div>
          <span className="au-label">Average pure gold price</span>
          <strong className="au-metal">
            ₹<Counter value={gold.avg_price_pure} digits={2} /> <small>/ gm</small>
          </strong>
          <p>Weighted purchase price per pure gram</p>
        </div>
      </div>
    </section>
  );
}

function Panel({ eyebrow, title, action, children, className = '', tour }) {
  return (
    <section className={`au-panel ${className}`} data-app-tour={tour}>
      <header className="au-panel__head">
        <div>
          {eyebrow && <span className="au-eyebrow">{eyebrow}</span>}
          <h2>{title}</h2>
        </div>
        {action}
      </header>
      {children}
    </section>
  );
}

function AssignmentQueue({ rows, todayIso, href }) {
  const [query, setQuery] = useState('');
  const [sortByDue, setSortByDue] = useState(false);

  const visible = useMemo(() => {
    const term = query.trim().toLowerCase();
    let list = rows.filter((row) =>
      !term ||
      [row.orderNo, row.orderName, row.orderType, row.source, row.priority]
        .join(' ')
        .toLowerCase()
        .includes(term),
    );
    if (sortByDue) {
      list = [...list].sort((a, b) => (a.dueRaw || '9999').localeCompare(b.dueRaw || '9999'));
    }
    return list;
  }, [rows, query, sortByDue]);

  return (
    <Panel
      eyebrow="Action queue"
      title="Orders needing assignment"
      className="au-panel--queue"
      action={
        <a className="au-btn au-btn--outline au-btn--sm" href={href}>
          View queue <Icon name="arrowRight" size={14} />
        </a>
      }
    >
      {rows.length === 0 ? (
        <div className="au-empty">
          <span className="au-empty__icon au-empty__icon--ok">
            <Icon name="check" size={22} />
          </span>
          <strong>Assignment queue is clear</strong>
          <span>Every active order has a karigar assigned.</span>
        </div>
      ) : (
        <>
          <div className="au-toolbar">
            <label className="au-search">
              <Icon name="search" size={15} />
              <input
                type="search"
                placeholder="Search order, name, customer…"
                value={query}
                onChange={(event) => setQuery(event.target.value)}
              />
            </label>
            <button
              type="button"
              className={`au-chip${sortByDue ? ' is-on' : ''}`}
              onClick={() => setSortByDue((v) => !v)}
              aria-pressed={sortByDue}
            >
              <Icon name="sort" size={14} /> Due date
            </button>
          </div>
          <div className="au-table-wrap">
            <table className="au-table" data-dt-skip="1">
              <thead>
                <tr>
                  <th>Order</th>
                  <th>Type</th>
                  <th>Customer</th>
                  <th className="au-right">Due</th>
                </tr>
              </thead>
              <tbody>
                {visible.map((row) => {
                  const overdue = row.dueRaw && row.dueRaw < todayIso;
                  const dueToday = row.dueRaw && row.dueRaw === todayIso;
                  return (
                    <tr key={row.id} onClick={() => (window.location.href = row.url)}>
                      <td>
                        <div className="au-order">
                          <Thumb src={row.thumbnailUrl} icon="image" />
                          <div>
                            <a href={row.url} onClick={(e) => e.stopPropagation()}>
                              {row.orderNo}
                            </a>
                            <span>{row.orderName || 'Unnamed order'}</span>
                          </div>
                        </div>
                      </td>
                      <td>
                        <span className="au-tag">{row.orderType || '—'}</span>
                        {row.priority && <span className="au-tag au-tag--soft">{row.priority}</span>}
                      </td>
                      <td className="au-muted-cell">{row.source}</td>
                      <td className="au-right">
                        {row.dueDate ? (
                          <span className={`au-due${overdue ? ' is-overdue' : dueToday ? ' is-today' : ''}`}>
                            <Icon name="calendar" size={13} />
                            {row.dueDate}
                          </span>
                        ) : (
                          <span className="au-faint">Not set</span>
                        )}
                      </td>
                    </tr>
                  );
                })}
                {visible.length === 0 && (
                  <tr>
                    <td colSpan={4} className="au-table__empty">
                      No orders match “{query}”.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </>
      )}
    </Panel>
  );
}

function RecentOrders({ rows, href }) {
  return (
    <Panel
      eyebrow="Live activity"
      title="Recent orders"
      className="au-panel--recent"
      action={
        <a className="au-btn au-btn--outline au-btn--sm" href={href}>
          View all <Icon name="arrowRight" size={14} />
        </a>
      }
    >
      {rows.length === 0 ? (
        <div className="au-empty">
          <span className="au-empty__icon">
            <Icon name="inbox" size={22} />
          </span>
          <strong>No orders available</strong>
          <span>New orders will appear here.</span>
        </div>
      ) : (
        <ol className="au-feed">
          {rows.map((row) => (
            <li key={row.id}>
              <a href={row.url}>
                <Thumb src={row.thumbnailUrl} icon="bag" />
                <div className="au-feed__body">
                  <strong>{row.orderNo}</strong>
                  <span>
                    {row.orderName || 'Unnamed order'} · {row.source}
                  </span>
                  <small>{row.createdAt}</small>
                </div>
                <StatusPill status={row.status} />
              </a>
            </li>
          ))}
        </ol>
      )}
    </Panel>
  );
}

export default function Dashboard({ data }) {
  const counts = data.counts || {};
  const urls = data.urls || {};
  const trend = data.ordersTrend || [];
  const trendTotal = trend.reduce((sum, p) => sum + p.total, 0);
  const lastWeek = trend.slice(-7).reduce((sum, p) => sum + p.total, 0);
  const prevWeek = trend.slice(-14, -7).reduce((sum, p) => sum + p.total, 0);
  const delta = prevWeek > 0 ? Math.round(((lastWeek - prevWeek) / prevWeek) * 100) : null;

  return (
    <div className="au-dash">
      <Hero data={data} />

      <div className="au-kpis" data-app-tour="dashboard-kpis">
        <KpiCard
          href={urls.freshOrders}
          icon="alert"
          label="Needs assignment"
          caption="Open production queue"
          value={counts.unassignedOrders}
          tone="ruby"
          alert={counts.unassignedOrders > 0}
        />
        <KpiCard href={urls.customers} icon="users" label="Active customers" caption="Customer directory" value={counts.customers} tone="gold" />
        <KpiCard href={urls.orders} icon="activity" label="Active orders" caption="Currently in workflow" value={counts.activeOrders} tone="sapphire" />
        <KpiCard href={urls.orders} icon="send" label="Dispatched today" caption={data.today} value={counts.dispatchedToday} tone="emerald" />
      </div>

      <GoldVault gold={data.goldCards || {}} urls={urls} />

      <div className="au-grid au-grid--charts">
        <Panel
          eyebrow="Order intake"
          title="Last 14 days"
          action={
            <div className="au-stat-inline">
              <strong>{trendTotal}</strong>
              <span>orders</span>
              {delta !== null && (
                <em className={delta >= 0 ? 'is-up' : 'is-down'}>
                  {delta >= 0 ? '▲' : '▼'} {Math.abs(delta)}% vs prior week
                </em>
              )}
            </div>
          }
        >
          <TrendChart points={trend} />
        </Panel>
        <Panel eyebrow="Pipeline" title="Orders by status">
          <StatusDonut items={data.statusBreakdown || []} />
        </Panel>
      </div>

      <div className="au-grid au-grid--activity" data-app-tour="dashboard-activity">
        <AssignmentQueue rows={data.ordersNeedingAssignment || []} todayIso={data.todayIso} href={urls.freshOrders} />
        <RecentOrders rows={data.recentOrders || []} href={urls.orders} />
      </div>
    </div>
  );
}
